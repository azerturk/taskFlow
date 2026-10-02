<?php

namespace Modules\Projects\Data;

use Modules\Projects\Enums\ProjectStatus;

final readonly class ChangeProjectStatusData
{
    public function __construct(public ProjectStatus $status) {}
}
