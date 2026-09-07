<?php

namespace Modules\Activity\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Modules\Activity\Http\Requests\ActivityIndexRequest;
use Modules\Activity\Services\ActivityQueryService;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Spatie\Activitylog\Models\Activity;

class ActivityController
{
    use AuthorizesRequests;

    public function __construct(private readonly ActivityQueryService $activity) {}

    public function index(ActivityIndexRequest $request): View
    {
        $this->authorize('viewAny', Activity::class);

        return view('activity::index', $this->activity->page(
            $request->user(),
            $request->filters(),
            $request->integer('per_page', 20),
        ));
    }

    public function forProject(ActivityIndexRequest $request, Project $project): View
    {
        $this->authorize('viewAny', Activity::class);
        $this->authorize('view', $project);

        return view('activity::index', $this->activity->page(
            $request->user(),
            $request->filters(['project_id' => $project->id]),
            $request->integer('per_page', 20),
        ));
    }

    public function forTask(ActivityIndexRequest $request, Task $task): View
    {
        $this->authorize('viewAny', Activity::class);
        $this->authorize('view', $task);

        return view('activity::index', $this->activity->page(
            $request->user(),
            $request->filters(['task_id' => $task->id]),
            $request->integer('per_page', 20),
        ));
    }
}
