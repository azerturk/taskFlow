<?php

use Modules\Activity\Support\ActivitySanitizer;

it('recursively removes sensitive and binary values while bounding retained data', function (): void {
    $long = str_repeat('a', 700);
    $many = array_fill(0, 110, 'safe');
    $safe = (new ActivitySanitizer)->sanitize([
        'task_id' => 42,
        'revoked_token_count' => 3,
        'password_confirmation' => 'secret',
        'nested' => [
            'authorization_header' => 'secret',
            'private_path' => '/hidden',
            'notification_payload' => ['safe' => 'not retained'],
            'description_changed' => true,
            'title' => $long,
            'binary' => "unsafe\0bytes",
            'many' => $many,
        ],
    ]);

    expect($safe['task_id'])->toBe(42)
        ->and($safe['revoked_token_count'])->toBe(3)
        ->and($safe)->not->toHaveKey('password_confirmation')
        ->and($safe['nested'])->not->toHaveKeys(['authorization_header', 'private_path', 'notification_payload', 'binary'])
        ->and($safe['nested']['description_changed'])->toBeTrue()
        ->and(strlen($safe['nested']['title']))->toBe(500)
        ->and($safe['nested']['many'])->toHaveCount(100);
});
