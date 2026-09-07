<?php

namespace Modules\Media\Models;

use App\Models\User;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return MediaFactory::new();
    }

    protected $fillable = [
        'uuid',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'extension',
        'mime_type',
        'size',
        'sha256',
        'image_width',
        'image_height',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'image_width' => 'integer',
            'image_height' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
