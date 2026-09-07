<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class WorkspaceHeaderQueryService
{
    /** @var array<int, array{globalRole: ?string, unreadNotificationCount: int}> */
    private array $cache = [];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly NotificationCenterService $notifications,
    ) {}

    /** @return array{globalRole: ?string, unreadNotificationCount: int} */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return ['globalRole' => null, 'unreadNotificationCount' => 0];
        }

        return $this->cache[$user->id] ??= [
            'globalRole' => $this->users->roleName($user),
            'unreadNotificationCount' => $this->notifications->unreadCount($user),
        ];
    }
}
