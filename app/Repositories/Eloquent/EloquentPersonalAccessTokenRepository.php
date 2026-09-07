<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\PersonalAccessTokenRepositoryInterface;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class EloquentPersonalAccessTokenRepository implements PersonalAccessTokenRepositoryInterface
{
    public function issue(User $user, string $deviceName, array $abilities): NewAccessToken
    {
        return $user->createToken($deviceName, $abilities);
    }

    public function revokeForUserByPlainText(User $user, ?string $plainTextToken): bool
    {
        $token = $plainTextToken ? PersonalAccessToken::findToken($plainTextToken) : null;

        if (! $token || $token->tokenable_type !== $user::class || $token->tokenable_id !== $user->id) {
            return false;
        }

        return (bool) $token->delete();
    }

    public function revokeAllFor(User $user): int
    {
        return $user->tokens()->delete();
    }

    public function currentAbilities(User $user): array
    {
        return array_values($user->currentAccessToken()?->abilities ?? []);
    }
}
