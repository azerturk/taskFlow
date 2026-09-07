<?php

namespace Modules\Tasks\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Tasks\Http\Requests\CreateTaskCommentRequest;
use Modules\Tasks\Http\Resources\TaskCommentResource;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskComment;
use Modules\Tasks\Services\TaskCommentService;

class TaskCommentController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskCommentService $comments) {}

    public function index(Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        return TaskCommentResource::collection($this->comments->commentsFor($task));
    }

    public function store(CreateTaskCommentRequest $request, Task $task): JsonResponse
    {
        $this->authorize('comment', $task);

        $comment = $this->comments->create(
            $task,
            $request->user(),
            $request->string('body')->toString(),
        );

        return (new TaskCommentResource($comment))->response()->setStatusCode(201);
    }

    public function destroy(Task $task, TaskComment $comment): JsonResponse
    {
        abort_unless($comment->task_id === $task->id, 404);
        $this->authorize('deleteComment', [$task, $comment]);
        $this->comments->delete($comment, request()->user());

        return response()->json(null, 204);
    }
}
