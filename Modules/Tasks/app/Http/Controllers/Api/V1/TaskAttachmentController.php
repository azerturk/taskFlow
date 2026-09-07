<?php

namespace Modules\Tasks\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Modules\Media\Exceptions\MediaUploadValidationException;
use Modules\Tasks\Http\Requests\Api\V1\TaskAttachmentIndexRequest;
use Modules\Tasks\Http\Requests\UploadTaskAttachmentRequest;
use Modules\Tasks\Http\Resources\TaskAttachmentResource;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Services\TaskAttachmentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskAttachmentService $attachments) {}

    public function index(TaskAttachmentIndexRequest $request, Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        return TaskAttachmentResource::collection(
            $this->attachments->paginateFor($task, $request->user(), $request->integer('per_page', 20)),
        );
    }

    public function store(UploadTaskAttachmentRequest $request, Task $task): JsonResponse
    {
        $this->authorize('uploadAttachment', $task);

        try {
            $attachments = $this->attachments->uploadMany(
                $task,
                $request->user(),
                $request->file('media'),
            );
        } catch (MediaUploadValidationException $exception) {
            throw ValidationException::withMessages(['media' => [$exception->getMessage()]]);
        }

        return TaskAttachmentResource::collection($attachments)
            ->response()
            ->setStatusCode(201);
    }

    public function download(Task $task, TaskAttachment $media): StreamedResponse
    {
        abort_unless($media->task_id === $task->id, 404);
        $this->authorize('view', $task);

        return $this->attachments->download($task, $media, request()->user());
    }

    public function preview(Task $task, TaskAttachment $media): StreamedResponse
    {
        abort_unless($media->task_id === $task->id, 404);
        $this->authorize('view', $task);

        return $this->attachments->preview($task, $media, request()->user());
    }

    public function destroy(Task $task, TaskAttachment $media): JsonResponse
    {
        abort_unless($media->task_id === $task->id, 404);
        $this->authorize('deleteAttachment', [$task, $media]);
        $this->attachments->delete($task, $media, request()->user());

        return response()->json(null, 204);
    }
}
