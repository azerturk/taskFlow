<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface SessionRepositoryInterface
{
    public function deleteForUser(User $user, ?string $exceptSessionId = null): int;
}
