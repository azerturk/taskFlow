<?php

namespace Modules\Tasks\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Modules\Tasks\Http\Requests\CreateTaskCommentRequest;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskComment;
use Modules\Tasks\Services\TaskCommentService;

class TaskCommentController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskCommentService $comments) {}

    public function store(CreateTaskCommentRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('comment', $task);
        $this->comments->create($task, $request->user(), $request->string('body')->toString());

        return back()->with('success', 'Comment added.');
    }

    public function destroy(Task $task, TaskComment $comment): RedirectResponse
    {
        abort_unless($comment->task_id === $task->id, 404);
        $this->authorize('deleteComment', [$task, $comment]);
        $this->comments->delete($comment, request()->user());

        return back()->with('success', 'Comment deleted.');
    }
}
