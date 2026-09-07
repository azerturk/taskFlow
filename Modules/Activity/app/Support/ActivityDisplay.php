<?php

namespace Modules\Activity\Support;

use Spatie\Activitylog\Models\Activity;

final class ActivityDisplay
{
    public static function label(Activity $activity): string
    {
        return match ($activity->event) {
            'project.created' => 'Project created',
            'project.updated' => 'Project updated',
            'project.activated' => 'Project activated',
            'project.archived' => 'Project archived',
            'project.status_changed' => 'Project status changed',
            'project.member_added' => 'Member added',
            'project.member_removed' => 'Member removed',
            'task.created' => 'Task created',
            'task.updated' => 'Task updated',
            'task.assigned' => 'Task assignment changed',
            'task.status_changed' => 'Task status changed',
            'task.deleted' => 'Task deleted',
            'comment.created' => 'Comment added',
            'comment.deleted' => 'Comment deleted',
            'attachment.uploaded' => 'Attachment uploaded',
            'attachment.deleted' => 'Attachment deleted',
            'task.reordered' => 'Task reordered', 'task.labels_updated' => 'Task labels updated',
            'label.created' => 'Label created', 'label.updated' => 'Label updated', 'label.deleted' => 'Label deleted',
            'watcher.added' => 'Watcher added', 'watcher.removed' => 'Watcher removed',
            'user.created' => 'User created', 'user.updated' => 'User updated', 'user.suspended' => 'User suspended', 'user.reactivated' => 'User reactivated', 'user.password_reset' => 'Password reset', 'user.password_changed' => 'Password changed',
            'api_token.issued' => 'API token issued', 'api_token.revoked' => 'API token revoked',
            default => 'Activity recorded',
        };
    }

    public static function summary(Activity $activity): ?string
    {
        $properties = $activity->properties->toArray();

        return match ($activity->event) {
            'project.created' => $properties['project_name'] ?? null,
            'task.created' => isset($properties['task_number'], $properties['task_title'])
                ? $properties['task_number'].' — '.$properties['task_title']
                : ($properties['task_title'] ?? null),
            'project.updated', 'task.updated' => ! empty($properties['changed'])
                ? 'Changed: '.implode(', ', array_map(static fn (string $field) => str_replace('_', ' ', $field), $properties['changed']))
                : null,
            'project.status_changed' => isset($properties['old_status'], $properties['new_status'])
                ? self::status($properties['old_status']).' → '.self::status($properties['new_status'])
                : null,
            'project.member_added' => isset($properties['member_name']) ? 'Added '.$properties['member_name'] : null,
            'project.member_removed' => isset($properties['member_name']) ? 'Removed '.$properties['member_name'] : null,
            'project.member_role_updated' => isset($properties['member_name'], $properties['new_member_role']) ? $properties['member_name'].' is now '.$properties['new_member_role'] : null,
            'task.assigned' => self::assignmentSummary($properties),
            'task.status_changed' => self::statusTransitionSummary($properties),
            'attachment.uploaded', 'attachment.deleted' => $properties['filename'] ?? null,
            'label.created', 'label.updated', 'label.deleted' => $properties['label_name'] ?? null,
            'task.reordered' => isset($properties['new']['rank'])
                ? 'Position '.$properties['new']['rank']
                : (isset($properties['rank']) ? 'Position '.$properties['rank'] : null),
            'watcher.added' => isset($properties['watcher_id']) ? 'Watcher #'.$properties['watcher_id'].' added' : null,
            'watcher.removed' => isset($properties['watcher_id']) ? 'Watcher #'.$properties['watcher_id'].' removed' : null,
            'user.suspended' => isset($properties['unassigned_task_count'])
                ? $properties['unassigned_task_count'].' open assignment(s) cleared'
                : null,
            default => null,
        };
    }

    private static function assignmentSummary(array $properties): string
    {
        $old = $properties['old_assignee_name'] ?? 'Unassigned';
        $new = $properties['new_assignee_name'] ?? 'Unassigned';

        return $old.' → '.$new;
    }

    private static function status(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    private static function statusTransitionSummary(array $properties): ?string
    {
        $old = is_array($properties['old'] ?? null) ? ($properties['old']['status'] ?? null) : ($properties['old'] ?? null);
        $new = is_array($properties['new'] ?? null) ? ($properties['new']['status'] ?? null) : ($properties['new'] ?? null);

        return is_string($old) && is_string($new) ? self::status($old).' → '.self::status($new) : null;
    }
}
