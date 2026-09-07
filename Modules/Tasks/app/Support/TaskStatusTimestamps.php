<?php

namespace Modules\Tasks\Support;

use DateTimeInterface;
use Modules\Tasks\Enums\TaskStatus;

final class TaskStatusTimestamps
{
    /** @return array{started_at: ?DateTimeInterface, completed_at: ?DateTimeInterface} */
    public static function resolve(
        TaskStatus $current,
        TaskStatus $target,
        ?DateTimeInterface $startedAt,
        ?DateTimeInterface $completedAt,
        DateTimeInterface $at,
    ): array {
        return [
            'started_at' => $target === TaskStatus::InProgress && $startedAt === null ? $at : $startedAt,
            'completed_at' => match (true) {
                $target === TaskStatus::Done => $at,
                $current === TaskStatus::Done => null,
                default => $completedAt,
            },
        ];
    }
}
