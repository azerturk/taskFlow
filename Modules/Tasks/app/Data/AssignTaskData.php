<?php

namespace Modules\Tasks\Data;

final readonly class AssignTaskData
{
    public function __construct(public ?int $assigneeId) {}
}
