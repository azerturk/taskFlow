<?php

namespace App\Data;

use App\Enums\ApiTokenAbility;
use Illuminate\Support\Str;

final readonly class CreatePersonalAccessTokenData
{
    /** @param list<ApiTokenAbility> $abilities */
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName,
        public array $abilities,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            Str::lower(trim($data['email'])),
            $data['password'],
            trim($data['device_name']),
            array_map(ApiTokenAbility::from(...), $data['abilities']),
        );
    }

    /** @return list<string> */
    public function abilityValues(): array
    {
        return array_map(static fn (ApiTokenAbility $ability): string => $ability->value, $this->abilities);
    }
}
