<?php

namespace Modules\Projects\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Projects\Data\ProjectFiltersData;
use Modules\Projects\Models\Project;

interface ProjectRepositoryInterface
{
    public function findOrFail(int $id): Project;

    public function findVisibleOrFail(User $user, int $id): Project;

    public function paginateFor(User $user, ProjectFiltersData $filters, int $perPage = 12): LengthAwarePaginator;

    public function detailFor(User $user, Project $project): Project;

    /** @return Collection<int, Project> */
    public function activeForTaskCreation(User $user): Collection;

    public function prepareForMembers(Project $project): Project;

    public function save(Project $project): Project;

    public function lockForUpdate(Project $project): Project;

    public function slugExists(string $slug, ?int $excludingProjectId = null): bool;

    /** @return array{active: int, completed: int, archived: int, distribution: array<string, int>} */
    public function dashboardStatusSummaryFor(User $user): array;
}
