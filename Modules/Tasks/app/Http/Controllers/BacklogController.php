<?php

namespace Modules\Tasks\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Modules\Projects\Models\Project;
use Modules\Tasks\Http\Requests\TaskReadModelRequest;
use Modules\Tasks\Services\BacklogQueryService;

class BacklogController
{
    use AuthorizesRequests;

    public function __construct(private readonly BacklogQueryService $backlog) {}

    public function show(TaskReadModelRequest $request, Project $project): View
    {
        $this->authorize('view', $project);
        $page = $this->backlog->page(
            $project,
            $request->user(),
            $request->filters('rank'),
            $request->integer('per_page', 25),
        );

        return view('tasks::backlog', [
            'project' => $project,
            ...$page,
            'query' => $request->validated('q', ''),
        ]);
    }
}
