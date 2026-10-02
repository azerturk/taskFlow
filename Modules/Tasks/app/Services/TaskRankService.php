<?php

namespace Modules\Tasks\Services;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\ReorderTaskData;
use Modules\Tasks\Exceptions\TaskMutationNotAllowed;
use Modules\Tasks\Exceptions\TaskVersionConflict;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;

class TaskRankService
{
    public function __construct(
        private readonly ProjectMemberService $members,
        private readonly ActivityRecorder $activity,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    /** Transaction-neutral helper for a mutation service that already owns the transaction. */
    public function placeAtEnd(Task $task): Task
    {
        return $this->tasks->appendToStatusColumn($task);
    }

    public function canReorder(Project $project, User $actor): bool
    {
        return $project->status === ProjectStatus::Active
            && $actor->isActive()
            && $actor->hasPermissionTo(PermissionName::TasksUpdate->value)
            && $this->members->canManage($project, $actor);
    }

    public function reorder(Task $task, ReorderTaskData $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor): Task {
            $task = $this->tasks->lockForRankMutation($task);

            if (! $this->canReorder($task->project, $actor)) {
                throw new TaskMutationNotAllowed('Only project managers can reorder tasks.');
            }
            if ($task->version !== $data->expectedVersion) {
                throw new TaskVersionConflict('This task was changed by another request.');
            }

            $old = ['rank' => $task->rank, 'version' => $task->version];
            $changed = $this->tasks->reorderWithinStatusColumn($task, $data->beforeTaskId, $data->afterTaskId);
            if (! $changed) {
                return $this->tasks->prepareForResource($task);
            }

            $task = $this->tasks->prepareForResource($task);
            $this->activity->record(ActivityEvent::TaskReordered, $actor, $task, [
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'old' => $old,
                'new' => ['rank' => $task->rank, 'version' => $task->version],
            ]);

            return $task;
        });
    }
}
