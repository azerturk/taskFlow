<?php

namespace Modules\Tasks\Http\Requests;

class TaskReadModelRequest extends TaskFilterRequest
{
    protected function searchParameter(): string
    {
        return 'q';
    }
}
