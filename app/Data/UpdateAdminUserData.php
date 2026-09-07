<?php

namespace App\Data;

use App\Enums\UserRole;

final readonly class UpdateAdminUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public UserRole $role,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self($data['name'], $data['email'], UserRole::from($data['role']));
    }
}
