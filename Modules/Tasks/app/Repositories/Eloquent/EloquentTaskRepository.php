<?php

namespace Modules\Tasks\Repositories\Eloquent;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Projects\Models\Project;
use Modules\Tasks\Data\TaskFiltersData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Enums\TaskType;
use Modules\Tasks\Exceptions\InvalidTaskRankPosition;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskLabel;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Tasks\Support\TaskRankSequence;

class EloquentTaskRepository implements TaskRepositoryInterface
{
    public function paginateFor(User $user, TaskFiltersData $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->applySorting(
            $this->visibleTo($this->baseQuery($filters), $user),
            $filters,
        )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): Task
    {
        return Task::query()->with(['project', 'parent', 'subtasks'])->findOrFail($id);
    }

    public function findVisibleOrFail(User $user, int $id): Task
    {
        return $this->visibleTo(Task::query(), $user)
            ->with('project')
            ->whereKey($id)
            ->firstOrFail();
    }

    public function findForProject(Project $project, int $id): ?Task
    {
        return Task::query()
            ->where('project_id', $project->id)
            ->whereKey($id)
            ->first();
    }

    public function withProject(Task $task): Task
    {
        return Task::query()->with(['project', 'assignee'])->whereKey($task->id)->firstOrFail();
    }

    public function prepareForResource(Task $task): Task
    {
        return Task::query()
            ->with(['project', 'creator', 'assignee', 'labels', 'parent', 'subtasks'])
            ->whereKey($task->id)
            ->firstOrFail();
    }

    public function prepareForWebDetail(Task $task): Task
    {
        return Task::query()
            ->with([
                'project', 'creator', 'assignee', 'labels', 'parent', 'subtasks',
                'comments.user', 'attachments.media.uploader', 'watchers',
            ])
            ->whereKey($task->id)
            ->firstOrFail();
    }

    public function prepareForEdit(Task $task): Task
    {
        return Task::query()->with(['project', 'labels'])->whereKey($task->id)->firstOrFail();
    }

    public function save(Task $task): Task
    {
        $task->save();

        return $task;
    }

    public function lockForRankMutation(Task $task): Task
    {
        Project::query()->whereKey($task->project_id)->lockForUpdate()->firstOrFail();

        return Task::query()->with(['project', 'assignee'])->whereKey($task->id)->lockForUpdate()->firstOrFail();
    }

    public function hasOpenSubtasks(Task $task): bool
    {
        return Task::query()
            ->where('parent_id', $task->id)
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])
            ->exists();
    }

    public function hasSubtasks(Task $task): bool
    {
        return Task::query()->where('parent_id', $task->id)->exists();
    }

    public function standardParentsForProject(Project $project): Collection
    {
        return Task::query()
            ->where('project_id', $project->id)
            ->where('type', '!=', TaskType::Subtask->value)
            ->orderBy('number')
            ->get(['id', 'project_id', 'number', 'title', 'type']);
    }

    public function backlogFor(Project $project, User $user, TaskFiltersData $filters, int $perPage): LengthAwarePaginator
    {
        return $this->applySorting(
            $this->visibleTo($this->baseQuery($filters), $user)
                ->where('project_id', $project->id)
                ->where('status', TaskStatus::Backlog->value),
            $filters,
        )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function boardFor(Project $project, User $user, TaskFiltersData $filters): Collection
    {
        return $this->applySorting(
            $this->visibleTo($this->baseQuery($filters, ['project', 'assignee', 'creator', 'labels']), $user)
                ->where('project_id', $project->id),
            $filters,
        )
            ->get();
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function lockOpenAssignmentsFor(User $user): Collection
    {
        return Task::query()
            ->with(['project', 'assignee'])
            ->where('assignee_id', $user->id)
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function unassignForSuspension(Task $task): Task
    {
        $task->assignee_id = null;
        $task->version++;
        $task->save();

        return $task;
    }

    public function existsForProject(Project $project): bool
    {
        return Task::query()->where('project_id', $project->id)->exists();
    }

    public function openAssignmentCountFor(Project $project, User $user): int
    {
        return Task::query()
            ->where('project_id', $project->id)
            ->where('assignee_id', $user->id)
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])
            ->count();
    }

    public function filterProjectsFor(User $user): Collection
    {
        return Project::query()
            ->whereIn('id', $this->visibleTo(Task::query(), $user)->select('project_id'))
            ->orderBy('name')
            ->get();
    }

    public function filterUsersFor(User $user): Collection
    {
        return User::query()
            ->whereIn('id', $this->visibleTo(Task::query(), $user)->whereNotNull('assignee_id')->select('assignee_id'))
            ->orderBy('name')
            ->get();
    }

    public function filterReportersFor(User $user): Collection
    {
        return User::query()
            ->whereIn('id', $this->visibleTo(Task::query(), $user)->select('creator_id'))
            ->orderBy('name')
            ->get();
    }

    public function filterParentsFor(User $user): Collection
    {
        return $this->visibleTo(Task::query(), $user)
            ->where('type', '!=', TaskType::Subtask->value)
            ->orderBy('number')
            ->get(['id', 'project_id', 'number', 'title', 'type']);
    }

    public function filterLabelsFor(User $user): Collection
    {
        return TaskLabel::query()
            ->whereIn('project_id', $this->visibleTo(Task::query(), $user)->select('project_id'))
            ->orderBy('name')
            ->get();
    }

    public function appendToStatusColumn(Task $task): Task
    {
        $this->lockRankProject($task->project_id);
        $column = Task::query()
            ->withTrashed()
            ->where('project_id', $task->project_id)
            ->where('status', $task->status->value)
            ->when($task->exists, fn (Builder $query) => $query->whereKeyNot($task->id))
            ->orderBy('rank')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $task->rank = TaskRankSequence::next($column->pluck('rank')->all());
        $task->save();

        return $task;
    }

    public function reorderWithinStatusColumn(Task $task, ?int $beforeTaskId, ?int $afterTaskId): bool
    {
        $this->lockRankProject($task->project_id);
        $locked = Task::query()
            ->withTrashed()
            ->where('project_id', $task->project_id)
            ->where('status', $task->status->value)
            ->orderBy('rank')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $items = $locked->whereNull('deleted_at')->values();
        $originalIds = $items->pluck('id')->all();
        $ids = $originalIds;
        $currentIndex = array_search($task->id, $ids, true);

        if ($currentIndex === false) {
            throw new InvalidTaskRankPosition('The task is not present in its status column.');
        }

        array_splice($ids, $currentIndex, 1);

        foreach ([$beforeTaskId, $afterTaskId] as $neighborId) {
            if ($neighborId !== null && ! in_array($neighborId, $ids, true)) {
                throw new InvalidTaskRankPosition('Reorder neighbors must belong to the same active project and status column.');
            }
        }

        if ($beforeTaskId !== null && $afterTaskId !== null) {
            $beforeIndex = array_search($beforeTaskId, $ids, true);
            $afterIndex = array_search($afterTaskId, $ids, true);

            if ($beforeIndex === false || $afterIndex === false || $beforeIndex + 1 !== $afterIndex) {
                throw new InvalidTaskRankPosition('Reorder neighbors must be adjacent.');
            }
        }

        $insertAt = match (true) {
            $afterTaskId !== null => array_search($afterTaskId, $ids, true),
            $beforeTaskId !== null => array_search($beforeTaskId, $ids, true) + 1,
            default => count($ids),
        };
        array_splice($ids, $insertAt, 0, [$task->id]);

        if ($ids === $originalIds) {
            return false;
        }

        $rows = $items->keyBy('id');
        $temporaryBase = ((int) $locked->max('rank')) + 1000;
        $finalRanks = TaskRankSequence::rebalance(
            $ids,
            $locked->whereNotNull('deleted_at')->pluck('rank')->all(),
        );

        foreach ($ids as $offset => $id) {
            $row = $rows->get($id);
            $this->saveRankWithoutTimestamps($row, $temporaryBase + (($offset + 1) * 1000));
        }

        foreach ($ids as $id) {
            $row = $rows->get($id);
            if ($row->id === $task->id) {
                $row->rank = $finalRanks[$id];
                $row->version++;
                $row->save();

                continue;
            }

            $this->saveRankWithoutTimestamps($row, $finalRanks[$id]);
        }

        $task->setRawAttributes($rows->get($task->id)->getAttributes(), true);

        return true;
    }

    public function visibleByIdsFor(User $user, array $ids): Collection
    {
        return $this->visibleTo(Task::query(), $user)
            ->with('project')
            ->whereKey($ids)
            ->get();
    }

    public function dashboardSummaryFor(User $user): array
    {
        $today = today()->toDateString();
        $tomorrow = today()->addDay()->toDateString();
        $closed = [TaskStatus::Done->value, TaskStatus::Cancelled->value];
        $selects = ['COUNT(*) AS total_tasks'];
        $bindings = [];
        foreach (TaskStatus::cases() as $index => $status) {
            $selects[] = "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS status_{$index}";
            $bindings[] = $status->value;
        }
        $selects[] = 'SUM(CASE WHEN due_at IS NOT NULL AND due_at < ? AND status NOT IN (?, ?) THEN 1 ELSE 0 END) AS overdue';
        array_push($bindings, $today, ...$closed);
        $selects[] = 'SUM(CASE WHEN status = ? AND completed_at >= ? AND completed_at < ? THEN 1 ELSE 0 END) AS completed_today';
        array_push($bindings, TaskStatus::Done->value, $today, $tomorrow);

        $metrics = $this->visibleTo(Task::query(), $user)
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();
        $statusDistribution = [];
        foreach (TaskStatus::cases() as $index => $status) {
            $statusDistribution[$status->value] = (int) ($metrics?->{"status_{$index}"} ?? 0);
        }

        $typeCounts = $this->visibleTo(Task::query(), $user)
            ->selectRaw('type, COUNT(*) AS aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(fn ($count): int => (int) $count)
            ->all();
        $typeDistribution = [];
        foreach (TaskType::cases() as $type) {
            $typeDistribution[$type->value] = $typeCounts[$type->value] ?? 0;
        }

        return [
            'totalTasks' => (int) ($metrics?->total_tasks ?? 0),
            'overdue' => (int) ($metrics?->overdue ?? 0),
            'completedToday' => (int) ($metrics?->completed_today ?? 0),
            'taskStatusDistribution' => $statusDistribution,
            'taskTypeDistribution' => $typeDistribution,
        ];
    }

    public function assignedQueueFor(User $user): Collection
    {
        return $this->queue($this->visibleTo(Task::query(), $user)->where('assignee_id', $user->id));
    }

    public function reportedQueueFor(User $user): Collection
    {
        return $this->queue($this->visibleTo(Task::query(), $user)->where('creator_id', $user->id));
    }

    public function watchedQueueFor(User $user): Collection
    {
        return $this->queue($this->visibleTo(Task::query(), $user)->whereHas('watchers', fn ($watchers) => $watchers->whereKey($user->id)));
    }

    public function overdueQueueFor(User $user): Collection
    {
        return $this->queue($this->overdueQuery($user), false);
    }

    public function completedTodayQueueFor(User $user): Collection
    {
        return $this->queue(
            $this->visibleTo(Task::query(), $user)
                ->where('status', TaskStatus::Done->value)
                ->whereDate('completed_at', today()->toDateString()),
            false,
        );
    }

    public function dashboardPageQueuesFor(User $user): array
    {
        return [
            'assigned' => $this->queue(
                $this->visibleTo(Task::query(), $user)->where('assignee_id', $user->id),
                relations: ['project'],
            ),
            'reported' => $this->queue(
                $this->visibleTo(Task::query(), $user)->where('creator_id', $user->id),
                relations: ['project'],
            ),
            'watched' => $this->queue(
                $this->visibleTo(Task::query(), $user)->whereHas('watchers', fn ($watchers) => $watchers->whereKey($user->id)),
                relations: ['project'],
            ),
            'overdue' => $this->queue($this->overdueQuery($user), false, ['project']),
            'completedToday' => $this->queue(
                $this->visibleTo(Task::query(), $user)
                    ->where('status', TaskStatus::Done->value)
                    ->whereDate('completed_at', today()->toDateString()),
                false,
                ['project'],
            ),
        ];
    }

    public function paginateOverdueFor(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->overdueQuery($user)
            ->with($this->taskRelations())
            ->orderBy('due_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function baseQuery(TaskFiltersData $filters, ?array $relations = null): Builder
    {
        return Task::query()
            ->with($relations ?? ['project', 'creator', 'assignee', 'labels', 'parent:id,project_id,number,title,type', 'subtasks:id,parent_id,project_id,number,title,type'])
            ->when(filled($filters->search), fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$filters->search}%")
                ->orWhere('title', 'like', "%{$filters->search}%")
                ->orWhere('description', 'like', "%{$filters->search}%")))
            ->when($filters->statuses !== [], fn ($query) => $query->whereIn('status', $filters->statuses))
            ->when($filters->types !== [], fn ($query) => $query->whereIn('type', $filters->types))
            ->when($filters->priorities !== [], fn ($query) => $query->whereIn('priority', $filters->priorities))
            ->when($filters->projectId, fn ($query, $id) => $query->where('project_id', $id))
            ->when($filters->assigneeId, fn ($query, $id) => $query->where('assignee_id', $id))
            ->when($filters->unassigned, fn ($query) => $query->whereNull('assignee_id'))
            ->when($filters->reporterId, fn ($query, $id) => $query->where('creator_id', $id))
            ->when($filters->parentId === 0, fn ($query) => $query->whereNull('parent_id'))
            ->when($filters->parentId !== null && $filters->parentId > 0, fn ($query) => $query->where('parent_id', $filters->parentId))
            ->when($filters->dueBefore, fn ($query, $date) => $query->whereDate('due_at', '<=', $date->toDateString()))
            ->when($filters->dueAfter, fn ($query, $date) => $query->whereDate('due_at', '>=', $date->toDateString()))
            ->when($filters->overdue, fn ($query) => $query->whereDate('due_at', '<', today())->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value]))
            ->when($filters->labelIds !== [], fn ($query) => $query->whereHas('labels', fn ($labels) => $labels->whereIn('task_labels.id', $filters->labelIds)));
    }

    private function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Admin->value)) {
            return $query;
        }

        return $query->whereHas('project', function ($projects) use ($user): void {
            $projects->where('owner_id', $user->id)
                ->orWhereHas('memberships', fn ($memberships) => $memberships->where('user_id', $user->id));
        });
    }

    private function overdueQuery(User $user): Builder
    {
        return $this->visibleTo(Task::query(), $user)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value]);
    }

    /** @param list<string>|null $relations */
    private function queue(Builder $query, bool $prioritizeDue = true, ?array $relations = null): Collection
    {
        $query->with($relations ?? $this->taskRelations());

        if ($prioritizeDue) {
            $query->orderByRaw(
                'CASE WHEN due_at IS NOT NULL AND due_at < ? AND status NOT IN (?, ?) THEN 0 ELSE 1 END',
                [now(), TaskStatus::Done->value, TaskStatus::Cancelled->value],
            )->orderByRaw('due_at IS NULL')->orderBy('due_at');
        } else {
            $query->latest('updated_at');
        }

        return $query->latest('id')->take(6)->get();
    }

    /** @return list<string> */
    private function taskRelations(): array
    {
        return ['project', 'creator', 'assignee', 'labels'];
    }

    private function sortColumn(TaskFiltersData $filters): string
    {
        return in_array(ltrim($filters->sort, '-'), ['created_at', 'updated_at', 'due_at', 'priority', 'status', 'number', 'rank'], true)
            ? ltrim($filters->sort, '-')
            : 'created_at';
    }

    private function sortDirection(TaskFiltersData $filters): string
    {
        return str_starts_with($filters->sort, '-') ? 'desc' : 'asc';
    }

    private function applySorting(Builder $query, TaskFiltersData $filters): Builder
    {
        $direction = $this->sortDirection($filters);

        if ($this->sortColumn($filters) === 'priority') {
            $priorities = array_map(fn (TaskPriority $priority): string => $priority->value, TaskPriority::cases());

            return $query
                ->orderByRaw(
                    'CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END '.$direction,
                    $priorities,
                )
                ->orderBy('id', $direction);
        }

        return $query
            ->orderBy($this->sortColumn($filters), $direction)
            ->orderBy('id', $direction);
    }

    private function lockRankProject(int $projectId): void
    {
        Project::query()->whereKey($projectId)->lockForUpdate()->firstOrFail();
    }

    private function saveRankWithoutTimestamps(Task $task, int $rank): void
    {
        $usesTimestamps = $task->timestamps;
        $task->timestamps = false;

        try {
            $task->rank = $rank;
            $task->save();
        } finally {
            $task->timestamps = $usesTimestamps;
        }
    }
}
