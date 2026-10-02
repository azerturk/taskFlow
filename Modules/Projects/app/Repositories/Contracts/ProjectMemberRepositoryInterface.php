<?php

namespace Modules\Projects\Repositories\Contracts;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;

interface ProjectMemberRepositoryInterface
{
    public function create(Project $project, User $user, ProjectMemberRole $role, DateTimeInterface $joinedAt): ProjectMember;

    public function delete(Project $project, User $user): void;

    public function updateRole(Project $project, User $user, ProjectMemberRole $role): ProjectMember;

    public function find(Project $project, User $user): ?ProjectMember;

    public function userForProjectOrFail(Project $project, int $userId): User;

    public function exists(Project $project, User $user): bool;

    public function isManager(Project $project, User $user): bool;

    /** @return Collection<int, ProjectMember> */
    public function allForProject(Project $project): Collection;

    public function paginateForProject(Project $project, int $perPage): LengthAwarePaginator;

    /** @return Collection<int, User> */
    public function availableUsers(Project $project): Collection;

    public function prepare(ProjectMember $membership): ProjectMember;
}
