<?php

namespace Modules\Tasks\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Modules\Projects\Models\Project;
use Modules\Tasks\Data\CreateTaskLabelData;
use Modules\Tasks\Data\UpdateTaskLabelData;
use Modules\Tasks\Exceptions\InvalidTaskLabel;
use Modules\Tasks\Http\Requests\StoreTaskLabelRequest;
use Modules\Tasks\Http\Requests\UpdateTaskLabelRequest;
use Modules\Tasks\Http\Resources\TaskLabelResource;
use Modules\Tasks\Models\TaskLabel;
use Modules\Tasks\Services\TaskLabelService;

class TaskLabelController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskLabelService $labels) {}

    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        return TaskLabelResource::collection($this->labels->forProject($project));
    }

    public function store(StoreTaskLabelRequest $request, Project $project): JsonResponse
    {
        $this->authorize('manageLabels', $project);
        try {
            $label = $this->labels->create($project, CreateTaskLabelData::fromArray($request->validated()), $request->user());
        } catch (InvalidTaskLabel $exception) {
            throw ValidationException::withMessages(['name' => [$exception->getMessage()]]);
        }

        return (new TaskLabelResource($label))->response()->setStatusCode(201);
    }

    public function update(UpdateTaskLabelRequest $request, Project $project, TaskLabel $label): TaskLabelResource
    {
        $this->authorize('manageLabels', $project);
        abort_unless($label->project_id === $project->id, 404);
        try {
            $label = $this->labels->update($label, UpdateTaskLabelData::fromArray($request->validated()), $request->user());
        } catch (InvalidTaskLabel $exception) {
            throw ValidationException::withMessages(['name' => [$exception->getMessage()]]);
        }

        return new TaskLabelResource($label);
    }

    public function destroy(Project $project, TaskLabel $label): JsonResponse
    {
        $this->authorize('manageLabels', $project);
        abort_unless($label->project_id === $project->id, 404);
        $this->labels->delete($label, request()->user());

        return response()->json(null, 204);
    }
}
