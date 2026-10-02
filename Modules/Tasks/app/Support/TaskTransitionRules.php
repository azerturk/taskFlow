<?php

namespace Modules\Tasks\Support;

use Modules\Projects\Enums\ProjectStatus;
use Modules\Tasks\Enums\TaskStatus;

final class TaskTransitionRules
{
    /** @return list<TaskStatus> */
    public static function available(
        TaskStatus $status,
        ProjectStatus $projectStatus,
        bool $actorActive,
        bool $hasPermission,
        bool $canManage,
        bool $isMember,
        bool $isAssignee,
    ): array {
        if ($projectStatus !== ProjectStatus::Active
            || ! $actorActive
            || ! $hasPermission
            || (! $canManage && ! ($isAssignee && $isMember))) {
            return [];
        }

        if (in_array($status, [TaskStatus::Done, TaskStatus::Cancelled], true) && ! $canManage) {
            return [];
        }

        return $status->allowedTransitions();
    }
}
