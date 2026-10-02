<?php

namespace Modules\Projects\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Projects\Data\ChangeProjectStatusData;
use Modules\Projects\Data\CreateProjectData;
use Modules\Projects\Data\ProjectFiltersData;
use Modules\Projects\Data\UpdateProjectData;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Http\Requests\Api\V1\ProjectIndexRequest;
use Modules\Projects\Http\Requests\ChangeProjectStatusRequest;
use Modules\Projects\Http\Requests\StoreProjectRequest;
use Modules\Projects\Http\Requests\UpdateProjectRequest;
use Modules\Projects\Http\Resources\ProjectResource;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectQueryService;
use Modules\Projects\Services\ProjectService;

class ProjectController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProjectQueryService $queries,
        private readonly ProjectService $projectService,
    ) {}

    public function index(ProjectIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        return ProjectResource::collection($this->queries->indexPage(
            $request->user(),
            ProjectFiltersData::fromArray($request->validated()),
            $request->integer('per_page', 12),
        )['projects']);
    }

    public function show(Request $request, Project $project): ProjectResource
    {
        $project = $this->queries->detail($request->user(), $project);
        $this->authorize('view', $project);

        return new ProjectResource($project);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $project = $this->projectService->create(
            $request->user(),
            CreateProjectData::fromArray($request->validated()),
        );

        return (new ProjectResource($project))->response()->setStatusCode(201);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project = $this->projectService->update(
            $project,
            UpdateProjectData::fromArray($request->validated()),
            $request->user(),
        );

        return new ProjectResource($project);
    }

    public function activate(Request $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        return new ProjectResource($this->projectService->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Active), $request->user()));
    }

    public function changeStatus(ChangeProjectStatusRequest $request, Project $project): ProjectResource
    {
        $this->authorize($request->validated('status') === ProjectStatus::Archived->value ? 'archive' : 'update', $project);

        return new ProjectResource($this->projectService->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::from($request->validated('status'))), $request->user()));
    }
}
