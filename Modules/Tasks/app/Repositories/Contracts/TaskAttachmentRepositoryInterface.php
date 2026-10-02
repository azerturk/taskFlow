<?php

namespace Modules\Tasks\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;

interface TaskAttachmentRepositoryInterface
{
    public function forTask(Task $task): Collection;

    public function paginateForTask(Task $task, int $perPage): LengthAwarePaginator;

    public function save(TaskAttachment $attachment): TaskAttachment;

    public function prepareForTask(Task $task, TaskAttachment $attachment): TaskAttachment;

    public function findForTaskOrFail(Task $task, int $id): TaskAttachment;

    public function delete(TaskAttachment $attachment): void;
}
