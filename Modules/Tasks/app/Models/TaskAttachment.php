<?php

namespace Modules\Tasks\Models;

use Database\Factories\TaskAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Media\Models\Media;

class TaskAttachment extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return TaskAttachmentFactory::new();
    }

    protected $fillable = ['task_id', 'media_id'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
