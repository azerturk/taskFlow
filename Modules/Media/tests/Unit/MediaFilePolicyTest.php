<?php

use Modules\Media\Support\MediaFilePolicy;

test('media MIME and extension pairs use the central fixed allowlist', function (): void {
    expect(MediaFilePolicy::accepts('PDF', 'application/pdf'))->toBeTrue()
        ->and(MediaFilePolicy::accepts('jpg', 'image/jpeg'))->toBeTrue()
        ->and(MediaFilePolicy::accepts('md', 'text/plain'))->toBeTrue()
        ->and(MediaFilePolicy::accepts('svg', 'image/svg+xml'))->toBeFalse()
        ->and(MediaFilePolicy::accepts('jpg', 'text/plain'))->toBeFalse();
});

test('media filenames and preview disposition helpers are safe and deterministic', function (): void {
    expect(MediaFilePolicy::safeOriginalName("../unsafe\r\nInjected.pdf", 'pdf'))->toBe('unsafe')
        ->and(MediaFilePolicy::safeOriginalName("\0<>:", 'txt'))->toBe('download.txt')
        ->and(MediaFilePolicy::isPreviewable('image/png'))->toBeTrue()
        ->and(MediaFilePolicy::isPreviewable('application/pdf'))->toBeTrue()
        ->and(MediaFilePolicy::isPreviewable('text/plain'))->toBeFalse();
});
