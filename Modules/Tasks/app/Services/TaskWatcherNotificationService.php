<?php

namespace Modules\Tasks\Services;

use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskWatcherNotification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Support\Collection;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskWatcherRepositoryInterface;

class TaskWatcherNotificationService
{
    public function __construct(private readonly TaskWatcherRepositoryInterface $watchers, private readonly NotificationRepositoryInterface $notifications) {}

    /** @return Collection<int, User> */
    public function recipients(Task $task, User $actor): Collection
    {
        return $this->watchers->eligibleWatchers($task)
            ->reject(fn (User $watcher): bool => $watcher->id === $actor->id)
            ->unique(fn (User $watcher): int|string => $watcher->getKey())
            ->values();
    }

    public function notify(Task $task, User $actor, ActivityEvent $event): void
    {
        $this->recipients($task, $actor)->each(function (User $watcher) use ($task, $actor, $event): void {
            $notification = $event === ActivityEvent::TaskAssigned
                ? new TaskAssignedNotification($task, $actor)
                : new TaskWatcherNotification($task, $actor, $event->value);
            $this->notifications->send($watcher, $notification);
        });
    }
}
