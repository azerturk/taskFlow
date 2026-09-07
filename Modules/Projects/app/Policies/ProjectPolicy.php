<?php

namespace Modules\Projects\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;

class ProjectPolicy
{
    public function __construct(private readonly ProjectMemberService $members) {}

    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasPermissionTo(PermissionName::ProjectsView->value);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasPermissionTo(PermissionName::ProjectsCreate->value);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermissionTo(PermissionName::ProjectsView->value)
            && $this->members->canParticipate($project, $user);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->members->canManage($project, $user);
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function manageLabels(User $user, Project $project): bool
    {
        return $project->status === ProjectStatus::Active && $this->manageMembers($user, $project);
    }
}
