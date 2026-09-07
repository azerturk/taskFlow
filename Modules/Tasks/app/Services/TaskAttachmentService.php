<?php

namespace Modules\Tasks\Services;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Media\Exceptions\MediaBatchStorageException;
use Modules\Media\Models\Media;
use Modules\Media\Services\MediaMetadataService;
use Modules\Media\Services\MediaStorageService;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Exceptions\TaskMutationNotAllowed;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Repositories\Contracts\TaskAttachmentRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TaskAttachmentService
{
    public function __construct(
        private readonly TaskAttachmentRepositoryInterface $attachments,
        private readonly ActivityRecorder $activity,
        private readonly MediaStorageService $storage,
        private readonly MediaMetadataService $metadata,
        private readonly ProjectMemberService $members,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    public function upload(Task $task, User $actor, UploadedFile $file): TaskAttachment
    {
        return $this->uploadMany($task, $actor, [$file])[0];
    }

    public function paginateFor(Task $task, User $actor, int $perPage): LengthAwarePaginator
    {
        $task = $this->tasks->withProject($task);
        $this->ensureVisible($task, $actor);

        return $this->attachments->paginateForTask($task, $perPage);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, TaskAttachment>
     */
    public function uploadMany(Task $task, User $actor, array $files): array
    {
        $task = $this->tasks->withProject($task);
        $this->ensureVisible($task, $actor);
        if ($task->project->status !== ProjectStatus::Active
            || ! $actor->hasPermissionTo(PermissionName::AttachmentsUpload->value)) {
            throw new TaskMutationNotAllowed('Only authorized active project members may upload attachments.');
        }

        try {
            $stored = $this->storage->storeFiles($files);
        } catch (MediaBatchStorageException $exception) {
            $this->storage->compensateStored($actor, $exception->storedItems(), $exception);
            throw $exception;
        }

        try {
            return DB::transaction(function () use ($task, $actor, $stored): array {
                $mediaItems = $this->metadata->registerManyWithinTransaction($actor, $stored);

                return array_map(function (Media $media) use ($task, $actor): TaskAttachment {
                    $attachment = $this->attachments->save(new TaskAttachment([
                        'task_id' => $task->id,
                        'media_id' => $media->id,
                    ]));
                    $this->activity->record(ActivityEvent::AttachmentUploaded, $actor, $attachment, [
                        'project_id' => $task->project_id,
                        'task_id' => $task->id,
                        'attachment_id' => $attachment->id,
                        'media_uuid' => $media->uuid,
                        'filename' => $media->original_name,
                        'mime_type' => $media->mime_type,
                        'size' => $media->size,
                    ]);
                    $media->setRelation('uploader', $actor);
                    $attachment->setRelation('media', $media);

                    return $attachment;
                }, $mediaItems);
            });
        } catch (Throwable $e) {
            $this->storage->compensateStored($actor, $stored, $e);
            throw $e;
        }
    }

    public function download(Task $task, TaskAttachment $attachment, User $actor): StreamedResponse
    {
        return $this->storage->download($this->visibleMedia($task, $attachment, $actor));
    }

    public function preview(Task $task, TaskAttachment $attachment, User $actor): StreamedResponse
    {
        return $this->storage->preview($this->visibleMedia($task, $attachment, $actor));
    }

    public function delete(Task $task, TaskAttachment $attachment, User $actor): void
    {
        $task = $this->tasks->withProject($task);
        $attachment = $this->attachments->prepareForTask($task, $attachment);
        $this->ensureVisible($task, $actor);
        $media = $this->mediaFor($attachment);

        if ($attachment->task->project->status !== ProjectStatus::Active
            || ! $actor->hasPermissionTo(PermissionName::AttachmentsDelete->value)
            || (! $this->members->canManage($attachment->task->project, $actor)
                && $media->uploaded_by !== $actor->id)) {
            throw new TaskMutationNotAllowed('Only the uploader or a project manager may delete an attachment.');
        }

        DB::transaction(function () use ($attachment, $actor, $media) {
            $properties = ['project_id' => $attachment->task->project_id, 'task_id' => $attachment->task_id, 'attachment_id' => $attachment->id, 'media_uuid' => $media->uuid, 'filename' => $media->original_name];
            $this->attachments->delete($attachment);
            $this->activity->record(ActivityEvent::AttachmentDeleted, $actor, $attachment, $properties);
        });
        $this->storage->delete($media);
    }

    private function mediaFor(TaskAttachment $attachment): Media
    {
        $media = $attachment->media;

        if (! $media instanceof Media) {
            throw new \LogicException('The attachment has no media record.');
        }

        return $media;
    }

    private function visibleMedia(Task $task, TaskAttachment $attachment, User $actor): Media
    {
        $task = $this->tasks->withProject($task);
        $this->ensureVisible($task, $actor);
        $attachment = $this->attachments->prepareForTask($task, $attachment);

        return $this->mediaFor($attachment);
    }

    private function ensureVisible(Task $task, User $actor): void
    {
        if (! $actor->isActive()
            || ! $actor->hasPermissionTo(PermissionName::TasksView->value)
            || ! $this->members->canParticipate($task->project, $actor)) {
            throw new TaskMutationNotAllowed('Only authorized project participants may access attachments.');
        }
    }
}
