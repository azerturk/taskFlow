<?php

namespace Modules\Activity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Activity\Data\ActivityFiltersData;
use Modules\Activity\Enums\ActivityEvent;

abstract class ActivityFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $identifiers = array_values($this->identifierParameters());

        return [
            'event' => ['nullable', Rule::enum(ActivityEvent::class)],
            $identifiers[0] => ['nullable', 'integer', 'min:1'],
            $identifiers[1] => ['nullable', 'integer', 'min:1'],
            $identifiers[2] => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(array $overrides = []): ActivityFiltersData
    {
        $validated = $this->validated();
        $canonical = [
            'event' => $validated['event'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ];

        foreach ($this->identifierParameters() as $canonicalName => $parameter) {
            $canonical[$canonicalName] = $validated[$parameter] ?? null;
        }

        return ActivityFiltersData::fromArray([...$canonical, ...$overrides]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query()) as $key) {
                if (! array_key_exists($key, $this->rules())) {
                    $validator->errors()->add($key, 'This filter is not supported.');
                }
            }
        });
    }

    /** @return array{project_id: string, task_id: string, actor_id: string} */
    abstract protected function identifierParameters(): array;
}
