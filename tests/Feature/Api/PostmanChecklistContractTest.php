<?php

use Illuminate\Support\Facades\Route;

test('the API reference route manifest matches the verified runtime contract and contains no token sample', function (): void {
    $checklist = file_get_contents(base_path('docs-ibp/technical/API.md'));
    preg_match_all('/^\| (GET|POST|PUT|PATCH|DELETE) \| `([^`]+)` \| ([^|]+) \|/m', $checklist, $matches, PREG_SET_ORDER);

    $documented = collect($matches)
        ->map(fn (array $match) => [$match[1], 'api/v1'.trim($match[2]), trim($match[3]) === '—' ? null : trim($match[3])])
        ->sort()
        ->values()
        ->all();
    $runtime = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
        ->map(fn ($route) => [
            collect($route->methods())->reject(fn ($method) => $method === 'HEAD')->sole(),
            $route->uri(),
            (($middleware = collect($route->gatherMiddleware())->first(fn ($middleware) => str_starts_with($middleware, 'abilities:'))) === null) ? null : substr($middleware, 10),
        ])
        ->sort()
        ->values()
        ->all();

    expect($documented)->toBe($runtime)
        ->and($checklist)->not->toMatch('/Bearer\\s+[A-Za-z0-9._-]{20,}/')
        ->and($checklist)->not->toMatch('/sk-[A-Za-z0-9]{20,}/');
});
