<?php

namespace Modules\Tasks\Enums;

enum TaskType: string
{
    case Task = 'task';
    case Bug = 'bug';
    case Story = 'story';
    case Subtask = 'subtask';

    public function requiresParent(): bool
    {
        return $this === self::Subtask;
    }

    public function canBeParent(): bool
    {
        return $this !== self::Subtask;
    }
}
