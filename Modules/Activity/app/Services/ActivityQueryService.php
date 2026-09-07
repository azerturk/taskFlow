<?php

namespace Modules\Activity\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Activity\Data\ActivityFiltersData;
use Modules\Activity\Repositories\Contracts\ActivityRepositoryInterface;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Spatie\Activitylog\Models\Activity;

class ActivityQueryService
{
    public function __construct(private readonly ActivityRepositoryInterface $activities) {}

    /** @return Collection<int, Activity> */
    public function recentForProject(Project $project, int $limit = 5): Collection
    {
        return $this->activities->recentForProject($project, $limit);
    }

    /** @return Collection<int, Activity> */
    public function recentForTask(Task $task, int $limit = 5): Collection
    {
        return $this->activities->recentForTask($task, $limit);
    }

    /** @return Collection<int, Activity> */
    public function recentForUser(User $user, int $limit = 8): Collection
    {
        return $this->activities->recentForUser($user, $limit);
    }

    public function paginate(User $user, ActivityFiltersData $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->activities->paginateFor($user, $filters, $perPage);
    }

    /** @return array{activities: LengthAwarePaginator, filters: array, options: array} */
    public function page(User $user, ActivityFiltersData $filters, int $perPage = 20): array
    {
        return [
            'activities' => $this->paginate($user, $filters, $perPage),
            'filters' => $filters->forWeb(),
            'options' => $this->activities->filterOptionsFor($user),
        ];
    }

    public function filterOptions(User $user): array
    {
        return $this->activities->filterOptionsFor($user);
    }
}
