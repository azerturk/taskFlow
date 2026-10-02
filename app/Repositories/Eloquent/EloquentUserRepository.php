<?php

namespace App\Repositories\Eloquent;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function findOrFail(int $id): User
    {
        return User::query()->findOrFail($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function paginateForAdministration(?string $search, ?string $role, int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->withCount('projectMemberships')
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(UserRole::tryFrom((string) $role), fn ($query, UserRole $userRole) => $query->role($userRole->value))
            ->orderBy('name')
            ->orderBy('email')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes, UserRole $role): User
    {
        $user = new User([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
        ]);
        $user->forceFill(['status' => $attributes['status']])->save();
        $user->syncRoles([$role->value]);

        return $this->prepareForAdministration($user);
    }

    public function updateIdentityAndRole(User $user, array $attributes, UserRole $role): User
    {
        $user->fill($attributes)->save();
        $user->syncRoles([$role->value]);

        return $this->prepareForAdministration($user);
    }

    public function updatePassword(User $user, string $passwordHash, string $rememberToken): void
    {
        $user->forceFill(['password' => $passwordHash, 'remember_token' => $rememberToken])->save();
    }

    public function setStatus(User $user, AccountStatus $status): User
    {
        $user->forceFill(['status' => $status])->save();

        return $this->prepareForAdministration($user);
    }

    public function roleName(User $user): ?string
    {
        return $this->prepareForAdministration($user)->roles->first()?->name;
    }

    public function activeAdministratorCountForUpdate(): int
    {
        return User::role(UserRole::Admin->value)
            ->where('status', AccountStatus::Active->value)
            ->lockForUpdate()
            ->count();
    }

    public function prepareForAdministration(User $user): User
    {
        return User::query()->with('roles')->whereKey($user->id)->firstOrFail();
    }

    public function prepareForAuthentication(User $user): User
    {
        return $this->prepareForAdministration($user);
    }
}
