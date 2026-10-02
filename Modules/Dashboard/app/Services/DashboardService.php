<?php

namespace Modules\Dashboard\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Activity\Services\ActivityQueryService;
use Modules\Dashboard\Data\DashboardSummaryData;
use Modules\Projects\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;

class DashboardService
{
    public function __construct(
        private readonly ActivityQueryService $activity,
        private readonly ProjectRepositoryInterface $projects,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    public function summary(User $user): DashboardSummaryData
    {
        $projectSummary = $this->projects->dashboardStatusSummaryFor($user);
        $taskSummary = $this->tasks->dashboardSummaryFor($user);

        return new DashboardSummaryData(
            activeProjects: $projectSummary['active'],
            completedProjects: $projectSummary['completed'],
            archivedProjects: $projectSummary['archived'],
            totalTasks: $taskSummary['totalTasks'],
            overdue: $taskSummary['overdue'],
            completedToday: $taskSummary['completedToday'],
            projectStatusDistribution: $projectSummary['distribution'],
            taskStatusDistribution: $taskSummary['taskStatusDistribution'],
            taskTypeDistribution: $taskSummary['taskTypeDistribution'],
        );
    }

    /** @return array<string, mixed> */
    public function page(User $user): array
    {
        $queues = $this->tasks->dashboardPageQueuesFor($user);

        return [
            ...$this->summary($user)->toViewData(),
            'myTasks' => $queues['assigned'],
            'reportedTasks' => $queues['reported'],
            'watchedTasks' => $queues['watched'],
            'overdueTasks' => $queues['overdue'],
            'completedTodayTasks' => $queues['completedToday'],
            'recentActivity' => $this->activity->recentForUser($user, 8),
        ];
    }

    /** @return Collection<int, Task> */
    public function myTasks(User $user): Collection
    {
        return $this->tasks->assignedQueueFor($user);
    }

    /** @return Collection<int, Task> */
    public function reportedTasks(User $user): Collection
    {
        return $this->tasks->reportedQueueFor($user);
    }

    /** @return Collection<int, Task> */
    public function watchedTasks(User $user): Collection
    {
        return $this->tasks->watchedQueueFor($user);
    }

    /** @return Collection<int, Task> */
    public function overdueTasks(User $user): Collection
    {
        return $this->tasks->overdueQueueFor($user);
    }

    /** @return Collection<int, Task> */
    public function completedTodayTasks(User $user): Collection
    {
        return $this->tasks->completedTodayQueueFor($user);
    }

    public function paginateOverdue(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->tasks->paginateOverdueFor($user, $perPage);
    }
}
