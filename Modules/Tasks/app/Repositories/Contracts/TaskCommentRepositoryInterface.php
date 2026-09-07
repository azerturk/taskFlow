<?php

namespace Modules\Tasks\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskComment;

interface TaskCommentRepositoryInterface
{
    public function forTask(Task $task): Collection;

    public function save(TaskComment $comment): TaskComment;

    public function prepare(TaskComment $comment): TaskComment;

    public function findForTaskOrFail(Task $task, int $id): TaskComment;

    public function delete(TaskComment $comment): void;
}
