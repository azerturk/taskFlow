<?php

namespace Modules\Activity\Http\Requests\Api\V1;

use Modules\Activity\Http\Requests\ActivityFilterRequest;

class ActivityIndexRequest extends ActivityFilterRequest
{
    protected function identifierParameters(): array
    {
        return [
            'project_id' => 'project_id',
            'task_id' => 'task_id',
            'actor_id' => 'actor_id',
        ];
    }
}
