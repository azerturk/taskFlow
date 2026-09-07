<?php

namespace Modules\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['before_task_id' => ['nullable', 'integer', 'different:after_task_id'], 'after_task_id' => ['nullable', 'integer'], 'expected_version' => ['required', 'integer', 'min:1']];
    }
}
