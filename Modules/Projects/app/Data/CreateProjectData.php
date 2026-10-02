<?php

namespace Modules\Projects\Data;

use DateTimeImmutable;

final readonly class CreateProjectData
{
    public function __construct(public string $name, public string $key, public ?string $description, public ?DateTimeImmutable $startsAt, public ?DateTimeImmutable $dueAt) {}

    public static function fromArray(array $data): self
    {
        return new self($data['name'], $data['key'], $data['description'] ?? null, filled($data['starts_at'] ?? null) ? new DateTimeImmutable($data['starts_at']) : null, filled($data['due_at'] ?? null) ? new DateTimeImmutable($data['due_at']) : null);
    }
}
