<?php

namespace Modules\Tasks\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Exceptions\InvalidWatcher;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskWatcherRepositoryInterface;

class TaskWatcherService
{
    public function __construct(private readonly TaskWatcherRepositoryInterface $watchers, private readonly ProjectMemberService $members, private readonly ActivityRecorder $activity, private readonly TaskRepositoryInterface $tasks, private readonly UserRepositoryInterface $users) {}

    /** @return Collection<int, User> */
    public function watchersFor(Task $task): Collection
    {
        return $this->watchers->forTask($task);
    }

    public function watchById(Task $task, ?int $userId, User $actor): void
    {
        $this->watch($task, $this->resolveTarget($userId, $actor), $actor);
    }

    public function unwatchById(Task $task, int $userId, User $actor): void
    {
        $this->unwatch($task, $this->resolveTarget($userId, $actor), $actor);
    }

    public function watch(Task $task, User $user, User $actor): void
    {
        $this->change($task, $user, $actor, true);
    }

    public function unwatch(Task $task, User $user, User $actor): void
    {
        $this->change($task, $user, $actor, false);
    }

    private function change(Task $task, User $user, User $actor, bool $watch): void
    {
        DB::transaction(function () use ($task, $user, $actor, $watch): void {
            $task = $this->tasks->withProject($task);
            $project = $task->project;
            if ($project->status !== ProjectStatus::Active
                || ! $actor->isActive()
                || ! $user->isActive()
                || ! $this->members->canParticipate($project, $user)
                || (! $this->members->canManage($project, $actor) && ! $this->members->canParticipate($project, $actor))) {
                throw new InvalidWatcher('Only active project members can watch this task.');
            }
            if ($actor->id !== $user->id && ! $this->members->canManage($project, $actor)) {
                throw new InvalidWatcher('Only project managers can manage another user\'s watcher state.');
            }
            $exists = $this->watchers->isWatching($task, $user);
            if ($watch && ! $exists) {
                $this->watchers->ensureWatching($task, $user);
                $this->activity->record(ActivityEvent::WatcherAdded, $actor, $task, ['project_id' => $task->project_id, 'task_id' => $task->id, 'watcher_id' => $user->id]);
            }
            if (! $watch && $exists) {
                $this->watchers->removeWatching($task, $user);
                $this->activity->record(ActivityEvent::WatcherRemoved, $actor, $task, ['project_id' => $task->project_id, 'task_id' => $task->id, 'watcher_id' => $user->id]);
            }
        });
    }

    private function resolveTarget(?int $userId, User $actor): User
    {
        if ($userId === null || $userId === $actor->id) {
            return $actor;
        }

        $target = $this->users->find($userId);
        if ($target === null) {
            throw new InvalidWatcher('The requested watcher is unavailable.');
        }

        return $target;
    }
}
