<?php

namespace Modules\Tasks\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Modules\Tasks\Http\Requests\ManageTaskWatcherRequest;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskWatcherService;

class TaskWatcherController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskWatcherService $watchers) {}

    public function store(ManageTaskWatcherRequest $request, Task $task): RedirectResponse
    {
        $userId = $request->filled('user_id') ? $request->integer('user_id') : $request->user()->id;
        $this->authorize('watch', [$task, $userId]);
        $this->watchers->watchById($task, $userId, $request->user());

        return back()->with('success', 'Watcher added.');
    }

    public function destroy(ManageTaskWatcherRequest $request, Task $task, string $user): RedirectResponse
    {
        $userId = (int) $user;
        $this->authorize('watch', [$task, $userId]);
        $this->watchers->unwatchById($task, $userId, $request->user());

        return back()->with('success', 'Watcher removed.');
    }
}
