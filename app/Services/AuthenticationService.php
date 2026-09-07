<?php

namespace App\Services;

use App\Data\AuthenticateSessionData;
use App\Data\CreatePersonalAccessTokenData;
use App\Enums\AccountStatus;
use App\Exceptions\InvalidCredentials;
use App\Models\User;
use App\Repositories\Contracts\PersonalAccessTokenRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use Modules\Activity\Enums\ActivityEvent;

class AuthenticationService
{
    public function __construct(private readonly UserRepositoryInterface $users, private readonly PersonalAccessTokenRepositoryInterface $tokens, private readonly SecurityAuditService $audit) {}

    public function createPersonalAccessToken(CreatePersonalAccessTokenData $data): ?NewAccessToken
    {
        $user = $this->users->findByEmail($data->email);

        if (! $user || ! $user->isActive() || ! Hash::check($data->password, $user->password)) {
            return null;
        }

        $token = $this->tokens->issue($user, $data->deviceName, $data->abilityValues());
        $this->audit->record($user, $user, ActivityEvent::ApiTokenIssued, [
            'user_id' => $user->id,
            'device_name' => $data->deviceName,
            'abilities' => $data->abilityValues(),
        ]);

        return $token;
    }

    public function authenticateSession(AuthenticateSessionData $data, Session $session): User
    {
        if (! Auth::guard('web')->attempt([
            'email' => $data->email,
            'password' => $data->password,
            'status' => AccountStatus::Active->value,
        ], $data->remember)) {
            throw new InvalidCredentials('The credentials are invalid.');
        }

        $user = Auth::guard('web')->user();
        if (! $user instanceof User) {
            Auth::guard('web')->logout();
            throw new InvalidCredentials('The credentials are invalid.');
        }

        $session->regenerate();

        return $user;
    }

    public function logoutSession(Session $session): void
    {
        Auth::guard('web')->logout();
        $session->invalidate();
        $session->regenerateToken();
    }

    public function revokeCurrentToken(User $user, ?string $plainTextToken): void
    {
        if ($this->tokens->revokeForUserByPlainText($user, $plainTextToken)) {
            $this->audit->record($user, $user, ActivityEvent::ApiTokenRevoked, ['user_id' => $user->id]);
        }
    }

    public function authenticatedUser(User $user): User
    {
        $abilities = $this->tokens->currentAbilities($user);
        $user = $this->users->prepareForAuthentication($user);
        $user->setAttribute('access_token_abilities', $abilities);

        return $user;
    }
}
