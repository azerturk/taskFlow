<?php

namespace Modules\Activity\Repositories\Eloquent;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Activity\Data\ActivityFiltersData;
use Modules\Activity\Repositories\Contracts\ActivityRepositoryInterface;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Spatie\Activitylog\Models\Activity;

class EloquentActivityRepository implements ActivityRepositoryInterface
{
    public function recentForProject(Project $project, int $limit): Collection
    {
        return Activity::query()->with('causer')->where('properties->project_id', $project->id)->latest()->take($limit)->get();
    }

    public function recentForTask(Task $task, int $limit): Collection
    {
        return Activity::query()->with('causer')->where('properties->task_id', $task->id)->latest()->take($limit)->get();
    }

    public function recentForUser(User $user, int $limit): Collection
    {
        return $this->scopedQuery($user)->latest()->take($limit)->get();
    }

    public function paginateFor(User $user, ActivityFiltersData $filters, int $perPage): LengthAwarePaginator
    {
        return $this->scopedQuery($user)
            ->when($filters->event, fn (Builder $query, $event) => $query->where('event', $event->value))
            ->when($filters->projectId, fn (Builder $query, int $id) => $query->where('properties->project_id', $id))
            ->when($filters->taskId, fn (Builder $query, int $id) => $query->where('properties->task_id', $id))
            ->when($filters->actorId, fn (Builder $query, int $id) => $query->where('causer_id', $id))
            ->when($filters->dateFrom, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date->toDateString()))
            ->when($filters->dateTo, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date->toDateString()))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function filterOptionsFor(User $user): array
    {
        $activities = $this->scopedQuery($user);
        $properties = (clone $activities)->get(['properties']);
        $projectIds = $properties->map(fn (Activity $activity) => $activity->properties['project_id'] ?? null)->filter()->unique()->values();
        $taskIds = $properties->map(fn (Activity $activity) => $activity->properties['task_id'] ?? null)->filter()->unique()->values();
        $actorIds = (clone $activities)->whereNotNull('causer_id')->pluck('causer_id')->unique()->filter();

        return [
            'events' => (clone $activities)->pluck('event')->unique()->sort()->values(),
            'projects' => Project::query()->whereIn('id', $projectIds)->orderBy('name')->get(),
            'tasks' => Task::query()->withTrashed()->whereIn('id', $taskIds)->orderBy('number')->get(),
            'actors' => User::query()->whereIn('id', $actorIds)->orderBy('name')->get(),
        ];
    }

    private function scopedQuery(User $user): Builder
    {
        $query = Activity::query()->with(['causer', 'subject']);

        if ($user->hasRole(UserRole::Admin->value)) {
            return $query;
        }

        $projectIds = Project::query()
            ->where('owner_id', $user->id)
            ->pluck('id')
            ->merge(Project::query()
                ->whereHas('memberships', fn (Builder $memberships) => $memberships->where('user_id', $user->id))
                ->pluck('id'))
            ->unique()
            ->values();

        return $query->where(function (Builder $scope) use ($projectIds, $user): void {
            $scope->whereIn('properties->project_id', $projectIds->all())
                ->orWhere(function (Builder $userActivity) use ($user): void {
                    $userActivity->where('causer_id', $user->id)->where('subject_type', User::class);
                });
        });
    }
}
