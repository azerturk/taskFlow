<?php

namespace Modules\Tasks\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\CreateTaskLabelData;
use Modules\Tasks\Data\UpdateTaskLabelData;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Exceptions\InvalidTaskLabel;
use Modules\Tasks\Exceptions\LabelOutsideProject;
use Modules\Tasks\Exceptions\TaskMutationNotAllowed;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskLabel;
use Modules\Tasks\Repositories\Contracts\TaskLabelRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Tasks\Support\TaskLabelName;

class TaskLabelService
{
    public function __construct(
        private readonly ProjectMemberService $members,
        private readonly TaskLabelRepositoryInterface $labels,
        private readonly ActivityRecorder $activity,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    /** @return Collection<int, TaskLabel> */
    public function forProject(Project $project): Collection
    {
        return $this->labels->forProject($project);
    }

    public function create(Project $project, CreateTaskLabelData $data, User $actor): TaskLabel
    {
        return DB::transaction(function () use ($project, $data, $actor): TaskLabel {
            $this->manage($project, $actor);
            $labelName = TaskLabelName::from($data->name);
            $this->ensureUnique($project, $labelName->name, $labelName->slug);
            $label = $this->labels->save(new TaskLabel([
                'project_id' => $project->id,
                'name' => $labelName->name,
                'slug' => $labelName->slug,
                'color' => $data->color->value,
            ]));
            $this->activity->record(ActivityEvent::LabelCreated, $actor, $label, [
                'project_id' => $project->id,
                'label_id' => $label->id,
                'label_name' => $label->name,
            ]);

            return $label;
        });
    }

    public function update(TaskLabel $label, UpdateTaskLabelData $data, User $actor): TaskLabel
    {
        return DB::transaction(function () use ($label, $data, $actor): TaskLabel {
            $this->manage($label->project, $actor);
            $labelName = TaskLabelName::from($data->name);
            $this->ensureUnique($label->project, $labelName->name, $labelName->slug, $label);
            $old = ['name' => $label->name, 'slug' => $label->slug, 'color' => $label->color];
            $label->fill(['name' => $labelName->name, 'slug' => $labelName->slug, 'color' => $data->color->value]);
            if (! $label->isDirty()) {
                return $label;
            }

            $label = $this->labels->save($label);
            $this->activity->record(ActivityEvent::LabelUpdated, $actor, $label, [
                'project_id' => $label->project_id,
                'label_id' => $label->id,
                'label_name' => $label->name,
                'old' => $old,
                'new' => ['name' => $label->name, 'slug' => $label->slug, 'color' => $label->color],
            ]);

            return $label;
        });
    }

    /** @param list<int> $labelIds */
    public function sync(Task $task, array $labelIds, User $actor): Task
    {
        return DB::transaction(fn (): Task => $this->syncWithinTransaction($task, $labelIds, $actor));
    }

    /**
     * Transaction-neutral collaborator for TaskService.
     *
     * @param  list<int>  $labelIds
     */
    public function syncWithinTransaction(Task $task, array $labelIds, User $actor): Task
    {
        $task = $this->tasks->withProject($task);
        $project = $task->project;
        if ($project->status !== ProjectStatus::Active
            || (! $this->members->canManage($project, $actor)
                && (! $this->members->isMember($project, $actor)
                    || $task->creator_id !== $actor->id
                    || ! in_array($task->status, [TaskStatus::Backlog, TaskStatus::Todo], true)))) {
            throw new TaskMutationNotAllowed('The actor cannot change task labels.');
        }

        $ids = array_values(array_unique($labelIds));
        sort($ids);
        if ($this->labels->countForProject($project, $ids) !== count($ids)) {
            throw new LabelOutsideProject('Every label must belong to the task project.');
        }

        $before = $this->labels->idsForTask($task);
        $this->labels->syncForTask($task, $ids);
        if ($before !== $ids) {
            $this->activity->record(ActivityEvent::TaskLabelsUpdated, $actor, $task, [
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'label_ids' => $ids,
                'old' => ['label_ids' => $before],
                'new' => ['label_ids' => $ids],
            ]);
        }

        return $this->tasks->prepareForResource($task);
    }

    public function delete(TaskLabel $label, User $actor): void
    {
        DB::transaction(function () use ($label, $actor): void {
            $this->manage($label->project, $actor);
            $projectId = $label->project_id;
            $labelId = $label->id;
            $name = $label->name;
            $this->labels->delete($label);
            $this->activity->record(ActivityEvent::LabelDeleted, $actor, $label, [
                'project_id' => $projectId,
                'label_id' => $labelId,
                'label_name' => $name,
            ]);
        });
    }

    private function ensureUnique(Project $project, string $name, string $slug, ?TaskLabel $ignore = null): void
    {
        if ($this->labels->conflictsWith($project, $name, $slug, $ignore)) {
            throw new InvalidTaskLabel('The label name is already in use for this project.');
        }
    }

    private function manage(Project $project, User $actor): void
    {
        if ($project->status !== ProjectStatus::Active || ! $this->members->canManage($project, $actor)) {
            throw new TaskMutationNotAllowed('The actor cannot manage project labels.');
        }
    }
}
