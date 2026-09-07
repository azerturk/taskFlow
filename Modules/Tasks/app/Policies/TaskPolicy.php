<?php

namespace Modules\Tasks\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Models\TaskComment;

class TaskPolicy
{
    public function __construct(private readonly ProjectMemberService $members) {}

    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && $user->hasPermissionTo(PermissionName::TasksView->value);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->isActive()
            && $user->hasPermissionTo(PermissionName::TasksCreate->value)
            && $project->status === ProjectStatus::Active
            && ($this->members->canManage($project, $user) || $this->members->isMember($project, $user));
    }

    public function createAny(User $user): bool
    {
        return $user->isActive()
            && $user->hasPermissionTo(PermissionName::TasksCreate->value);
    }

    public function view(User $user, Task $task): bool
    {
        return $user->isActive()
            && $user->hasPermissionTo(PermissionName::TasksView->value)
            && ($this->members->canManage($task->project, $user) || $this->members->isMember($task->project, $user));
    }

    public function update(User $user, Task $task): bool
    {
        if ($task->project->status !== ProjectStatus::Active
            || ! $user->hasPermissionTo(PermissionName::TasksUpdate->value)
            || ! $this->view($user, $task)) {
            return false;
        }

        return $this->members->canManage($task->project, $user)
            || ($task->creator_id === $user->id && in_array($task->status, [TaskStatus::Backlog, TaskStatus::Todo], true));
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->isActive()
            && $task->project->status === ProjectStatus::Active
            && $user->hasPermissionTo(PermissionName::TasksDelete->value)
            && $this->view($user, $task)
            && $this->members->canManage($task->project, $user);
    }

    public function assign(User $user, Task $task, User|int|null $assignee = null): bool
    {
        if ($task->project->status !== ProjectStatus::Active || ! $this->view($user, $task)) {
            return false;
        }

        if (! $user->hasPermissionTo(PermissionName::TasksAssign->value)) {
            return false;
        }
        if (is_int($assignee)) {
            return $this->members->canManage($task->project, $user)
                || ($assignee === $user->id && $this->members->isMember($task->project, $user));
        }
        if ($assignee === null) {
            return $this->members->canManage($task->project, $user)
                || $this->members->isMember($task->project, $user);
        }
        if ($assignee !== null
            && (! $assignee->isActive() || ! $this->members->isMember($task->project, $assignee))) {
            return false;
        }

        if ($this->members->canManage($task->project, $user)) {
            return true;
        }

        return $assignee?->id === $user->id && $this->members->isMember($task->project, $user);
    }

    public function changeStatus(User $user, Task $task): bool
    {
        return $user->isActive()
            && $task->project->status === ProjectStatus::Active
            && $user->hasPermissionTo(PermissionName::TasksStatusChange->value)
            && $this->view($user, $task)
            && ($this->members->canManage($task->project, $user) || ($task->assignee_id === $user->id && $this->members->isMember($task->project, $user)));
    }

    public function comment(User $user, Task $task): bool
    {
        return $user->isActive()
            && $task->project->status === ProjectStatus::Active
            && $user->hasPermissionTo(PermissionName::CommentsCreate->value)
            && $this->view($user, $task);
    }

    public function watch(User $user, Task $task, User|int $target): bool
    {
        $targetId = $target instanceof User ? $target->id : $target;

        return $user->isActive()
            && $task->project->status === ProjectStatus::Active
            && $this->view($user, $task)
            && ($user->id === $targetId || $this->members->canManage($task->project, $user));
    }

    public function reorder(User $user, Task $task): bool
    {
        return $user->isActive()
            && $task->project->status === ProjectStatus::Active
            && $user->hasPermissionTo(PermissionName::TasksUpdate->value)
            && $this->view($user, $task)
            && $this->members->canManage($task->project, $user);
    }

    public function deleteComment(User $user, Task $task, TaskComment $comment): bool
    {
        if ($task->project->status !== ProjectStatus::Active || ! $this->view($user, $task)) {
            return false;
        }

        return $user->hasPermissionTo(PermissionName::CommentsDelete->value)
            && ($user->id === $comment->user_id || $this->members->canManage($task->project, $user));
    }

    public function uploadAttachment(User $user, Task $task): bool
    {
        return $user->isActive()
            && $task->project->status === ProjectStatus::Active
            && $user->hasPermissionTo(PermissionName::AttachmentsUpload->value)
            && $this->view($user, $task);
    }

    public function deleteAttachment(User $user, Task $task, TaskAttachment $attachment): bool
    {
        return $user->isActive()
            && $task->project->status === ProjectStatus::Active
            && $user->hasPermissionTo(PermissionName::AttachmentsDelete->value)
            && $this->view($user, $task)
            && $attachment->task_id === $task->id
            && ($attachment->media?->uploaded_by === $user->id
                || $this->members->canManage($task->project, $user));
    }
}
