<?php

namespace Modules\Tasks\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tasks\Models\Task;

/** @mixin Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'display_key' => $this->display_key,
            'issue_number' => $this->issue_number,
            'version' => $this->version,
            'rank' => $this->rank,
            'type' => $this->type->value,
            'parent' => $this->whenLoaded('parent', fn (): ?array => $this->parent ? $this->summary($this->parent) : null),
            'subtasks' => $this->whenLoaded('subtasks', fn (): array => $this->subtasks->map(fn (Task $task): array => $this->summary($task))->values()->all()),
            'labels' => $this->whenLoaded('labels', fn (): array => $this->labels->map(fn ($label): array => ['id' => $label->id, 'name' => $label->name, 'slug' => $label->slug, 'color' => $label->color])->values()->all()),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'slug' => $this->project->slug,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn (): ?array => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'due_at' => $this->due_at?->toDateString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function summary(Task $task): array
    {
        return ['id' => $task->id, 'display_key' => $task->display_key, 'title' => $task->title, 'type' => $task->type->value];
    }
}
