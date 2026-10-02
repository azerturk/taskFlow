<?php

namespace Modules\Tasks\Data;

use DateTimeImmutable;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskType;

final readonly class UpdateTaskData
{
    public function __construct(public string $title, public ?string $description, public TaskPriority $priority, public ?DateTimeImmutable $dueAt, public ?TaskType $type = null, public ?int $parentId = null, public bool $parentProvided = false, public array $labelIds = [], public bool $labelsProvided = false) {}

    public static function fromArray(array $data): self
    {
        return new self($data['title'], $data['description'] ?? null, TaskPriority::from($data['priority']), filled($data['due_at'] ?? null) ? new DateTimeImmutable($data['due_at']) : null, isset($data['type']) ? TaskType::from($data['type']) : null, $data['parent_id'] ?? null, array_key_exists('parent_id', $data), $data['label_ids'] ?? [], array_key_exists('label_ids', $data));
    }
}
