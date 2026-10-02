<?php

namespace Modules\Media\Support;

final class MediaFilePolicy
{
    /** @var array<string, list<string>> */
    private const ALLOWED_TYPES = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'txt' => ['text/plain'],
        'log' => ['text/plain'],
        'md' => ['text/plain'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ];

    public static function accepts(string $extension, string $mimeType): bool
    {
        $extension = strtolower($extension);

        return isset(self::ALLOWED_TYPES[$extension])
            && in_array(strtolower($mimeType), self::ALLOWED_TYPES[$extension], true);
    }

    public static function safeOriginalName(string $name, string $extension): string
    {
        $name = preg_split('/[\r\n]/', $name)[0] ?? '';
        $name = basename(str_replace('\\', '/', str_replace("\0", '', $name)));
        $name = trim((string) preg_replace('/[^A-Za-z0-9._ -]/', '', $name));

        return $name !== '' ? $name : 'download.'.strtolower($extension);
    }

    public static function isPreviewable(string $mimeType): bool
    {
        return str_starts_with(strtolower($mimeType), 'image/') || strtolower($mimeType) === 'application/pdf';
    }
}
