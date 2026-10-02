<?php

namespace Modules\Tasks\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Projects\Models\Project;
use Modules\Tasks\Data\CreateTaskLabelData;
use Modules\Tasks\Data\UpdateTaskLabelData;
use Modules\Tasks\Exceptions\InvalidTaskLabel;
use Modules\Tasks\Http\Requests\StoreTaskLabelRequest;
use Modules\Tasks\Http\Requests\UpdateTaskLabelRequest;
use Modules\Tasks\Models\TaskLabel;
use Modules\Tasks\Services\TaskLabelService;

class TaskLabelController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskLabelService $labels) {}

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('tasks::labels.index', ['project' => $project, 'labels' => $this->labels->forProject($project)]);
    }

    public function store(StoreTaskLabelRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('manageLabels', $project);
        try {
            $this->labels->create($project, CreateTaskLabelData::fromArray($request->validated()), $request->user());
        } catch (InvalidTaskLabel $exception) {
            throw ValidationException::withMessages(['name' => [$exception->getMessage()]]);
        }

        return back()->with('success', 'Label created.');
    }

    public function update(UpdateTaskLabelRequest $request, Project $project, TaskLabel $label): RedirectResponse
    {
        $this->authorize('manageLabels', $project);
        abort_unless($label->project_id === $project->id, 404);
        try {
            $this->labels->update($label, UpdateTaskLabelData::fromArray($request->validated()), $request->user());
        } catch (InvalidTaskLabel $exception) {
            throw ValidationException::withMessages(['name' => [$exception->getMessage()]]);
        }

        return back()->with('success', 'Label updated.');
    }

    public function destroy(Project $project, TaskLabel $label): RedirectResponse
    {
        $this->authorize('manageLabels', $project);
        abort_unless($label->project_id === $project->id, 404);
        $this->labels->delete($label, request()->user());

        return back()->with('success', 'Label deleted.');
    }
}
