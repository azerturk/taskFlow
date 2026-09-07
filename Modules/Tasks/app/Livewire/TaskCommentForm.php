<?php

namespace Modules\Tasks\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Tasks\Exceptions\InvalidTaskComment;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskCommentService;

class TaskCommentForm extends Component
{
    #[Locked]
    public int $taskId;

    public string $body = '';

    public ?string $success = null;

    public function mount(Task $task): void
    {
        $this->authorize('view', $task);
        $this->taskId = $task->id;
    }

    public function submit(TaskCommentService $comments): void
    {
        $task = $comments->currentTask($this->taskId);
        $this->authorize('comment', $task);
        $this->validate();

        try {
            $comments->create($task, auth()->user(), $this->body);
        } catch (InvalidTaskComment) {
            $this->addError('body', 'Comments must contain at most 5,000 non-whitespace characters.');

            return;
        }

        $this->body = '';
        $this->success = 'Comment added.';
        $this->resetValidation();
    }

    public function render(TaskCommentService $comments)
    {
        $task = $comments->currentTask($this->taskId);
        $this->authorize('view', $task);

        return view('tasks::livewire.task-comment-form', [
            'task' => $task,
            'comments' => $comments->commentsFor($task),
        ]);
    }

    protected function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000', 'not_regex:/^\s*$/u']];
    }
}
