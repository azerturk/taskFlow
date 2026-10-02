<?php

namespace Modules\Tasks\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Projects\Models\Project;
use Modules\Tasks\Data\TaskFiltersData;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;

class TaskBoardQueryService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly TaskStatusService $statuses,
        private readonly TaskRankService $ranks,
    ) {}

    /** @return array<string, Collection<int, Task>> */
    public function forProject(Project $project, User $user, TaskFiltersData $filters): array
    {
        $tasks = $this->tasks->boardFor($project, $user, $filters);

        return collect(TaskStatus::cases())
            ->mapWithKeys(fn (TaskStatus $status) => [$status->value => $tasks->where('status', $status)->values()])
            ->all();
    }

    /** @return array{columns: array<string, Collection<int, Task>>, transitions: array<int, list<TaskStatus>>, canReorder: bool} */
    public function page(Project $project, User $user, TaskFiltersData $filters): array
    {
        $columns = $this->forProject($project, $user, $filters);
        $tasks = collect($columns)->reduce(
            fn (Collection $all, Collection $column): Collection => $all->concat($column),
            collect(),
        );

        return [
            'columns' => $columns,
            'transitions' => $this->statuses->availableStatusesForBoard($tasks, $project, $user),
            'canReorder' => $this->ranks->canReorder($project, $user),
        ];
    }
}
