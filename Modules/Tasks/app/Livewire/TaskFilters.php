<?php

namespace Modules\Tasks\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Tasks\Data\TaskFiltersData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Enums\TaskType;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskQueryService;

class TaskFilters extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: [])]
    public array $statuses = [];

    #[Url(except: [])]
    public array $types = [];

    #[Url(except: [])]
    public array $priorities = [];

    #[Url(as: 'project_id', except: null)]
    public ?int $projectId = null;

    #[Url(as: 'assignee_id', except: null)]
    public int|string|null $assigneeId = null;

    #[Url(as: 'reporter_id', except: null)]
    public ?int $reporterId = null;

    #[Url(as: 'label_ids', except: [])]
    public array $labelIds = [];

    #[Url(as: 'parent_id', except: null)]
    public ?int $parentId = null;

    #[Url(as: 'due_before', except: null)]
    public ?string $dueBefore = null;

    #[Url(as: 'due_after', except: null)]
    public ?string $dueAfter = null;

    #[Url(except: false)]
    public bool $overdue = false;

    #[Url(except: '-created_at')]
    public string $sort = '-created_at';

    public function mount(): void
    {
        $this->authorize('viewAny', Task::class);
        $this->normalizeState();
    }

    public function updated(string $property): void
    {
        if ($property !== 'paginators.page') {
            $this->resetPage();
        }
    }

    public function apply(): void
    {
        $this->validate();
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->reset('q', 'statuses', 'types', 'priorities', 'projectId', 'assigneeId', 'reporterId', 'labelIds', 'parentId', 'dueBefore', 'dueAfter', 'overdue');
        $this->sort = '-created_at';
        $this->resetValidation();
        $this->resetPage();
    }

    public function render(TaskQueryService $queries)
    {
        $this->authorize('viewAny', Task::class);
        $actor = auth()->user();
        $options = $queries->filterOptionsFor($actor);

        try {
            $this->validate();
            $filters = TaskFiltersData::fromArray($this->filterPayload());
        } catch (ValidationException) {
            $filters = TaskFiltersData::fromArray([]);
        }

        return view('tasks::livewire.task-filters', [
            'tasks' => $queries->paginateFor($actor, $filters),
            'statusOptions' => TaskStatus::cases(),
            'typeOptions' => TaskType::cases(),
            'priorityOptions' => TaskPriority::cases(),
            ...$options,
        ]);
    }

    protected function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:180'],
            'statuses' => ['array'], 'statuses.*' => [Rule::enum(TaskStatus::class)],
            'types' => ['array'], 'types.*' => [Rule::enum(TaskType::class)],
            'priorities' => ['array'], 'priorities.*' => [Rule::enum(TaskPriority::class)],
            'projectId' => ['nullable', 'integer'],
            'assigneeId' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== 'unassigned' && (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1)) {
                    $fail('The assignee id must be an integer or unassigned.');
                }
            }],
            'reporterId' => ['nullable', 'integer'],
            'labelIds' => ['array'], 'labelIds.*' => ['integer'], 'parentId' => ['nullable', 'integer'],
            'dueBefore' => ['nullable', 'date', 'after_or_equal:dueAfter'], 'dueAfter' => ['nullable', 'date'], 'overdue' => ['boolean'],
            'sort' => [Rule::in(['number', '-number', 'created_at', '-created_at', 'updated_at', '-updated_at', 'due_at', '-due_at', 'priority', '-priority', 'status', '-status', 'rank', '-rank'])],
        ];
    }

    private function filterPayload(): array
    {
        return [
            'search' => $this->q,
            'statuses' => $this->statuses,
            'types' => $this->types,
            'priorities' => $this->priorities,
            'project_id' => $this->projectId,
            'assignee_id' => $this->assigneeId,
            'reporter_id' => $this->reporterId,
            'label_ids' => $this->labelIds,
            'parent_id' => $this->parentId,
            'due_before' => $this->dueBefore,
            'due_after' => $this->dueAfter,
            'overdue' => $this->overdue,
            'sort' => $this->sort,
        ];
    }

    private function normalizeState(): void
    {
        $this->statuses = array_values(array_intersect($this->statuses, array_map(fn (TaskStatus $status): string => $status->value, TaskStatus::cases())));
        $this->types = array_values(array_intersect($this->types, array_map(fn (TaskType $type): string => $type->value, TaskType::cases())));
        $this->priorities = array_values(array_intersect($this->priorities, array_map(fn (TaskPriority $priority): string => $priority->value, TaskPriority::cases())));
        if ($this->assigneeId !== null && $this->assigneeId !== 'unassigned') {
            $this->assigneeId = filter_var($this->assigneeId, FILTER_VALIDATE_INT) !== false && (int) $this->assigneeId > 0
                ? (int) $this->assigneeId
                : null;
        }
        $this->labelIds = array_values(array_filter(array_map('intval', $this->labelIds)));
        $this->sort = in_array($this->sort, ['number', '-number', 'created_at', '-created_at', 'updated_at', '-updated_at', 'due_at', '-due_at', 'priority', '-priority', 'status', '-status', 'rank', '-rank'], true) ? $this->sort : '-created_at';
    }
}
