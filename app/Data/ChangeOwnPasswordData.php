<?php

namespace App\Data;

final readonly class ChangeOwnPasswordData
{
    public function __construct(public string $currentPassword, public string $password) {}

    public static function fromArray(array $data): self
    {
        return new self($data['current_password'], $data['password']);
    }
}
