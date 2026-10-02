<?php

namespace Modules\Projects\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Projects\Data\UpdateProjectMemberData;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Exceptions\DuplicateProjectMember;
use Modules\Projects\Exceptions\InvalidProjectMember;
use Modules\Projects\Exceptions\MemberHasOpenAssignments;
use Modules\Projects\Exceptions\ProjectReadOnly;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Projects\Repositories\Contracts\ProjectMemberRepositoryInterface;
use Modules\Projects\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskWatcherRepositoryInterface;

class ProjectMemberService
{
    public function __construct(
        private readonly ProjectMemberRepositoryInterface $members,
        private readonly ActivityRecorder $activity,
        private readonly TaskWatcherRepositoryInterface $watchers,
        private readonly TaskRepositoryInterface $tasks,
        private readonly UserRepositoryInterface $users,
        private readonly ProjectRepositoryInterface $projects,
    ) {}

    public function addMember(Project $project, User $user, ProjectMemberRole $role, ?DateTimeInterface $joinedAt = null, ?User $actor = null): ProjectMember
    {
        return DB::transaction(fn (): ProjectMember => $this->addMemberWithinTransaction($project, $user, $role, $joinedAt, $actor));
    }

    public function addMemberById(Project $project, int $userId, ProjectMemberRole $role, User $actor): ProjectMember
    {
        $user = $this->users->find($userId);
        if ($user === null) {
            throw new InvalidProjectMember('The requested user cannot be added to this project.');
        }

        return DB::transaction(fn (): ProjectMember => $this->addMemberWithinTransaction($project, $user, $role, actor: $actor));
    }

    /** Transaction-neutral collaborator for ProjectService. */
    public function addMemberWithinTransaction(Project $project, User $user, ProjectMemberRole $role, ?DateTimeInterface $joinedAt = null, ?User $actor = null): ProjectMember
    {
        $this->ensureMutable($project);

        if (! $project->exists || ! $user->exists) {
            throw new LogicException('Projects and users must be persisted before membership is created.');
        }

        if (! $user->isActive()) {
            throw new InvalidProjectMember('The requested user cannot be added to this project.');
        }

        if ($project->owner_id === $user->id && $role !== ProjectMemberRole::Manager) {
            throw new InvalidProjectMember('The project owner must remain a project manager.');
        }

        if ($this->isMember($project, $user)) {
            throw new DuplicateProjectMember('This user is already a project member.');
        }

        $membership = $this->members->create($project, $user, $role, $joinedAt ?? now());
        $this->activity->record(ActivityEvent::ProjectMemberAdded, $actor ?? $user, $project, [
            'project_id' => $project->id,
            'member_id' => $user->id,
            'member_name' => $user->name ?: $user->email,
            'member_role' => $role->value,
        ]);

        return $membership;
    }

    public function updateMemberRole(Project $project, User $user, UpdateProjectMemberData $data, ?User $actor = null): ProjectMember
    {
        return DB::transaction(function () use ($project, $user, $data, $actor): ProjectMember {
            $this->ensureMutable($project);
            $existing = $this->members->find($project, $user);
            if ($existing === null) {
                throw new InvalidProjectMember('The requested user is not a project member.');
            }
            if ($project->owner_id === $user->id && $data->role !== ProjectMemberRole::Manager) {
                throw new InvalidProjectMember('The project owner must remain a project manager.');
            }

            if ($existing->member_role === $data->role) {
                return $existing;
            }

            $membership = $this->members->updateRole($project, $user, $data->role);
            $this->activity->record(ActivityEvent::ProjectMemberRoleUpdated, $actor ?? $user, $project, [
                'project_id' => $project->id,
                'member_id' => $user->id,
                'member_name' => $user->name ?: $user->email,
                'old_member_role' => $existing->member_role->value,
                'new_member_role' => $data->role->value,
            ]);

            return $membership;
        });
    }

    public function removeMember(Project $project, User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($project, $user, $actor): void {
            $this->ensureMutable($project);
            $this->ensureExistingNonOwnerMembership($project, $user, 'removed');

            $openAssignments = $this->tasks->openAssignmentCountFor($project, $user);
            if ($openAssignments > 0) {
                throw new MemberHasOpenAssignments($openAssignments);
            }

            // Remove project-scoped subscriptions before revoking membership visibility.
            $this->watchers->removeForProject($project, $user);
            $this->members->delete($project, $user);
            $this->activity->record(ActivityEvent::ProjectMemberRemoved, $actor ?? $user, $project, [
                'project_id' => $project->id,
                'member_id' => $user->id,
                'member_name' => $user->name ?: $user->email,
            ]);
        });
    }

    public function isMember(Project $project, User $user): bool
    {
        return $this->members->exists($project, $user);
    }

    public function isManager(Project $project, User $user): bool
    {
        return $this->members->isManager($project, $user);
    }

    public function canParticipate(Project $project, User $user): bool
    {
        return $user->isActive()
            && ($user->hasRole(UserRole::Admin->value)
                || $project->owner_id === $user->id
                || $this->isMember($project, $user));
    }

    public function canManage(Project $project, User $user): bool
    {
        return $user->isActive()
            && (
                $user->hasRole(UserRole::Admin->value)
                || $project->owner_id === $user->id
                || $this->isManager($project, $user)
            );
    }

    /** @return Collection<int, ProjectMember> */
    public function memberships(Project $project): Collection
    {
        return $this->members->allForProject($project);
    }

    public function paginateMemberships(Project $project, int $perPage): LengthAwarePaginator
    {
        return $this->members->paginateForProject($project, $perPage);
    }

    public function memberForProject(Project $project, int $userId): User
    {
        return $this->members->userForProjectOrFail($project, $userId);
    }

    /** @return array{project: Project, memberships: LengthAwarePaginator} */
    public function paginatedPageFor(User $actor, Project $project, int $perPage): array
    {
        $project = $this->projects->detailFor($actor, $project);

        return [
            'project' => $project,
            'memberships' => $this->members->paginateForProject($project, $perPage),
        ];
    }

    /** @return Collection<int, User> */
    public function availableUsers(Project $project): Collection
    {
        return $this->members->availableUsers($project);
    }

    /** @return array{project: Project, memberships: Collection, availableUsers: Collection, roles: array} */
    public function managementPage(Project $project): array
    {
        return [
            'project' => $this->projects->prepareForMembers($project),
            'memberships' => $this->members->allForProject($project),
            'availableUsers' => $this->members->availableUsers($project),
            'roles' => ProjectMemberRole::cases(),
        ];
    }

    private function ensureMutable(Project $project): void
    {
        if (in_array($project->status, [ProjectStatus::Completed, ProjectStatus::Archived], true)) {
            throw new ProjectReadOnly('Completed and archived projects are read-only.');
        }
    }

    private function ensureExistingNonOwnerMembership(Project $project, User $user, string $action): void
    {
        if ($project->owner_id === $user->id) {
            throw new InvalidProjectMember("The project owner cannot be {$action} from project membership.");
        }

        if (! $this->isMember($project, $user)) {
            throw new InvalidProjectMember('The requested user is not a project member.');
        }
    }
}
