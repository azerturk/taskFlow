<?php

namespace Modules\Projects\Models;

use App\Models\User;
use Database\Factories\ProjectMemberFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Projects\Enums\ProjectMemberRole;

class ProjectMember extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return ProjectMemberFactory::new();
    }

    protected $fillable = [
        'project_id',
        'user_id',
        'member_role',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'member_role' => ProjectMemberRole::class,
            'joined_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
