<?php

namespace Modules\Projects\Data;

final readonly class ProjectFiltersData
{
    public function __construct(public ?string $search, public ?string $status) {}

    public static function fromArray(array $data): self
    {
        return new self($data['q'] ?? $data['search'] ?? null, $data['status'] ?? null);
    }
}
