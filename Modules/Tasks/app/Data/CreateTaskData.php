<?php

namespace Modules\Tasks\Data;

use DateTimeImmutable;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskType;

final readonly class CreateTaskData
{
    public function __construct(public string $title, public ?string $description, public ?int $assigneeId, public TaskPriority $priority, public ?DateTimeImmutable $dueAt, public TaskType $type = TaskType::Task, public ?int $parentId = null, public array $labelIds = []) {}

    public static function fromArray(array $data): self
    {
        return new self($data['title'], $data['description'] ?? null, $data['assignee_id'] ?? null, TaskPriority::from($data['priority']), filled($data['due_at'] ?? null) ? new DateTimeImmutable($data['due_at']) : null, TaskType::from($data['type'] ?? TaskType::Task->value), $data['parent_id'] ?? null, $data['label_ids'] ?? []);
    }
}
