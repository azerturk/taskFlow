<?php

namespace Modules\Tasks\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Exceptions\ProjectReadOnly;
use Modules\Projects\Models\Project;
use Modules\Projects\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Projects\Services\ProjectService;
use Modules\Tasks\Data\CreateTaskData;
use Modules\Tasks\Data\UpdateTaskData;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Enums\TaskType;
use Modules\Tasks\Exceptions\InvalidAssignee;
use Modules\Tasks\Exceptions\ParentTaskInvalid;
use Modules\Tasks\Exceptions\TaskMutationNotAllowed;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskWatcherRepositoryInterface;

class TaskService
{
    public function __construct(private readonly TaskRepositoryInterface $tasks, private readonly ProjectMemberService $members, private readonly ActivityRecorder $activity, private readonly ProjectService $projects, private readonly ProjectRepositoryInterface $projectQueries, private readonly UserRepositoryInterface $users, private readonly TaskLabelService $labels, private readonly TaskWatcherRepositoryInterface $watchers, private readonly TaskRankService $ranks, private readonly TaskWatcherNotificationService $notifications) {}

    public function createForProjectId(User $actor, int $projectId, CreateTaskData $data): Task
    {
        return $this->create($actor, $this->projectQueries->findVisibleOrFail($actor, $projectId), $data);
    }

    public function create(User $actor, Project $project, CreateTaskData $data): Task
    {
        return DB::transaction(function () use ($actor, $project, $data): Task {
            if ($project->status !== ProjectStatus::Active) {
                throw new ProjectReadOnly('Tasks can only be created in active projects.');
            }
            if (! $actor->isActive()) {
                throw new TaskMutationNotAllowed('Suspended users cannot create tasks.');
            }
            if (! $this->members->canManage($project, $actor) && ! $this->members->isMember($project, $actor)) {
                throw new TaskMutationNotAllowed('The actor cannot create tasks in this project.');
            }
            if ($data->assigneeId) {
                $assignee = $this->users->find($data->assigneeId);
                if ($assignee === null) {
                    throw new InvalidAssignee('The assignee must be an active project member.');
                }
                if (! $assignee->isActive() || ! $this->members->isMember($project, $assignee)) {
                    throw new InvalidAssignee('The assignee must be an active project member.');
                }
                if (! $this->members->canManage($project, $actor) && $assignee->id !== $actor->id) {
                    throw new InvalidAssignee('Project members can only assign themselves.');
                }
            }

            $parent = $this->validateParent($project, $data->type, $data->parentId);
            $allocatedIssue = $this->projects->allocateIssueNumber($project);
            $task = new Task([
                'number' => $allocatedIssue->displayKey,
                'issue_number' => $allocatedIssue->issueNumber,
                'version' => 1,
                'project_id' => $project->id, 'creator_id' => $actor->id, 'assignee_id' => $data->assigneeId, 'type' => $data->type, 'parent_id' => $parent?->id,
                'title' => $data->title, 'description' => $data->description, 'status' => TaskStatus::Backlog,
                'priority' => $data->priority, 'due_at' => $data->dueAt,
            ]);
            $task = $this->ranks->placeAtEnd($task);
            if ($data->labelIds !== []) {
                $task = $this->labels->syncWithinTransaction($task, $data->labelIds, $actor);
            }
            $this->watchers->ensureWatching($task, $actor);
            if ($data->assigneeId && $data->assigneeId !== $actor->id) {
                $this->watchers->ensureWatching($task, $assignee);
            }
            $this->activity->record(ActivityEvent::TaskCreated, $actor, $task, [
                'project_id' => $project->id,
                'task_id' => $task->id,
                'task_number' => $task->number,
                'task_title' => $task->title,
                'status' => $task->status->value,
                'rank' => $task->rank,
                'version' => $task->version,
            ]);
            if ($data->assigneeId && $data->assigneeId !== $actor->id) {
                $this->notifications->notify($task, $actor, ActivityEvent::TaskAssigned);
            }

            return $this->tasks->prepareForResource($task);
        });
    }

    public function update(Task $task, UpdateTaskData $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor): Task {
            $task = $this->tasks->withProject($task);
            $this->ensureUpdateAllowed($task, $actor);
            $type = $data->type ?? $task->type;
            $parentId = $data->parentProvided ? $data->parentId : $task->parent_id;
            if ($type === TaskType::Subtask && $this->tasks->hasSubtasks($task)) {
                throw new ParentTaskInvalid('A task with subtasks cannot become a subtask.');
            }
            $parent = $this->validateParent($task->project, $type, $parentId, $task);
            $task->fill(['title' => $data->title, 'description' => $data->description, 'priority' => $data->priority, 'due_at' => $data->dueAt, 'type' => $type, 'parent_id' => $parent?->id]);
            $changed = array_keys($task->getDirty());
            $old = $this->safeChangedValues($task, $changed, true);
            $new = $this->safeChangedValues($task, $changed, false);
            if ($changed !== []) {
                $task->version++;
                $task = $this->tasks->save($task);
                $this->activity->record(ActivityEvent::TaskUpdated, $actor, $task, ['project_id' => $task->project_id, 'task_id' => $task->id, 'changed' => $changed, 'old' => $old, 'new' => $new]);
            }

            if ($changed === []) {
                $task = $this->tasks->save($task);
            }
            if ($data->labelsProvided) {
                $task = $this->labels->syncWithinTransaction($task, $data->labelIds, $actor);
            }

            return $this->tasks->prepareForResource($task);
        });
    }

    /** @param list<int> $labelIds */
    public function syncLabels(Task $task, array $labelIds, User $actor): Task
    {
        $task = $this->tasks->withProject($task);
        $this->ensureUpdateAllowed($task, $actor);

        return $this->labels->sync($task, $labelIds, $actor);
    }

    public function delete(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor): void {
            $task = $this->tasks->withProject($task);
            if ($task->project->status !== ProjectStatus::Active
                || ! $actor->isActive()
                || ! $this->members->canManage($task->project, $actor)) {
                throw new TaskMutationNotAllowed('The actor cannot delete this task.');
            }
            $this->tasks->delete($task);
            $this->activity->record(ActivityEvent::TaskDeleted, $actor, $task, ['project_id' => $task->project_id, 'task_id' => $task->id, 'task_number' => $task->number, 'task_title' => $task->title]);
        });
    }

    private function ensureUpdateAllowed(Task $task, User $actor): void
    {
        if ($task->project->status !== ProjectStatus::Active) {
            throw new ProjectReadOnly('Tasks can only be changed in active projects.');
        }
        if (! $actor->isActive()) {
            throw new TaskMutationNotAllowed('Suspended users cannot change tasks.');
        }

        if ($this->members->canManage($task->project, $actor)) {
            return;
        }

        if ($this->members->isMember($task->project, $actor)
            && $task->creator_id === $actor->id
            && in_array($task->status, [TaskStatus::Backlog, TaskStatus::Todo], true)) {
            return;
        }

        throw new TaskMutationNotAllowed('The actor cannot update this task.');
    }

    /** @return array<string, mixed> */
    private function safeChangedValues(Task $task, array $changed, bool $old): array
    {
        $values = [];
        foreach ($changed as $attribute) {
            if ($attribute === 'description') {
                $values['description_changed'] = true;

                continue;
            }

            $value = $old ? $task->getOriginal($attribute) : $task->getAttribute($attribute);
            $values[$attribute] = $this->activityValue($value);
        }

        return $values;
    }

    private function activityValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    private function validateParent(Project $project, TaskType $type, ?int $parentId, ?Task $task = null): ?Task
    {
        if ($type !== TaskType::Subtask) {
            if ($parentId !== null) {
                throw new ParentTaskInvalid('Only subtasks may have a parent.');
            }

            return null;
        }

        if ($parentId === null) {
            throw new ParentTaskInvalid('A subtask requires a parent task.');
        }

        $parent = $this->tasks->findForProject($project, $parentId);
        if ($parent === null || $parent->id === $task?->id) {
            throw new ParentTaskInvalid('The subtask parent must belong to the same project.');
        }
        if ($parent->type === TaskType::Subtask) {
            throw new ParentTaskInvalid('A subtask cannot have another subtask as parent.');
        }

        return $parent;
    }
}
