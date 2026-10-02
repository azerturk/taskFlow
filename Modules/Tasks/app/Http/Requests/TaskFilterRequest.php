<?php

namespace Modules\Tasks\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Tasks\Data\TaskFiltersData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Enums\TaskType;

abstract class TaskFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            $this->searchParameter() => ['nullable', 'string', 'max:180'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => [Rule::enum(TaskStatus::class)],
            'types' => ['nullable', 'array'],
            'types.*' => [Rule::enum(TaskType::class)],
            'priorities' => ['nullable', 'array'],
            'priorities.*' => [Rule::enum(TaskPriority::class)],
            'assignee_id' => ['nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== 'unassigned' && (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1)) {
                    $fail('The assignee id must be an integer or unassigned.');
                }
            }],
            'reporter_id' => ['nullable', 'integer', 'min:1'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'min:1', 'distinct'],
            'parent_id' => ['nullable', 'integer', 'min:0'],
            'due_before' => ['nullable', 'date', 'after_or_equal:due_after'],
            'due_after' => ['nullable', 'date'],
            'overdue' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in($this->allowedSorts())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];

        if ($this->allowsProjectFilter()) {
            $rules['project_id'] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }

    public function filters(string $defaultSort = '-created_at'): TaskFiltersData
    {
        $validated = $this->validated();
        $searchParameter = $this->searchParameter();
        $validated['search'] = $validated[$searchParameter] ?? null;
        if ($searchParameter !== 'search') {
            unset($validated[$searchParameter]);
        }
        $validated['sort'] ??= $defaultSort;

        return TaskFiltersData::fromArray($validated);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = array_keys($this->rules());
            $allowed = array_values(array_filter($allowed, fn (string $key): bool => ! str_contains($key, '.*')));

            foreach (array_keys($this->query()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'This filter is not supported.');
                }
            }
        });
    }

    abstract protected function searchParameter(): string;

    protected function allowsProjectFilter(): bool
    {
        return false;
    }

    /** @return list<string> */
    private function allowedSorts(): array
    {
        return [
            'number', '-number', 'created_at', '-created_at', 'updated_at', '-updated_at',
            'due_at', '-due_at', 'priority', '-priority', 'status', '-status', 'rank', '-rank',
        ];
    }
}
