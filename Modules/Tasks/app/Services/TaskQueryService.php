<?php

namespace Modules\Tasks\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Activity\Services\ActivityQueryService;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\TaskFiltersData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskType;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskLabelRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;

class TaskQueryService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly ProjectMemberService $members,
        private readonly TaskLabelRepositoryInterface $labels,
        private readonly ActivityQueryService $activity,
    ) {}

    public function paginateFor(User $actor, TaskFiltersData $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->tasks->paginateFor($actor, $filters, $perPage);
    }

    public function filterOptionsFor(User $actor): array
    {
        return [
            'projects' => $this->tasks->filterProjectsFor($actor),
            'assignees' => $this->tasks->filterUsersFor($actor),
            'reporters' => $this->tasks->filterReportersFor($actor),
            'parents' => $this->tasks->filterParentsFor($actor),
            'labels' => $this->tasks->filterLabelsFor($actor),
        ];
    }

    public function createPage(Project $project): array
    {
        return [
            'project' => $project,
            'memberships' => $this->members->memberships($project),
            'priorities' => TaskPriority::cases(),
            'types' => TaskType::cases(),
            'parents' => $this->tasks->standardParentsForProject($project),
            'labels' => $this->labels->forProject($project),
        ];
    }

    public function detailPage(User $actor, Task $task, bool $includeActivity): array
    {
        $task = $this->tasks->prepareForWebDetail($task);

        return [
            'task' => $task,
            'memberships' => $this->members->memberships($task->project),
            'activities' => $includeActivity ? $this->activity->recentForTask($task) : null,
            'canViewActivity' => $includeActivity,
        ];
    }

    public function editPage(Task $task): array
    {
        $task = $this->tasks->prepareForEdit($task);

        return [
            'task' => $task,
            'types' => TaskType::cases(),
            'parents' => $this->tasks->standardParentsForProject($task->project)
                ->reject(fn (Task $parent): bool => $parent->id === $task->id),
            'labels' => $this->labels->forProject($task->project),
        ];
    }

    public function resource(Task $task): Task
    {
        return $this->tasks->prepareForResource($task);
    }
}
