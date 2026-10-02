<?php

namespace App\Data;

final readonly class AuthenticateSessionData
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            strtolower(trim((string) $data['email'])),
            (string) $data['password'],
            (bool) ($data['remember'] ?? false),
        );
    }
}
