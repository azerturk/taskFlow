<?php

namespace Modules\Projects\Enums;

enum ProjectMemberRole: string
{
    case Manager = 'manager';
    case Member = 'member';
}
