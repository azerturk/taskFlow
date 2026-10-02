<?php

namespace Modules\Projects\Repositories\Eloquent;

use App\Enums\AccountStatus;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Projects\Repositories\Contracts\ProjectMemberRepositoryInterface;

class EloquentProjectMemberRepository implements ProjectMemberRepositoryInterface
{
    public function create(Project $project, User $user, ProjectMemberRole $role, DateTimeInterface $joinedAt): ProjectMember
    {
        $membership = ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'member_role' => $role,
            'joined_at' => $joinedAt,
        ]);

        return $this->prepare($membership);
    }

    public function delete(Project $project, User $user): void
    {
        ProjectMember::query()->where('project_id', $project->id)->where('user_id', $user->id)->delete();
    }

    public function updateRole(Project $project, User $user, ProjectMemberRole $role): ProjectMember
    {
        $membership = ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->firstOrFail();
        $membership->member_role = $role;
        $membership->save();

        return $this->prepare($membership);
    }

    public function find(Project $project, User $user): ?ProjectMember
    {
        return ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();
    }

    public function userForProjectOrFail(Project $project, int $userId): User
    {
        return User::query()
            ->whereKey($userId)
            ->whereHas('projectMemberships', fn ($memberships) => $memberships->where('project_id', $project->id))
            ->firstOrFail();
    }

    public function exists(Project $project, User $user): bool
    {
        return ProjectMember::query()->where('project_id', $project->id)->where('user_id', $user->id)->exists();
    }

    public function isManager(Project $project, User $user): bool
    {
        return ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->where('member_role', ProjectMemberRole::Manager->value)
            ->exists();
    }

    public function allForProject(Project $project): Collection
    {
        return ProjectMember::query()->with('user')->where('project_id', $project->id)->orderBy('member_role')->get();
    }

    public function paginateForProject(Project $project, int $perPage): LengthAwarePaginator
    {
        return ProjectMember::query()
            ->with('user')
            ->where('project_id', $project->id)
            ->orderBy('member_role')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function availableUsers(Project $project): Collection
    {
        return User::query()
            ->where('status', AccountStatus::Active->value)
            ->whereNotIn('id', ProjectMember::query()->where('project_id', $project->id)->select('user_id'))
            ->orderBy('name')
            ->get();
    }

    public function prepare(ProjectMember $membership): ProjectMember
    {
        return ProjectMember::query()->with('user')->whereKey($membership->id)->firstOrFail();
    }
}
