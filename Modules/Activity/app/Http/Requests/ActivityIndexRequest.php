<?php

namespace Modules\Activity\Http\Requests;

class ActivityIndexRequest extends ActivityFilterRequest
{
    protected function identifierParameters(): array
    {
        return [
            'project_id' => 'project',
            'task_id' => 'task',
            'actor_id' => 'actor',
        ];
    }
}
