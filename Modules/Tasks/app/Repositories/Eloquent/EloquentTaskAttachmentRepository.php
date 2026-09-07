<?php

namespace Modules\Tasks\Repositories\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Repositories\Contracts\TaskAttachmentRepositoryInterface;

class EloquentTaskAttachmentRepository implements TaskAttachmentRepositoryInterface
{
    public function forTask(Task $task): Collection
    {
        return TaskAttachment::query()->with('media.uploader')->where('task_id', $task->id)->latest()->get();
    }

    public function paginateForTask(Task $task, int $perPage): LengthAwarePaginator
    {
        return TaskAttachment::query()->with('media.uploader')->where('task_id', $task->id)->latest()->paginate($perPage)->withQueryString();
    }

    public function save(TaskAttachment $attachment): TaskAttachment
    {
        $attachment->save();

        return $attachment;
    }

    public function prepareForTask(Task $task, TaskAttachment $attachment): TaskAttachment
    {
        return TaskAttachment::query()
            ->with(['media.uploader', 'task.project'])
            ->whereKey($attachment->id)
            ->where('task_id', $task->id)
            ->firstOrFail();
    }

    public function findForTaskOrFail(Task $task, int $id): TaskAttachment
    {
        return TaskAttachment::query()
            ->with(['media.uploader', 'task.project'])
            ->where('task_id', $task->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    public function delete(TaskAttachment $attachment): void
    {
        $attachment->delete();
    }
}
