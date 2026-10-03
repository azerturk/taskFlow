<?php

namespace Modules\LearningCatalog\Models;

use Illuminate\Database\Eloquent\Model;

class LearningEntry extends Model
{
    protected $table = 'r1_learning_entries';

    protected $fillable = [
        'title',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
        ];
    }
}
