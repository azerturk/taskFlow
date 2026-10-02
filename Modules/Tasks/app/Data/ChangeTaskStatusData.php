<?php

namespace Modules\Tasks\Data;

use Modules\Tasks\Enums\TaskStatus;

final readonly class ChangeTaskStatusData
{
    public function __construct(public TaskStatus $status, public int $expectedVersion) {}

    public static function fromArray(array $data): self
    {
        return new self(TaskStatus::from($data['status']), (int) $data['expected_version']);
    }
}
