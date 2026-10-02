<?php

namespace Modules\Activity\Data;

use Carbon\CarbonImmutable;
use Modules\Activity\Enums\ActivityEvent;

final readonly class ActivityFiltersData
{
    public function __construct(
        public ?ActivityEvent $event,
        public ?int $projectId,
        public ?int $taskId,
        public ?int $actorId,
        public ?CarbonImmutable $dateFrom,
        public ?CarbonImmutable $dateTo,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['event']) ? ActivityEvent::from((string) $data['event']) : null,
            isset($data['project_id']) ? (int) $data['project_id'] : null,
            isset($data['task_id']) ? (int) $data['task_id'] : null,
            isset($data['actor_id']) ? (int) $data['actor_id'] : null,
            self::date($data['date_from'] ?? null),
            self::date($data['date_to'] ?? null),
        );
    }

    public function forWeb(): array
    {
        return array_filter([
            'event' => $this->event?->value,
            'project' => $this->projectId,
            'task' => $this->taskId,
            'actor' => $this->actorId,
            'date_from' => $this->dateFrom?->toDateString(),
            'date_to' => $this->dateTo?->toDateString(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        return $value === null || $value === ''
            ? null
            : CarbonImmutable::parse((string) $value)->startOfDay();
    }
}
