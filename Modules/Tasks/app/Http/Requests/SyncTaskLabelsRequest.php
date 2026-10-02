<?php

namespace Modules\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncTaskLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['label_ids' => ['required', 'array'], 'label_ids.*' => ['integer', 'distinct', 'min:1']];
    }
}
