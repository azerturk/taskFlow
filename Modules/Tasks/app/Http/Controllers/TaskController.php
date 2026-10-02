<?php

namespace Modules\Tasks\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Projects\Models\Project;
use Modules\Tasks\Data\AssignTaskData;
use Modules\Tasks\Data\ChangeTaskStatusData;
use Modules\Tasks\Data\CreateTaskData;
use Modules\Tasks\Data\ReorderTaskData;
use Modules\Tasks\Data\SyncTaskLabelsData;
use Modules\Tasks\Data\UpdateTaskData;
use Modules\Tasks\Http\Requests\AssignTaskRequest;
use Modules\Tasks\Http\Requests\ChangeTaskStatusRequest;
use Modules\Tasks\Http\Requests\CreateTaskRequest;
use Modules\Tasks\Http\Requests\ReorderTaskRequest;
use Modules\Tasks\Http\Requests\SyncTaskLabelsRequest;
use Modules\Tasks\Http\Requests\TaskIndexRequest;
use Modules\Tasks\Http\Requests\UpdateTaskRequest;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskAssignmentService;
use Modules\Tasks\Services\TaskQueryService;
use Modules\Tasks\Services\TaskRankService;
use Modules\Tasks\Services\TaskService;
use Modules\Tasks\Services\TaskStatusService;
use Spatie\Activitylog\Models\Activity;

class TaskController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskQueryService $queries, private readonly TaskService $taskService, private readonly TaskAssignmentService $assignments, private readonly TaskStatusService $statuses, private readonly TaskRankService $ranks) {}

    public function index(TaskIndexRequest $request): View
    {
        $this->authorize('viewAny', Task::class);

        return view('tasks::index');
    }

    public function create(Project $project): View
    {
        $this->authorize('create', [Task::class, $project]);

        return view('tasks::create', $this->queries->createPage($project));
    }

    public function store(CreateTaskRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('create', [Task::class, $project]);
        $task = $this->taskService->create($request->user(), $project, CreateTaskData::fromArray($request->validated()));

        return redirect()->route('tasks.show', $task)->with('success', 'Task created successfully.');
    }

    public function show(Task $task): View
    {
        $this->authorize('view', $task);
        $canViewActivity = request()->user()->can('viewAny', Activity::class);

        return view('tasks::show', $this->queries->detailPage(request()->user(), $task, $canViewActivity));
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);

        return view('tasks::edit', $this->queries->editPage($task));
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->taskService->update($task, UpdateTaskData::fromArray($request->validated()), $request->user());

        return redirect()->route('tasks.show', $task)->with('success', 'Task updated successfully.');
    }

    public function syncLabels(SyncTaskLabelsRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->taskService->syncLabels($task, SyncTaskLabelsData::fromArray($request->validated())->labelIds, $request->user());

        return redirect()->route('tasks.show', $task)->with('success', 'Task labels updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);
        $this->taskService->delete($task, request()->user());

        return redirect()->route('tasks.index')->with('success', 'Task deleted successfully.');
    }

    public function assign(AssignTaskRequest $request, Task $task): RedirectResponse
    {
        $assigneeId = $request->filled('assignee_id') ? $request->integer('assignee_id') : null;
        $this->authorize('assign', [$task, $assigneeId]);
        $this->assignments->assign($task, new AssignTaskData($assigneeId), $request->user());

        return back()->with('success', 'Task assignment updated.');
    }

    public function changeStatus(ChangeTaskStatusRequest $request, Task $task): RedirectResponse|Response
    {
        $this->authorize('changeStatus', $task);
        $this->statuses->change($task, ChangeTaskStatusData::fromArray($request->validated()), $request->user());

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('success', 'Task status updated.');
    }

    public function reorder(ReorderTaskRequest $request, Task $task): RedirectResponse|Response
    {
        $this->authorize('reorder', $task);
        $this->ranks->reorder($task, ReorderTaskData::fromArray($request->validated()), $request->user());

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('success', 'Task reordered.');
    }
}
