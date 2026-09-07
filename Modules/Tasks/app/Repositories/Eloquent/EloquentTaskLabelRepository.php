<?php

namespace Modules\Tasks\Repositories\Eloquent;

use Illuminate\Support\Collection;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskLabel;
use Modules\Tasks\Repositories\Contracts\TaskLabelRepositoryInterface;

class EloquentTaskLabelRepository implements TaskLabelRepositoryInterface
{
    public function forProject(Project $project): Collection
    {
        return TaskLabel::query()->where('project_id', $project->id)->orderBy('name')->get();
    }

    public function save(TaskLabel $label): TaskLabel
    {
        $label->save();

        return $label;
    }

    public function delete(TaskLabel $label): void
    {
        $label->delete();
    }

    public function findForProjectOrFail(Project $project, int $id): TaskLabel
    {
        return TaskLabel::query()
            ->where('project_id', $project->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    public function countForProject(Project $project, array $ids): int
    {
        return TaskLabel::query()->where('project_id', $project->id)->whereIn('id', $ids)->count();
    }

    public function conflictsWith(Project $project, string $name, string $slug, ?TaskLabel $ignore = null): bool
    {
        return TaskLabel::query()
            ->where('project_id', $project->id)
            ->where(fn ($query) => $query->where('name', $name)->orWhere('slug', $slug))
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();
    }

    public function idsForTask(Task $task): array
    {
        return $task->labels()->pluck('task_labels.id')->sort()->values()->all();
    }

    public function syncForTask(Task $task, array $ids): void
    {
        $task->labels()->sync($ids);
    }
}
