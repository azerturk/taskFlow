<?php

namespace Modules\Projects\Data;

use Modules\Projects\Enums\ProjectMemberRole;

readonly class UpdateProjectMemberData
{
    public function __construct(public ProjectMemberRole $role) {}
}
