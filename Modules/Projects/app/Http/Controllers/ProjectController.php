<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Projects\Data\ChangeProjectStatusData;
use Modules\Projects\Data\CreateProjectData;
use Modules\Projects\Data\ProjectFiltersData;
use Modules\Projects\Data\UpdateProjectData;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Http\Requests\ProjectIndexRequest;
use Modules\Projects\Http\Requests\StoreProjectRequest;
use Modules\Projects\Http\Requests\UpdateProjectRequest;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectQueryService;
use Modules\Projects\Services\ProjectService;
use Spatie\Activitylog\Models\Activity;

class ProjectController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProjectQueryService $queries,
        private readonly ProjectService $projectService,
    ) {}

    public function index(ProjectIndexRequest $request): View
    {
        $this->authorize('viewAny', Project::class);

        return view('projects::index', $this->queries->indexPage(
            $request->user(),
            ProjectFiltersData::fromArray($request->validated()),
        ));
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects::create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $project = $this->projectService->create(
            $request->user(),
            CreateProjectData::fromArray($request->validated()),
        );

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);
        $canViewActivity = request()->user()->can('viewAny', Activity::class);

        return view('projects::show', $this->queries->detailPage(request()->user(), $project, $canViewActivity));
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects::edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project = $this->projectService->update($project, UpdateProjectData::fromArray($request->validated()), $request->user());

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }

    public function archive(Project $project): RedirectResponse
    {
        $this->authorize('archive', $project);

        $this->projectService->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Archived), request()->user());

        return redirect()->route('projects.index')
            ->with('success', 'Project archived successfully.');
    }

    public function activate(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->projectService->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Active), request()->user());

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project activated successfully.');
    }

    public function complete(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->projectService->changeStatus($project, new ChangeProjectStatusData(ProjectStatus::Completed), request()->user());

        return redirect()->route('projects.show', $project)->with('success', 'Project completed successfully.');
    }
}
