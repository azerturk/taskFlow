<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\DatabaseNotification;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;

class NotificationCenterService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    public function unreadCount(User $user): int
    {
        return $this->notifications->unreadCount($user);
    }

    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $page = $this->notifications->paginateFor($user, $perPage);
        $taskIds = $page->getCollection()
            ->map(fn (DatabaseNotification $notification): int|false => filter_var($notification->data['task_id'] ?? null, FILTER_VALIDATE_INT))
            ->filter(fn (int|false $id): bool => $id !== false)
            ->map(fn (int $id): int => $id)
            ->unique()
            ->values()
            ->all();
        $tasks = $this->tasks->visibleByIdsFor($user, $taskIds)->keyBy('id');

        return $page->through(fn (DatabaseNotification $notification): array => $this->presentPrepared(
            $notification,
            $tasks->get((int) ($notification->data['task_id'] ?? 0)),
        ));
    }

    /** @return array{notification: DatabaseNotification, summary: string, task_url: ?string} */
    public function present(User $user, DatabaseNotification $notification): array
    {
        $taskId = filter_var($notification->data['task_id'] ?? null, FILTER_VALIDATE_INT);
        $task = $taskId === false ? null : $this->tasks->visibleByIdsFor($user, [(int) $taskId])->first();

        return $this->presentPrepared($notification, $task);
    }

    public function markRead(User $user, DatabaseNotification $notification): void
    {
        if (! $this->notifications->markReadFor($user, $notification)) {
            throw (new ModelNotFoundException)->setModel(DatabaseNotification::class, [$notification->id]);
        }
    }

    public function markAllRead(User $user): void
    {
        $this->notifications->markAllReadFor($user);
    }

    /** @return array{notification: DatabaseNotification, summary: string, task_url: ?string} */
    private function presentPrepared(DatabaseNotification $notification, ?Task $task): array
    {
        if ($task === null) {
            return ['notification' => $notification, 'summary' => 'A task update is no longer available.', 'task_url' => null];
        }

        $event = match ($notification->data['event'] ?? null) {
            'task.assigned' => 'You were assigned a task',
            'task.status_changed' => 'Task status changed',
            'comment.created' => 'New task comment',
            default => 'Task update',
        };

        return [
            'notification' => $notification,
            'summary' => $event.' · '.$task->number.' — '.$task->title,
            'task_url' => route('tasks.show', $task),
        ];
    }
}
