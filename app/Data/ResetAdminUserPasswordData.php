<?php

namespace App\Data;

final readonly class ResetAdminUserPasswordData
{
    public function __construct(public string $password) {}

    public static function fromArray(array $data): self
    {
        return new self($data['password']);
    }
}
