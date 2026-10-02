<?php

namespace Modules\Tasks\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Projects\Models\Project;
use Modules\Tasks\Http\Requests\Api\V1\TaskReadModelRequest;
use Modules\Tasks\Http\Resources\TaskResource;
use Modules\Tasks\Services\BacklogQueryService;

class BacklogController
{
    use AuthorizesRequests;

    public function __construct(private readonly BacklogQueryService $backlog) {}

    public function show(TaskReadModelRequest $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        return TaskResource::collection($this->backlog->paginate(
            $project,
            $request->user(),
            $request->filters('rank'),
            $request->integer('per_page', 25),
        ));
    }
}
