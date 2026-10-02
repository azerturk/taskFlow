<?php

namespace Modules\Dashboard\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Dashboard\Http\Requests\Api\V1\DashboardOverdueRequest;
use Modules\Dashboard\Http\Resources\DashboardSummaryResource;
use Modules\Dashboard\Services\DashboardService;
use Modules\Tasks\Http\Resources\TaskResource;

class DashboardController
{
    use AuthorizesRequests;

    public function __construct(private readonly DashboardService $dashboard) {}

    public function summary(Request $request): DashboardSummaryResource
    {
        $this->authorize('viewDashboard');

        return new DashboardSummaryResource($this->dashboard->summary($request->user()));
    }

    public function myTasks(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewDashboard');

        return TaskResource::collection($this->dashboard->myTasks($request->user()));
    }

    public function reported(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewDashboard');

        return TaskResource::collection($this->dashboard->reportedTasks($request->user()));
    }

    public function watched(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewDashboard');

        return TaskResource::collection($this->dashboard->watchedTasks($request->user()));
    }

    public function overdue(DashboardOverdueRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewDashboard');

        return TaskResource::collection($this->dashboard->paginateOverdue(
            $request->user(),
            $request->integer('per_page', 20),
        ));
    }
}
