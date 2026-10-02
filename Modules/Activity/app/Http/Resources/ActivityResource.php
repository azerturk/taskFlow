<?php

namespace Modules\Activity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Modules\Activity\Support\ActivityDisplay;
use Spatie\Activitylog\Models\Activity;

/** @mixin Activity */
class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $properties = $this->properties->toArray();

        return [
            'id' => $this->id,
            'event' => $this->event,
            'display' => ActivityDisplay::label($this->resource),
            'summary' => ActivityDisplay::summary($this->resource),
            'actor' => $this->whenLoaded('causer', fn (): ?array => $this->causer ? [
                'id' => $this->causer->id,
                'name' => $this->causer->name,
            ] : null),
            'subject' => $this->whenLoaded('subject', fn (): ?array => $this->subject ? [
                'id' => $this->subject->getKey(),
                'type' => class_basename($this->subject_type),
            ] : null),
            'project_id' => $properties['project_id'] ?? null,
            'task_id' => $properties['task_id'] ?? null,
            'schema_version' => $properties['schema_version'] ?? null,
            'properties' => Arr::only($properties, [
                'project_id', 'project_name', 'task_id', 'task_number', 'task_title',
                'member_id', 'member_name', 'member_role', 'comment_id', 'attachment_id',
                'filename', 'changed', 'old', 'new', 'old_assignee_id', 'old_assignee_name',
                'new_assignee_id', 'new_assignee_name', 'label_id', 'label_name', 'label_ids',
                'watcher_id', 'rank', 'old_member_role', 'new_member_role', 'old_status', 'new_status',
                'reason', 'unassigned_task_count', 'watcher_subscription_count',
                'revoked_token_count', 'revoked_session_count',
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
