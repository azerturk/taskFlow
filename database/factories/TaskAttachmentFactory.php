<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Media\Models\Media;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;

/** @extends Factory<TaskAttachment> */
class TaskAttachmentFactory extends Factory
{
    protected $model = TaskAttachment::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'media_id' => Media::factory(),
        ];
    }
}
