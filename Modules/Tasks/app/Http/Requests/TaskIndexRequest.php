<?php

namespace Modules\Tasks\Http\Requests;

class TaskIndexRequest extends TaskFilterRequest
{
    protected function searchParameter(): string
    {
        return 'q';
    }

    protected function allowsProjectFilter(): bool
    {
        return true;
    }
}
