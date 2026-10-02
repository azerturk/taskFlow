<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;

class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function paginateFor(User $user, int $perPage): LengthAwarePaginator
    {
        return $user->notifications()->latest()->paginate($perPage)->withQueryString();
    }

    public function markReadFor(User $user, DatabaseNotification $notification): bool
    {
        if ($notification->notifiable_id !== $user->id || $notification->notifiable_type !== $user::class) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    public function markAllReadFor(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function send(User $user, Notification $notification): void
    {
        $user->notify($notification);
    }
}
