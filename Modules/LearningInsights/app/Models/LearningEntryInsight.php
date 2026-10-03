<?php

namespace Modules\LearningInsights\Models;

use Illuminate\Database\Eloquent\Model;

class LearningEntryInsight extends Model
{
    protected $table = 'r1_learning_insight_entries';

    protected $fillable = [
        'entry_id',
        'source_event_id',
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
