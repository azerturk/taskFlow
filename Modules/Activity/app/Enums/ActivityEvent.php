<?php

namespace Modules\Activity\Enums;

enum ActivityEvent: string
{
    case ProjectCreated = 'project.created';
    case ProjectUpdated = 'project.updated';
    case ProjectStatusChanged = 'project.status_changed';
    case ProjectMemberAdded = 'project.member_added';
    case ProjectMemberRoleUpdated = 'project.member_role_updated';
    case ProjectMemberRemoved = 'project.member_removed';
    case TaskCreated = 'task.created';
    case TaskUpdated = 'task.updated';
    case TaskDeleted = 'task.deleted';
    case TaskAssigned = 'task.assigned';
    case TaskStatusChanged = 'task.status_changed';
    case TaskReordered = 'task.reordered';
    case TaskLabelsUpdated = 'task.labels_updated';
    case CommentCreated = 'comment.created';
    case CommentDeleted = 'comment.deleted';
    case AttachmentUploaded = 'attachment.uploaded';
    case AttachmentDeleted = 'attachment.deleted';
    case LabelCreated = 'label.created';
    case LabelUpdated = 'label.updated';
    case LabelDeleted = 'label.deleted';
    case WatcherAdded = 'watcher.added';
    case WatcherRemoved = 'watcher.removed';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserSuspended = 'user.suspended';
    case UserReactivated = 'user.reactivated';
    case UserPasswordReset = 'user.password_reset';
    case UserPasswordChanged = 'user.password_changed';
    case ApiTokenIssued = 'api_token.issued';
    case ApiTokenRevoked = 'api_token.revoked';
}
