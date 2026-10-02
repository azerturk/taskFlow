<?php

namespace App\Enums;

enum ApiTokenAbility: string
{
    case ProjectsRead = 'projects:read';
    case ProjectsWrite = 'projects:write';
    case TasksRead = 'tasks:read';
    case TasksWrite = 'tasks:write';
    case CommentsWrite = 'comments:write';
    case ActivityRead = 'activity:read';
    case DashboardRead = 'dashboard:read';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $ability): string => $ability->value, self::cases());
    }
}
