<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Tasks\Models\Task;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Task $task, private readonly User $actor) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'task.assigned',
            'project_id' => $this->task->project_id,
            'task_id' => $this->task->id,
            'task_number' => $this->task->number,
            'task_title' => $this->task->title,
            'actor_id' => $this->actor->id,
        ];
    }
}
