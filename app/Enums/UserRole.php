<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case ProjectManager = 'project_manager';
    case Member = 'member';
}
