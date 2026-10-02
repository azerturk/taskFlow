<?php

namespace Modules\Activity\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Activity\Data\ActivityFiltersData;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Spatie\Activitylog\Models\Activity;

interface ActivityRepositoryInterface
{
    /** @return Collection<int, Activity> */
    public function recentForProject(Project $project, int $limit): Collection;

    /** @return Collection<int, Activity> */
    public function recentForTask(Task $task, int $limit): Collection;

    /** @return Collection<int, Activity> */
    public function recentForUser(User $user, int $limit): Collection;

    public function paginateFor(User $user, ActivityFiltersData $filters, int $perPage): LengthAwarePaginator;

    /** @return array{events: Collection, projects: Collection, tasks: Collection, actors: Collection} */
    public function filterOptionsFor(User $user): array;
}
