<?php

namespace Modules\Tasks\Data;

final readonly class ReorderTaskData
{
    public function __construct(public ?int $beforeTaskId, public ?int $afterTaskId, public int $expectedVersion) {}

    public static function fromArray(array $data): self
    {
        return new self($data['before_task_id'] ?? null, $data['after_task_id'] ?? null, $data['expected_version']);
    }
}
