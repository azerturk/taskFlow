<?php

namespace Modules\Tasks\Enums;

enum TaskStatus: string
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';
    case Cancelled = 'cancelled';

    /** @return list<TaskStatus> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Backlog => [self::Todo, self::Cancelled],
            self::Todo => [self::Backlog, self::InProgress, self::Cancelled],
            self::InProgress => [self::Todo, self::Review, self::Cancelled],
            self::Review => [self::InProgress, self::Done, self::Cancelled],
            self::Done => [self::InProgress],
            self::Cancelled => [self::Backlog],
        };
    }
}
