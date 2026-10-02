<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Media\Models\Media;

/** @extends Factory<Media> */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => "media/{$uuid}.pdf",
            'original_name' => 'document.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'sha256' => hash('sha256', $uuid),
            'image_width' => null,
            'image_height' => null,
        ];
    }
}
