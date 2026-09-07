<?php

namespace Modules\Projects\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Projects\Data\AllocatedIssueNumberData;
use Modules\Projects\Data\ChangeProjectStatusData;
use Modules\Projects\Data\CreateProjectData;
use Modules\Projects\Data\UpdateProjectData;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Exceptions\InvalidProjectTransition;
use Modules\Projects\Exceptions\ProjectKeyImmutable;
use Modules\Projects\Exceptions\ProjectReadOnly;
use Modules\Projects\Models\Project;
use Modules\Projects\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly ProjectMemberService $members,
        private readonly ActivityRecorder $activity,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    public function create(User $actor, CreateProjectData $data): Project
    {
        return DB::transaction(function () use ($actor, $data): Project {
            $project = new Project([
                'name' => $data->name,
                'key' => $data->key,
                'slug' => $this->uniqueSlug($data->name),
                'description' => $data->description,
                'status' => ProjectStatus::Draft,
                'owner_id' => $actor->id,
                'starts_at' => $data->startsAt,
                'due_at' => $data->dueAt,
            ]);

            $project = $this->projects->save($project);
            $this->members->addMemberWithinTransaction($project, $actor, ProjectMemberRole::Manager, actor: $actor);
            $this->activity->record(ActivityEvent::ProjectCreated, $actor, $project, ['project_id' => $project->id, 'project_name' => $project->name, 'project_key' => $project->key, 'status' => $project->status->value]);

            return $this->projects->detailFor($actor, $project);
        });
    }

    public function update(Project $project, UpdateProjectData $data, User $actor): Project
    {
        return DB::transaction(function () use ($project, $data, $actor): Project {
            $this->ensureDetailsMutable($project);

            if ($data->key !== null && $data->key !== $project->key && $this->tasks->existsForProject($project)) {
                throw new ProjectKeyImmutable('Project key cannot change after an issue has been allocated.');
            }

            $old = ['name' => $project->name, 'key' => $project->key, 'description' => $project->description, 'starts_at' => $project->starts_at?->toDateString(), 'due_at' => $project->due_at?->toDateString()];

            $project->fill([
                'name' => $data->name,
                'key' => $data->key ?? $project->key,
                'description' => $data->description,
                'starts_at' => $data->startsAt,
                'due_at' => $data->dueAt,
            ]);

            if ($project->isDirty('name')) {
                $project->slug = $this->uniqueSlug($data->name, $project);
            }

            $changed = array_keys($project->getDirty());
            $project = $this->projects->save($project);

            if ($changed !== []) {
                $this->activity->record(ActivityEvent::ProjectUpdated, $actor, $project, [
                    'project_id' => $project->id,
                    'changed' => $changed,
                    'old' => array_intersect_key($old, array_flip($changed)),
                    'new' => array_intersect_key(['name' => $project->name, 'key' => $project->key, 'description' => $project->description, 'starts_at' => $project->starts_at?->toDateString(), 'due_at' => $project->due_at?->toDateString()], array_flip($changed)),
                ]);
            }

            return $this->projects->detailFor($actor, $project);
        });
    }

    public function allocateIssueNumber(Project $project): AllocatedIssueNumberData
    {
        $lockedProject = $this->projects->lockForUpdate($project);
        if ($lockedProject->status !== ProjectStatus::Active) {
            throw new ProjectReadOnly('Issue numbers can only be allocated for active projects.');
        }
        $issueNumber = $lockedProject->next_issue_number;
        $lockedProject->forceFill(['next_issue_number' => $lockedProject->next_issue_number + 1]);
        $this->projects->save($lockedProject);

        return AllocatedIssueNumberData::forProject($lockedProject->key, $issueNumber);
    }

    public function changeStatus(Project $project, ChangeProjectStatusData $data, User $actor): Project
    {
        return DB::transaction(function () use ($project, $data, $actor): Project {
            $lockedProject = $this->projects->lockForUpdate($project);
            $oldStatus = $lockedProject->status;
            if (! $oldStatus->canTransitionTo($data->status)) {
                throw new InvalidProjectTransition('The requested project lifecycle transition is not allowed.');
            }
            $lockedProject->status = $data->status;

            $project = $this->projects->save($lockedProject);
            $this->activity->record(ActivityEvent::ProjectStatusChanged, $actor, $project, ['project_id' => $project->id, 'old_status' => $oldStatus->value, 'new_status' => $project->status->value]);

            return $this->projects->detailFor($actor, $project);
        });
    }

    private function uniqueSlug(string $name, ?Project $excludingProject = null): string
    {
        $baseSlug = Str::slug($name) ?: 'project';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->projects->slugExists($slug, $excludingProject?->id)) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function ensureDetailsMutable(Project $project): void
    {
        if (in_array($project->status, [ProjectStatus::Completed, ProjectStatus::Archived], true)) {
            throw new ProjectReadOnly('Completed and archived projects are read-only.');
        }
    }
}
