<?php

namespace Modules\Dashboard\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Projects\Exceptions\ProjectReadOnly;
use Modules\Projects\Models\Project;
use Modules\Tasks\Data\CreateTaskData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskType;
use Modules\Tasks\Exceptions\InvalidAssignee;
use Modules\Tasks\Exceptions\LabelOutsideProject;
use Modules\Tasks\Exceptions\ParentTaskInvalid;
use Modules\Tasks\Exceptions\TaskMutationNotAllowed;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\QuickTaskCreateService;

class QuickTaskCreate extends Component
{
    #[Locked]
    public ?int $fixedProjectId = null;

    public ?int $projectId = null;

    public string $title = '';

    public string $type = 'task';

    public string $priority = 'medium';

    public ?int $assigneeId = null;

    public ?int $parentId = null;

    /** @var list<int> */
    public array $labelIds = [];

    public ?string $success = null;

    public function mount(?Project $project = null): void
    {
        if ($project === null) {
            return;
        }

        $this->authorize('create', [Task::class, $project]);
        $this->fixedProjectId = $project->id;
        $this->projectId = $project->id;
    }

    public function updatedProjectId(): void
    {
        if ($this->fixedProjectId !== null) {
            $this->projectId = $this->fixedProjectId;
        }

        $this->assigneeId = null;
        $this->parentId = null;
        $this->labelIds = [];
        $this->resetValidation(['projectId', 'assigneeId', 'parentId', 'labelIds']);
    }

    public function submit(QuickTaskCreateService $quickTasks): void
    {
        $this->validate();

        try {
            $project = $quickTasks->project(auth()->user(), (int) $this->projectId);
            $this->authorize('create', [Task::class, $project]);
            $task = $quickTasks->create(auth()->user(), $project, new CreateTaskData(
                $this->title,
                null,
                $this->assigneeId,
                TaskPriority::from($this->priority),
                null,
                TaskType::from($this->type),
                $this->parentId,
                $this->labelIds,
            ));
        } catch (InvalidAssignee) {
            $this->addError('assigneeId', 'Select an active member of this project.');

            return;
        } catch (ParentTaskInvalid) {
            $this->addError('parentId', 'Select a valid parent task from this project.');

            return;
        } catch (LabelOutsideProject) {
            $this->addError('labelIds', 'Every selected label must belong to this project.');

            return;
        } catch (ProjectReadOnly|TaskMutationNotAllowed) {
            $this->addError('projectId', 'Tasks cannot be created in the selected project.');

            return;
        }

        $this->reset(['title', 'type', 'priority', 'assigneeId', 'parentId', 'labelIds']);
        $this->type = TaskType::Task->value;
        $this->priority = TaskPriority::Medium->value;
        $this->projectId = $this->fixedProjectId;
        $this->success = "{$task->display_key} created in Backlog.";
        $this->resetValidation();
    }

    public function render(QuickTaskCreateService $quickTasks)
    {
        $projects = $quickTasks->projectsFor(auth()->user());
        $project = $this->projectId && $projects->contains('id', $this->projectId)
            ? $quickTasks->project(auth()->user(), $this->projectId)
            : null;

        return view('dashboard::livewire.quick-task-create', [
            'projects' => $projects,
            'project' => $project,
            'options' => $project ? $quickTasks->optionsFor($project) : ['memberships' => collect(), 'labels' => collect(), 'parents' => collect()],
            'priorities' => TaskPriority::cases(),
            'types' => TaskType::cases(),
        ]);
    }

    protected function rules(): array
    {
        return [
            'projectId' => ['required', 'integer'],
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'type' => ['required', Rule::enum(TaskType::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'assigneeId' => ['nullable', 'integer'],
            'parentId' => ['nullable', 'integer'],
            'labelIds' => ['array'],
            'labelIds.*' => ['integer', 'distinct'],
        ];
    }
}
