<?php

namespace Modules\Activity\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Activity\Http\Requests\Api\V1\ActivityIndexRequest;
use Modules\Activity\Http\Resources\ActivityResource;
use Modules\Activity\Services\ActivityQueryService;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Spatie\Activitylog\Models\Activity;

class ActivityController
{
    use AuthorizesRequests;

    public function __construct(private readonly ActivityQueryService $activity) {}

    public function index(ActivityIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);

        return ActivityResource::collection($this->activity->paginate(
            $request->user(),
            $request->filters(),
            $request->integer('per_page', 20),
        ));
    }

    public function forProject(ActivityIndexRequest $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);
        $this->authorize('view', $project);

        return ActivityResource::collection($this->activity->paginate(
            $request->user(),
            $request->filters(['project_id' => $project->id]),
            $request->integer('per_page', 20),
        ));
    }

    public function forTask(ActivityIndexRequest $request, Task $task): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);
        $this->authorize('view', $task);

        return ActivityResource::collection($this->activity->paginate(
            $request->user(),
            $request->filters(['task_id' => $task->id]),
            $request->integer('per_page', 20),
        ));
    }
}
