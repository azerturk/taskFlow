<?php

namespace Modules\Projects\Repositories\Eloquent;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Projects\Data\ProjectFiltersData;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Repositories\Contracts\ProjectRepositoryInterface;

class EloquentProjectRepository implements ProjectRepositoryInterface
{
    public function findOrFail(int $id): Project
    {
        return Project::query()->findOrFail($id);
    }

    public function findVisibleOrFail(User $user, int $id): Project
    {
        return $this->visibleTo(Project::query(), $user)->whereKey($id)->firstOrFail();
    }

    public function paginateFor(User $user, ProjectFiltersData $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->visibleTo($this->baseQuery($filters->search, $filters->status), $user)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function detailFor(User $user, Project $project): Project
    {
        return $this->visibleTo($this->baseQuery(null, null), $user)
            ->whereKey($project->id)
            ->firstOrFail();
    }

    public function activeForTaskCreation(User $user): Collection
    {
        return $this->visibleTo(Project::query()->where('status', ProjectStatus::Active->value), $user)
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'status', 'owner_id']);
    }

    public function prepareForMembers(Project $project): Project
    {
        return Project::query()->with('owner')->whereKey($project->id)->firstOrFail();
    }

    public function save(Project $project): Project
    {
        $project->save();

        return $project;
    }

    public function lockForUpdate(Project $project): Project
    {
        return Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
    }

    public function slugExists(string $slug, ?int $excludingProjectId = null): bool
    {
        return Project::query()
            ->where('slug', $slug)
            ->when($excludingProjectId, fn ($query) => $query->where('id', '!=', $excludingProjectId))
            ->exists();
    }

    public function dashboardStatusSummaryFor(User $user): array
    {
        $counts = $this->visibleTo(Project::query(), $user)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();
        $distribution = [];
        foreach (ProjectStatus::cases() as $status) {
            $distribution[$status->value] = $counts[$status->value] ?? 0;
        }

        return [
            'active' => $distribution[ProjectStatus::Active->value],
            'completed' => $distribution[ProjectStatus::Completed->value],
            'archived' => $distribution[ProjectStatus::Archived->value],
            'distribution' => $distribution,
        ];
    }

    private function baseQuery(?string $search, ?string $status): Builder
    {
        return Project::query()
            ->with('owner')
            ->withCount(['memberships', 'tasks'])
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('key', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(ProjectStatus::tryFrom((string) $status), fn ($query, ProjectStatus $projectStatus) => $query->where('status', $projectStatus->value));
    }

    private function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Admin->value)) {
            return $query;
        }

        return $query->where(function ($query) use ($user): void {
            $query->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($members) => $members->whereKey($user->id));
        });
    }
}
