<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

interface PersonalAccessTokenRepositoryInterface
{
    /** @param list<string> $abilities */
    public function issue(User $user, string $deviceName, array $abilities): NewAccessToken;

    public function revokeForUserByPlainText(User $user, ?string $plainTextToken): bool;

    public function revokeAllFor(User $user): int;

    /** @return list<string> */
    public function currentAbilities(User $user): array;
}
