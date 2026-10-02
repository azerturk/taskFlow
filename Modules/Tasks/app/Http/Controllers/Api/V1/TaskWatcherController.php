<?php

namespace Modules\Tasks\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Tasks\Http\Requests\ManageTaskWatcherRequest;
use Modules\Tasks\Http\Resources\TaskWatcherResource;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskWatcherService;

class TaskWatcherController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskWatcherService $watchers) {}

    public function index(Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        return TaskWatcherResource::collection($this->watchers->watchersFor($task));
    }

    public function store(ManageTaskWatcherRequest $request, Task $task): JsonResponse
    {
        $userId = $request->filled('user_id') ? $request->integer('user_id') : $request->user()->id;
        $this->authorize('watch', [$task, $userId]);
        $this->watchers->watchById($task, $userId, $request->user());

        return response()->json(null, 204);
    }

    public function destroy(ManageTaskWatcherRequest $request, Task $task, string $user): JsonResponse
    {
        $userId = (int) $user;
        $this->authorize('watch', [$task, $userId]);
        $this->watchers->unwatchById($task, $userId, $request->user());

        return response()->json(null, 204);
    }
}
