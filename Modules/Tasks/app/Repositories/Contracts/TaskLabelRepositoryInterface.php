<?php

namespace Modules\Tasks\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskLabel;

interface TaskLabelRepositoryInterface
{
    /** @return Collection<int, TaskLabel> */
    public function forProject(Project $project): Collection;

    public function save(TaskLabel $label): TaskLabel;

    public function delete(TaskLabel $label): void;

    public function findForProjectOrFail(Project $project, int $id): TaskLabel;

    /** @param list<int> $ids */
    public function countForProject(Project $project, array $ids): int;

    public function conflictsWith(Project $project, string $name, string $slug, ?TaskLabel $ignore = null): bool;

    /** @return list<int> */
    public function idsForTask(Task $task): array;

    /** @param list<int> $ids */
    public function syncForTask(Task $task, array $ids): void;
}
