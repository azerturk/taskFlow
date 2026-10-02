<?php

namespace Modules\Tasks\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Exceptions\InvalidTaskComment;
use Modules\Tasks\Exceptions\TaskMutationNotAllowed;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskComment;
use Modules\Tasks\Repositories\Contracts\TaskCommentRepositoryInterface;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;

class TaskCommentService
{
    public function __construct(
        private readonly TaskCommentRepositoryInterface $comments,
        private readonly ActivityRecorder $activity,
        private readonly TaskWatcherNotificationService $notifications,
        private readonly ProjectMemberService $members,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    public function currentTask(int $taskId): Task
    {
        return $this->tasks->findOrFail($taskId);
    }

    public function commentsFor(Task $task)
    {
        return $this->comments->forTask($task);
    }

    public function create(Task $task, User $actor, string $body): TaskComment
    {
        $task = $this->tasks->withProject($task);
        $this->ensureActorCanComment($task, $actor);
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > 5000) {
            throw new InvalidTaskComment('Comments must contain at most 5,000 non-whitespace characters.');
        }

        return DB::transaction(function () use ($task, $actor, $body) {
            $comment = $this->comments->save(new TaskComment(['task_id' => $task->id, 'user_id' => $actor->id, 'body' => $body]));
            $this->activity->record(ActivityEvent::CommentCreated, $actor, $comment, ['project_id' => $task->project_id, 'task_id' => $task->id, 'comment_id' => $comment->id]);
            $this->notifications->notify($task, $actor, ActivityEvent::CommentCreated);

            return $this->comments->prepare($comment);
        });
    }

    public function delete(TaskComment $comment, User $actor): void
    {
        $comment = $this->comments->prepare($comment);
        $task = $comment->task;
        $this->ensureActorCanComment($task, $actor);

        if ($comment->user_id !== $actor->id && ! $this->members->canManage($task->project, $actor)) {
            throw new TaskMutationNotAllowed('Only the comment author or a project manager may delete a comment.');
        }

        DB::transaction(function () use ($comment, $actor) {
            $taskId = $comment->task_id;
            $projectId = $comment->task->project_id;
            $commentId = $comment->id;
            $this->comments->delete($comment);
            $this->activity->record(ActivityEvent::CommentDeleted, $actor, $comment, ['project_id' => $projectId, 'task_id' => $taskId, 'comment_id' => $commentId]);
        });
    }

    private function ensureActorCanComment(Task $task, User $actor): void
    {
        if ($task->project->status !== ProjectStatus::Active) {
            throw new TaskMutationNotAllowed('Only active projects accept comments.');
        }

        if (! $actor->isActive() || (! $this->members->canManage($task->project, $actor) && ! $this->members->isMember($task->project, $actor))) {
            throw new TaskMutationNotAllowed('Only active project members may comment.');
        }
    }
}
