<?php

namespace Modules\Tasks\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskAttachmentMediaBackfill
{
    /**
     * @return array{
     *     attachment_count: int,
     *     media_count: int,
     *     null_media_associations: int,
     *     duplicate_attachment_paths: int,
     *     duplicate_media_paths: int,
     *     duplicate_media_associations: int,
     *     missing_media_records: int,
     *     uploader_mismatches: int,
     *     size_mismatches: int,
     *     unavailable_files: int
     * }
     */
    public static function preflight(): array
    {
        $hasLegacyColumns = Schema::hasColumn('task_attachments', 'path');
        $attachments = DB::table('task_attachments')->orderBy('id')->get();
        $media = DB::table('media')->orderBy('id')->get();
        $mediaById = $media->keyBy('id');
        $mediaByPath = $media->keyBy('path');
        $effectiveMediaIds = $attachments->map(function (object $attachment) use ($hasLegacyColumns, $mediaByPath): ?int {
            if ($attachment->media_id !== null) {
                return (int) $attachment->media_id;
            }

            if (! $hasLegacyColumns) {
                return null;
            }

            $matched = $mediaByPath->get($attachment->path);

            return $matched === null ? null : (int) $matched->id;
        })->filter();

        $unavailableFiles = 0;
        $storageTargets = $media->map(fn (object $item): array => ['disk' => $item->disk, 'path' => $item->path]);
        if ($hasLegacyColumns) {
            $storageTargets = $storageTargets->concat(
                $attachments->whereNull('media_id')->map(fn (object $item): array => ['disk' => $item->disk, 'path' => $item->path]),
            );
        }

        foreach ($storageTargets->unique(fn (array $item): string => $item['disk'].'|'.$item['path']) as $item) {
            try {
                if (! Storage::disk($item['disk'])->exists($item['path'])) {
                    $unavailableFiles++;
                }
            } catch (\Throwable) {
                $unavailableFiles++;
            }
        }

        return [
            'attachment_count' => $attachments->count(),
            'media_count' => $media->count(),
            'null_media_associations' => $attachments->whereNull('media_id')->count(),
            'duplicate_attachment_paths' => $hasLegacyColumns
                ? $attachments->pluck('path')->filter()->duplicates()->unique()->count()
                : 0,
            'duplicate_media_paths' => $media->pluck('path')->filter()->duplicates()->unique()->count(),
            'duplicate_media_associations' => $effectiveMediaIds->duplicates()->unique()->count(),
            'missing_media_records' => $attachments
                ->filter(fn (object $attachment): bool => $attachment->media_id !== null && ! $mediaById->has($attachment->media_id))
                ->count(),
            'uploader_mismatches' => $hasLegacyColumns
                ? $attachments->filter(function (object $attachment) use ($mediaById, $mediaByPath): bool {
                    $item = $attachment->media_id === null
                        ? $mediaByPath->get($attachment->path)
                        : $mediaById->get($attachment->media_id);

                    return $item !== null && (int) $attachment->uploaded_by !== (int) $item->uploaded_by;
                })->count()
                : 0,
            'size_mismatches' => $hasLegacyColumns
                ? $attachments->filter(function (object $attachment) use ($mediaById, $mediaByPath): bool {
                    $item = $attachment->media_id === null
                        ? $mediaByPath->get($attachment->path)
                        : $mediaById->get($attachment->media_id);

                    return $item !== null && (int) $attachment->size !== (int) $item->size;
                })->count()
                : 0,
            'unavailable_files' => $unavailableFiles,
        ];
    }

    public static function run(): void
    {
        if (! Schema::hasColumn('task_attachments', 'path')) {
            return;
        }

        DB::table('task_attachments')->whereNull('media_id')->orderBy('id')->each(function (object $attachment): void {
            $existing = DB::table('media')->where('path', $attachment->path)->first();
            $mediaId = $existing?->id;

            if ($mediaId === null) {
                $mediaId = DB::table('media')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'uploaded_by' => $attachment->uploaded_by,
                    'disk' => $attachment->disk,
                    'path' => $attachment->path,
                    'original_name' => $attachment->original_name,
                    'extension' => strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) ?: 'bin',
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'sha256' => self::checksumFor($attachment->disk, $attachment->path),
                    'created_at' => $attachment->created_at,
                    'updated_at' => $attachment->updated_at,
                ]);
            }

            DB::table('task_attachments')->where('id', $attachment->id)->update(['media_id' => $mediaId]);
        });
    }

    public static function assertAssociationIntegrity(): void
    {
        $report = self::preflight();

        if ($report['null_media_associations'] > 0
            || $report['duplicate_media_associations'] > 0
            || $report['missing_media_records'] > 0) {
            throw new \RuntimeException('Task attachment Media associations could not be reconciled safely.');
        }
    }

    private static function checksumFor(string $disk, string $path): string
    {
        try {
            if (Storage::disk($disk)->exists($path)) {
                return hash('sha256', Storage::disk($disk)->get($path));
            }
        } catch (\Throwable) {
            // A missing preserved legacy file keeps a deterministic marker for later reconciliation.
        }

        return hash('sha256', 'missing:'.$disk.'|'.$path);
    }
}
