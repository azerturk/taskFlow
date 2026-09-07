<?php

namespace Modules\Tasks\Repositories\Eloquent;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskWatcherRepositoryInterface;

class EloquentTaskWatcherRepository implements TaskWatcherRepositoryInterface
{
    public function forTask(Task $task): Collection
    {
        return $task->watchers()->orderBy('name')->get(['users.id', 'users.name']);
    }

    public function ensureWatching(Task $task, User $user): void
    {
        $task->watchers()->syncWithoutDetaching([$user->id]);
    }

    public function removeWatching(Task $task, User $user): void
    {
        $task->watchers()->detach($user->id);
    }

    public function removeForProject(Project $project, User $user): int
    {
        return DB::table('task_watchers')->where('user_id', $user->id)->whereIn('task_id', Task::query()->where('project_id', $project->id)->select('id'))->delete();
    }

    public function removeForUser(User $user): int
    {
        return DB::table('task_watchers')->where('user_id', $user->id)->delete();
    }

    public function isWatching(Task $task, User $user): bool
    {
        return $task->watchers()->whereKey($user->id)->exists();
    }

    public function eligibleWatchers(Task $task): Collection
    {
        return User::query()->where('status', AccountStatus::Active->value)->whereIn('id', $task->watchers()->select('users.id'))->where(function ($query) use ($task): void {
            $query->whereKey($task->project->owner_id)->orWhereHas('projectMemberships', fn ($memberships) => $memberships->where('project_id', $task->project_id));
        })->get();
    }
}
