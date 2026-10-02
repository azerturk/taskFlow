<?php

namespace App\Repositories\Contracts;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function find(int $id): ?User;

    public function findOrFail(int $id): User;

    public function findByEmail(string $email): ?User;

    public function paginateForAdministration(?string $search, ?string $role, int $perPage): LengthAwarePaginator;

    /** @param array{name: string, email: string, password: string, status: string} $attributes */
    public function create(array $attributes, UserRole $role): User;

    /** @param array{name: string, email: string} $attributes */
    public function updateIdentityAndRole(User $user, array $attributes, UserRole $role): User;

    public function updatePassword(User $user, string $passwordHash, string $rememberToken): void;

    public function setStatus(User $user, AccountStatus $status): User;

    public function roleName(User $user): ?string;

    public function activeAdministratorCountForUpdate(): int;

    public function prepareForAdministration(User $user): User;

    public function prepareForAuthentication(User $user): User;
}
