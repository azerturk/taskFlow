<?php

namespace Modules\Activity\Support;

use BackedEnum;
use DateTimeInterface;

final class ActivitySanitizer
{
    private const MAX_ARRAY_ITEMS = 100;

    private const MAX_DEPTH = 8;

    private const MAX_STRING_BYTES = 500;

    /** @var list<string> */
    private const SENSITIVE_KEY_PARTS = [
        'password', 'token', 'secret', 'authorization', 'cookie', 'hash',
        'credential', 'path', 'checksum', 'content', 'body', 'description',
        'disk', 'payload', 'notification', 'signed_url', 'temporary',
    ];

    /** @param array<array-key, mixed> $properties @return array<array-key, mixed> */
    public function sanitize(array $properties): array
    {
        return $this->sanitizeArray($properties, 0);
    }

    /** @param array<array-key, mixed> $properties @return array<array-key, mixed> */
    private function sanitizeArray(array $properties, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return [];
        }

        $safe = [];
        foreach (array_slice($properties, 0, self::MAX_ARRAY_ITEMS, true) as $key => $value) {
            if ($this->isSensitiveKey((string) $key, $value)) {
                continue;
            }

            [$accepted, $sanitized] = $this->sanitizeValue($value, $depth + 1);
            if ($accepted) {
                $safe[$key] = $sanitized;
            }
        }

        return $safe;
    }

    /** @return array{bool, mixed} */
    private function sanitizeValue(mixed $value, int $depth): array
    {
        if (is_array($value)) {
            return [true, $this->sanitizeArray($value, $depth)];
        }

        if ($value instanceof BackedEnum) {
            return [true, $value->value];
        }

        if ($value instanceof DateTimeInterface) {
            return [true, $value->format(DATE_ATOM)];
        }

        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
                return [false, null];
            }

            return [true, mb_strcut($value, 0, self::MAX_STRING_BYTES, 'UTF-8')];
        }

        return [is_int($value) || is_float($value) || is_bool($value) || $value === null, $value];
    }

    private function isSensitiveKey(string $key, mixed $value): bool
    {
        $normalized = strtolower($key);
        if ((str_ends_with($normalized, '_changed') && is_bool($value))
            || (str_ends_with($normalized, '_count') && is_int($value))) {
            return false;
        }

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}
