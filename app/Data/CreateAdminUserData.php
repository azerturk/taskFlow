<?php

namespace App\Data;

use App\Enums\UserRole;

final readonly class CreateAdminUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public UserRole $role,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self($data['name'], $data['email'], $data['password'], UserRole::from($data['role']));
    }
}
