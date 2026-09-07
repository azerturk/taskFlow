<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;

interface NotificationRepositoryInterface
{
    public function unreadCount(User $user): int;

    public function paginateFor(User $user, int $perPage): LengthAwarePaginator;

    public function markReadFor(User $user, DatabaseNotification $notification): bool;

    public function markAllReadFor(User $user): void;

    public function send(User $user, Notification $notification): void;
}
