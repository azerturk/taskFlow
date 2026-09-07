<?php

namespace Modules\Tasks\Data;

use Carbon\CarbonImmutable;

final readonly class TaskFiltersData
{
    public function __construct(
        public ?string $search,
        public array $statuses,
        public array $priorities,
        public ?int $projectId,
        public ?int $assigneeId,
        public ?int $reporterId,
        public array $labelIds,
        public ?int $parentId,
        public ?CarbonImmutable $dueBefore,
        public ?CarbonImmutable $dueAfter,
        public bool $overdue,
        public string $sort,
        public array $types = [],
        public bool $unassigned = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['search']) && $data['search'] !== '' ? (string) $data['search'] : null,
            array_values($data['statuses'] ?? []),
            array_values($data['priorities'] ?? []),
            isset($data['project_id']) ? (int) $data['project_id'] : null,
            array_key_exists('assignee_id', $data) && $data['assignee_id'] !== 'unassigned' ? (int) $data['assignee_id'] : null,
            isset($data['reporter_id']) ? (int) $data['reporter_id'] : null,
            array_map('intval', $data['label_ids'] ?? []),
            isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            self::date($data['due_before'] ?? null),
            self::date($data['due_after'] ?? null),
            (bool) ($data['overdue'] ?? false),
            $data['sort'] ?? '-created_at',
            array_values($data['types'] ?? []),
            ($data['assignee_id'] ?? null) === 'unassigned',
        );
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        return $value === null || $value === ''
            ? null
            : CarbonImmutable::parse((string) $value)->startOfDay();
    }
}
