<?php

namespace Modules\Tasks\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Modules\Projects\Models\Project;
use Modules\Tasks\Http\Requests\Api\V1\TaskReadModelRequest;
use Modules\Tasks\Http\Resources\TaskResource;
use Modules\Tasks\Services\TaskBoardQueryService;

class TaskBoardController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskBoardQueryService $board) {}

    public function show(TaskReadModelRequest $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => collect($this->board->forProject($project, $request->user(), $request->filters('rank')))
                ->map(fn ($tasks) => TaskResource::collection($tasks)->resolve())
                ->all(),
        ]);
    }
}
