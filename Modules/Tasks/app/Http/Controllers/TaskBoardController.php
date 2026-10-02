<?php

namespace Modules\Tasks\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Modules\Projects\Models\Project;
use Modules\Tasks\Http\Requests\TaskReadModelRequest;
use Modules\Tasks\Services\TaskBoardQueryService;

class TaskBoardController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskBoardQueryService $board) {}

    public function show(TaskReadModelRequest $request, Project $project): View
    {
        $this->authorize('view', $project);
        $page = $this->board->page($project, $request->user(), $request->filters('rank'));

        return view('tasks::board', [
            'project' => $project,
            ...$page,
            'query' => $request->validated('q', ''),
        ]);
    }
}
