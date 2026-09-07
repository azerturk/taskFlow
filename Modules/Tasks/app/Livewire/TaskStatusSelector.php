<?php

namespace Modules\Tasks\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Tasks\Data\ChangeTaskStatusData;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Exceptions\InvalidTaskStatusTransition;
use Modules\Tasks\Exceptions\TaskVersionConflict;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskStatusService;

class TaskStatusSelector extends Component
{
    #[Locked]
    public int $taskId;

    public int $expectedVersion;

    public string $status = '';

    public ?string $success = null;

    public function mount(Task $task): void
    {
        $this->authorize('view', $task);
        $this->taskId = $task->id;
        $this->expectedVersion = $task->version;
    }

    public function change(TaskStatusService $statuses): void
    {
        $task = $statuses->current($this->taskId);
        $this->authorize('changeStatus', $task);
        $this->validate();

        try {
            $task = $statuses->change($task, new ChangeTaskStatusData(TaskStatus::from($this->status), $this->expectedVersion), auth()->user());
        } catch (TaskVersionConflict) {
            $this->expectedVersion = $statuses->current($this->taskId)->version;
            $this->addError('status', 'This task changed. Refresh and try again.');

            return;
        } catch (InvalidTaskStatusTransition) {
            $this->addError('status', 'That status transition is not allowed.');

            return;
        }

        $this->expectedVersion = $task->version;
        $this->status = '';
        $this->success = 'Task status updated.';
        $this->resetValidation();
    }

    public function render(TaskStatusService $statuses)
    {
        $task = $statuses->current($this->taskId);
        $this->authorize('view', $task);

        return view('tasks::livewire.task-status-selector', [
            'task' => $task,
            'availableStatuses' => $statuses->availableStatuses($task, auth()->user()),
        ]);
    }

    protected function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'expectedVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
