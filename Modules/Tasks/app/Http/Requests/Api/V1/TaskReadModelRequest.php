<?php

namespace Modules\Tasks\Http\Requests\Api\V1;

class TaskReadModelRequest extends TaskIndexRequest
{
    protected function allowsProjectFilter(): bool
    {
        return false;
    }
}
