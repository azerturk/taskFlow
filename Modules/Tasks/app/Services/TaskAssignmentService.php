<?php

namespace Modules\Tasks\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Exceptions\ProjectReadOnly;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\AssignTaskData;
use Modules\Tasks\Exceptions\InvalidAssignee;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskWatcherRepositoryInterface;

class TaskAssignmentService
{
    public function __construct(private readonly TaskRepositoryInterface $tasks, private readonly ProjectMemberService $members, private readonly ActivityRecorder $activity, private readonly TaskWatcherRepositoryInterface $watchers, private readonly TaskWatcherNotificationService $notifications, private readonly UserRepositoryInterface $users) {}

    public function assign(Task $task, AssignTaskData|User|null $assignment, User $actor): Task
    {
        return DB::transaction(function () use ($task, $assignment, $actor): Task {
            $task = $this->tasks->withProject($task);
            $assignee = $assignment instanceof AssignTaskData
                ? ($assignment->assigneeId === null ? null : $this->users->find($assignment->assigneeId))
                : $assignment;
            if ($assignment instanceof AssignTaskData && $assignment->assigneeId !== null && $assignee === null) {
                throw new InvalidAssignee('The assignee must be an active project member.');
            }
            if ($task->project->status !== ProjectStatus::Active) {
                throw new ProjectReadOnly('Tasks can only be assigned in active projects.');
            }
            if (! $actor->isActive()) {
                throw new InvalidAssignee('The actor cannot change task assignment.');
            }
            if (! $this->members->canManage($task->project, $actor)
                && ($assignee?->id !== $actor->id || ! $this->members->isMember($task->project, $actor))) {
                throw new InvalidAssignee('Project members can only assign themselves.');
            }
            if ($assignee && (! $assignee->isActive() || ! $this->members->isMember($task->project, $assignee))) {
                throw new InvalidAssignee('The assignee must be an active project member.');
            }
            $oldAssignee = $task->assignee;
            if ($oldAssignee?->id === $assignee?->id) {
                return $task;
            }
            $task->assignee_id = $assignee?->id;
            $task->version++;
            $task = $this->tasks->save($task);
            if ($assignee !== null) {
                $this->watchers->ensureWatching($task, $assignee);
            }
            $this->activity->record(ActivityEvent::TaskAssigned, $actor, $task, [
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'old_assignee_id' => $oldAssignee?->id,
                'old_assignee_name' => $oldAssignee?->name ?: $oldAssignee?->email,
                'new_assignee_id' => $assignee?->id,
                'new_assignee_name' => $assignee?->name ?: $assignee?->email,
                'old' => ['assignee_id' => $oldAssignee?->id, 'assignee_name' => $oldAssignee?->name ?: $oldAssignee?->email],
                'new' => ['assignee_id' => $assignee?->id, 'assignee_name' => $assignee?->name ?: $assignee?->email],
            ]);
            $this->notifications->notify($task, $actor, ActivityEvent::TaskAssigned);

            return $this->tasks->prepareForResource($task);
        });
    }
}
