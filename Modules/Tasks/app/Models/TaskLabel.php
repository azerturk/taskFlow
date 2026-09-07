<?php

namespace Modules\Tasks\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Projects\Models\Project;

class TaskLabel extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'name', 'slug', 'color'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
