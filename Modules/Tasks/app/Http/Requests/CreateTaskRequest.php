<?php

namespace Modules\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskType;

class CreateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'min:3', 'max:180'], 'description' => ['nullable', 'string', 'max:10000'], 'assignee_id' => ['nullable', 'integer', 'min:1'], 'priority' => ['required', Rule::enum(TaskPriority::class)], 'due_at' => ['nullable', 'date'], 'type' => ['nullable', Rule::enum(TaskType::class)], 'parent_id' => ['nullable', 'integer', 'min:1'], 'label_ids' => ['nullable', 'array'], 'label_ids.*' => ['integer', 'distinct', 'min:1']];
    }
}
