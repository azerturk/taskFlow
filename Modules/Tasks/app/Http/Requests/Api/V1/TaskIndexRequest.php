<?php

namespace Modules\Tasks\Http\Requests\Api\V1;

use Modules\Tasks\Http\Requests\TaskFilterRequest;

class TaskIndexRequest extends TaskFilterRequest
{
    protected function searchParameter(): string
    {
        return 'search';
    }

    protected function allowsProjectFilter(): bool
    {
        return true;
    }
}
