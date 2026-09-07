<?php

namespace Modules\Tasks\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Projects\Models\Project;
use Modules\Tasks\Data\TaskFiltersData;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;

class BacklogQueryService
{
    public function __construct(private readonly TaskRepositoryInterface $tasks, private readonly TaskRankService $ranks) {}

    public function paginate(Project $project, User $user, TaskFiltersData $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->tasks->backlogFor($project, $user, $filters, $perPage);
    }

    /** @return array{tasks: LengthAwarePaginator, canReorder: bool} */
    public function page(Project $project, User $user, TaskFiltersData $filters, int $perPage = 25): array
    {
        return [
            'tasks' => $this->paginate($project, $user, $filters, $perPage),
            'canReorder' => $this->ranks->canReorder($project, $user),
        ];
    }
}
