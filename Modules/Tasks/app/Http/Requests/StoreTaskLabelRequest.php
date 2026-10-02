<?php

namespace Modules\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tasks\Enums\TaskLabelColor;

class StoreTaskLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name')), 'color' => strtoupper((string) $this->input('color'))]);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80', 'regex:/.*\\S.*/u'], 'color' => ['required', Rule::enum(TaskLabelColor::class)]];
    }
}
