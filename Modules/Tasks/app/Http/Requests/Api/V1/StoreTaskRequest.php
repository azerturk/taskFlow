<?php

namespace Modules\Tasks\Http\Requests\Api\V1;

use Modules\Tasks\Http\Requests\CreateTaskRequest;

class StoreTaskRequest extends CreateTaskRequest
{
    public function rules(): array
    {
        return ['project_id' => ['required', 'integer', 'min:1'], ...parent::rules()];
    }
}
