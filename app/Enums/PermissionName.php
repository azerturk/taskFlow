<?php

namespace App\Enums;

enum PermissionName: string
{
    case UserRolesManage = 'users.roles.manage';
    case ApiTokensManage = 'api_tokens.manage';
    case ProjectsView = 'projects.view';
    case ProjectsCreate = 'projects.create';
    case ProjectsUpdate = 'projects.update';
    case ProjectsArchive = 'projects.archive';
    case ProjectsMembersManage = 'projects.members.manage';
    case TasksView = 'tasks.view';
    case TasksCreate = 'tasks.create';
    case TasksUpdate = 'tasks.update';
    case TasksAssign = 'tasks.assign';
    case TasksStatusChange = 'tasks.status.change';
    case TasksDelete = 'tasks.delete';
    case CommentsCreate = 'comments.create';
    case CommentsDelete = 'comments.delete';
    case AttachmentsUpload = 'attachments.upload';
    case AttachmentsDelete = 'attachments.delete';
    case ActivityView = 'activity.view';
    case DashboardView = 'dashboard.view';
}
