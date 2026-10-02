<?php

namespace Modules\Projects\Services;

use App\Models\User;
use Modules\Activity\Services\ActivityQueryService;
use Modules\Projects\Data\ProjectFiltersData;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Repositories\Contracts\ProjectRepositoryInterface;

class ProjectQueryService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly ActivityQueryService $activity,
    ) {}

    public function indexPage(User $actor, ProjectFiltersData $filters, int $perPage = 12): array
    {
        return [
            'projects' => $this->projects->paginateFor($actor, $filters, $perPage),
            'statuses' => ProjectStatus::cases(),
        ];
    }

    public function detailPage(User $actor, Project $project, bool $includeActivity): array
    {
        $project = $this->projects->detailFor($actor, $project);

        return [
            'project' => $project,
            'activities' => $includeActivity ? $this->activity->recentForProject($project) : null,
            'canViewActivity' => $includeActivity,
        ];
    }

    public function detail(User $actor, Project $project): Project
    {
        return $this->projects->detailFor($actor, $project);
    }
}
