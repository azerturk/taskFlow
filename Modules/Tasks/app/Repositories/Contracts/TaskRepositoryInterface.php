<?php

namespace Modules\Tasks\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Projects\Models\Project;
use Modules\Tasks\Data\TaskFiltersData;
use Modules\Tasks\Models\Task;

interface TaskRepositoryInterface
{
    public function paginateFor(User $user, TaskFiltersData $filters, int $perPage = 12): LengthAwarePaginator;

    public function findOrFail(int $id): Task;

    public function findVisibleOrFail(User $user, int $id): Task;

    public function findForProject(Project $project, int $id): ?Task;

    public function withProject(Task $task): Task;

    public function prepareForResource(Task $task): Task;

    public function prepareForWebDetail(Task $task): Task;

    public function prepareForEdit(Task $task): Task;

    public function save(Task $task): Task;

    public function lockForRankMutation(Task $task): Task;

    public function hasOpenSubtasks(Task $task): bool;

    public function hasSubtasks(Task $task): bool;

    /** @return Collection<int, Task> */
    public function standardParentsForProject(Project $project): Collection;

    public function backlogFor(Project $project, User $user, TaskFiltersData $filters, int $perPage): LengthAwarePaginator;

    /** @return Collection<int, Task> */
    public function boardFor(Project $project, User $user, TaskFiltersData $filters): Collection;

    public function delete(Task $task): void;

    /** @return Collection<int, Task> */
    public function lockOpenAssignmentsFor(User $user): Collection;

    public function unassignForSuspension(Task $task): Task;

    public function existsForProject(Project $project): bool;

    public function openAssignmentCountFor(Project $project, User $user): int;

    /** @return Collection<int, Project> */
    public function filterProjectsFor(User $user): Collection;

    /** @return Collection<int, User> */
    public function filterUsersFor(User $user): Collection;

    /** @return Collection<int, User> */
    public function filterReportersFor(User $user): Collection;

    /** @return Collection<int, Task> */
    public function filterParentsFor(User $user): Collection;

    public function filterLabelsFor(User $user): Collection;

    public function appendToStatusColumn(Task $task): Task;

    public function reorderWithinStatusColumn(Task $task, ?int $beforeTaskId, ?int $afterTaskId): bool;

    /** @param list<int> $ids
     * @return Collection<int, Task>
     */
    public function visibleByIdsFor(User $user, array $ids): Collection;

    /** @return array{totalTasks: int, overdue: int, completedToday: int, taskStatusDistribution: array<string, int>, taskTypeDistribution: array<string, int>} */
    public function dashboardSummaryFor(User $user): array;

    /** @return array{assigned: Collection<int, Task>, reported: Collection<int, Task>, watched: Collection<int, Task>, overdue: Collection<int, Task>, completedToday: Collection<int, Task>} */
    public function dashboardPageQueuesFor(User $user): array;

    /** @return Collection<int, Task> */
    public function assignedQueueFor(User $user): Collection;

    /** @return Collection<int, Task> */
    public function reportedQueueFor(User $user): Collection;

    /** @return Collection<int, Task> */
    public function watchedQueueFor(User $user): Collection;

    /** @return Collection<int, Task> */
    public function overdueQueueFor(User $user): Collection;

    /** @return Collection<int, Task> */
    public function completedTodayQueueFor(User $user): Collection;

    public function paginateOverdueFor(User $user, int $perPage): LengthAwarePaginator;
}
