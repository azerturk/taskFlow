<?php

namespace Modules\Tasks\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tasks\Models\TaskAttachment;

/** @mixin TaskAttachment */
class TaskAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = $this->resource->relationLoaded('media') ? $this->media : null;
        $uploader = $media?->relationLoaded('uploader') ? $media->uploader : null;

        return [
            'id' => $this->id,
            'media_uuid' => $media?->uuid,
            'original_name' => $media?->original_name,
            'mime_type' => $media?->mime_type,
            'size' => $media?->size,
            'uploaded_by' => $uploader === null ? null : [
                'id' => $uploader->id,
                'name' => $uploader->name,
            ],
            'preview_url' => route('api.v1.tasks.media.preview', [$this->task_id, $this->id]),
            'download_url' => route('api.v1.tasks.media.download', [$this->task_id, $this->id]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
