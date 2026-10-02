<?php

namespace Modules\Tasks\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Tasks\Data\AssignTaskData;
use Modules\Tasks\Data\ChangeTaskStatusData;
use Modules\Tasks\Data\CreateTaskData;
use Modules\Tasks\Data\ReorderTaskData;
use Modules\Tasks\Data\SyncTaskLabelsData;
use Modules\Tasks\Data\UpdateTaskData;
use Modules\Tasks\Http\Requests\Api\V1\StoreTaskRequest;
use Modules\Tasks\Http\Requests\Api\V1\TaskIndexRequest;
use Modules\Tasks\Http\Requests\AssignTaskRequest;
use Modules\Tasks\Http\Requests\ChangeTaskStatusRequest;
use Modules\Tasks\Http\Requests\ReorderTaskRequest;
use Modules\Tasks\Http\Requests\SyncTaskLabelsRequest;
use Modules\Tasks\Http\Requests\UpdateTaskRequest;
use Modules\Tasks\Http\Resources\TaskResource;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskAssignmentService;
use Modules\Tasks\Services\TaskQueryService;
use Modules\Tasks\Services\TaskRankService;
use Modules\Tasks\Services\TaskService;
use Modules\Tasks\Services\TaskStatusService;

class TaskController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskQueryService $queries,
        private readonly TaskService $taskService,
        private readonly TaskAssignmentService $assignments,
        private readonly TaskStatusService $statuses,
        private readonly TaskRankService $ranks,
    ) {}

    public function index(TaskIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Task::class);

        return TaskResource::collection($this->queries->paginateFor(
            $request->user(),
            $request->filters(),
            $request->integer('per_page', 12),
        ));
    }

    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource($this->queries->resource($task));
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->authorize('createAny', Task::class);

        $task = $this->taskService->createForProjectId(
            $request->user(),
            $data['project_id'],
            CreateTaskData::fromArray($data),
        );

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $task = $this->taskService->update($task, UpdateTaskData::fromArray($request->validated()), $request->user());

        return new TaskResource($task);
    }

    public function syncLabels(SyncTaskLabelsRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);
        $task = $this->taskService->syncLabels($task, SyncTaskLabelsData::fromArray($request->validated())->labelIds, $request->user());

        return new TaskResource($task);
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $this->taskService->delete($task, request()->user());

        return response()->json(null, 204);
    }

    public function assign(AssignTaskRequest $request, Task $task): TaskResource
    {
        $assigneeId = $request->filled('assignee_id') ? $request->integer('assignee_id') : null;
        $this->authorize('assign', [$task, $assigneeId]);
        $task = $this->assignments->assign($task, new AssignTaskData($assigneeId), $request->user());

        return new TaskResource($task);
    }

    public function changeStatus(ChangeTaskStatusRequest $request, Task $task): TaskResource
    {
        $this->authorize('changeStatus', $task);
        $task = $this->statuses->change(
            $task,
            ChangeTaskStatusData::fromArray($request->validated()),
            $request->user(),
        );

        return new TaskResource($task);
    }

    public function reorder(ReorderTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('reorder', $task);

        return new TaskResource($this->ranks->reorder($task, ReorderTaskData::fromArray($request->validated()), $request->user()));
    }
}
