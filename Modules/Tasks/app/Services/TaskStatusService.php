<?php

namespace Modules\Tasks\Services;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\ChangeTaskStatusData;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Exceptions\InvalidTaskStatusTransition;
use Modules\Tasks\Exceptions\TaskVersionConflict;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Tasks\Support\TaskStatusTimestamps;
use Modules\Tasks\Support\TaskTransitionRules;

class TaskStatusService
{
    public function __construct(private readonly TaskRepositoryInterface $tasks, private readonly ProjectMemberService $members, private readonly ActivityRecorder $activity, private readonly TaskWatcherNotificationService $notifications, private readonly TaskRankService $ranks) {}

    /** Return a task with the context needed by the status use case. */
    public function current(int $taskId): Task
    {
        return $this->tasks->findOrFail($taskId);
    }

    /** @return list<TaskStatus> */
    public function availableStatuses(Task $task, User $actor): array
    {
        $canManage = $this->members->canManage($task->project, $actor);
        $isMember = $canManage || $this->members->isMember($task->project, $actor);

        return $this->availableStatusesWithAuthority($task, $actor, $canManage, $isMember);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<int, list<TaskStatus>>
     */
    public function availableStatusesForBoard(Collection $tasks, Project $project, User $actor): array
    {
        $canManage = $this->members->canManage($project, $actor);
        $isMember = $canManage || $this->members->isMember($project, $actor);

        return $tasks->mapWithKeys(fn (Task $task): array => [
            $task->id => $this->availableStatusesWithAuthority($task, $actor, $canManage, $isMember),
        ])->all();
    }

    /** @return list<TaskStatus> */
    private function availableStatusesWithAuthority(Task $task, User $actor, bool $canManage, bool $isMember): array
    {
        return TaskTransitionRules::available(
            $task->status,
            $task->project->status,
            $actor->isActive(),
            $actor->hasPermissionTo(PermissionName::TasksStatusChange->value),
            $canManage,
            $isMember,
            $task->assignee_id === $actor->id,
        );
    }

    public function change(Task $task, ChangeTaskStatusData $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor): Task {
            $task = $this->tasks->lockForRankMutation($task);
            if ($task->version !== $data->expectedVersion) {
                throw new TaskVersionConflict('This task was changed by another request.');
            }
            if ($data->status === TaskStatus::Done && $this->tasks->hasOpenSubtasks($task)) {
                throw new InvalidTaskStatusTransition('A task with open subtasks cannot be completed.');
            }
            if (! in_array($data->status, $this->availableStatuses($task, $actor), true)) {
                throw new InvalidTaskStatusTransition('This task status transition is not allowed.');
            }

            $old = ['status' => $task->status->value, 'rank' => $task->rank, 'version' => $task->version];
            $timestamps = TaskStatusTimestamps::resolve(
                $task->status,
                $data->status,
                $task->started_at,
                $task->completed_at,
                now(),
            );
            $task->started_at = $timestamps['started_at'];
            $task->completed_at = $timestamps['completed_at'];
            $task->status = $data->status;
            $task->version++;
            $task = $this->ranks->placeAtEnd($task);
            $this->activity->record(ActivityEvent::TaskStatusChanged, $actor, $task, [
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'old' => $old,
                'new' => ['status' => $task->status->value, 'rank' => $task->rank, 'version' => $task->version],
            ]);
            $this->notifications->notify($task, $actor, ActivityEvent::TaskStatusChanged);

            return $this->tasks->prepareForResource($task);
        });
    }
}
