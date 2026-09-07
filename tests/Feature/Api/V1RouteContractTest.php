<?php

use Illuminate\Support\Facades\Route;

function v1RouteMatrix(): array
{
    return [
        ['POST', 'api/v1/auth/token', 'api.v1.auth.token.issue', null],
        ['GET', 'api/v1/me', 'api.v1.me.show', null], ['DELETE', 'api/v1/auth/token', 'api.v1.auth.token.destroy', null],
        ['GET', 'api/v1/projects', 'api.v1.projects.index', 'projects:read'], ['POST', 'api/v1/projects', 'api.v1.projects.store', 'projects:write'], ['GET', 'api/v1/projects/{project}', 'api.v1.projects.show', 'projects:read'], ['PUT', 'api/v1/projects/{project}', 'api.v1.projects.update', 'projects:write'], ['PATCH', 'api/v1/projects/{project}/status', 'api.v1.projects.status', 'projects:write'], ['GET', 'api/v1/projects/{project}/members', 'api.v1.projects.members.index', 'projects:read'], ['POST', 'api/v1/projects/{project}/members', 'api.v1.projects.members.store', 'projects:write'], ['PATCH', 'api/v1/projects/{project}/members/{user}', 'api.v1.projects.members.update', 'projects:write'], ['DELETE', 'api/v1/projects/{project}/members/{user}', 'api.v1.projects.members.destroy', 'projects:write'],
        ['GET', 'api/v1/tasks', 'api.v1.tasks.index', 'tasks:read'], ['POST', 'api/v1/tasks', 'api.v1.tasks.store', 'tasks:write'], ['GET', 'api/v1/tasks/{task}', 'api.v1.tasks.show', 'tasks:read'], ['PUT', 'api/v1/tasks/{task}', 'api.v1.tasks.update', 'tasks:write'], ['DELETE', 'api/v1/tasks/{task}', 'api.v1.tasks.destroy', 'tasks:write'], ['PATCH', 'api/v1/tasks/{task}/assignee', 'api.v1.tasks.assign', 'tasks:write'], ['PATCH', 'api/v1/tasks/{task}/status', 'api.v1.tasks.status', 'tasks:write'], ['PATCH', 'api/v1/tasks/{task}/rank', 'api.v1.tasks.reorder', 'tasks:write'], ['PUT', 'api/v1/tasks/{task}/labels', 'api.v1.tasks.labels.sync', 'tasks:write'],
        ['GET', 'api/v1/projects/{project}/backlog', 'api.v1.projects.backlog', 'tasks:read'], ['GET', 'api/v1/projects/{project}/board', 'api.v1.projects.board', 'tasks:read'],
        ['GET', 'api/v1/projects/{project}/labels', 'api.v1.projects.labels.index', 'tasks:read'], ['POST', 'api/v1/projects/{project}/labels', 'api.v1.projects.labels.store', 'tasks:write'], ['PATCH', 'api/v1/projects/{project}/labels/{label}', 'api.v1.projects.labels.update', 'tasks:write'], ['DELETE', 'api/v1/projects/{project}/labels/{label}', 'api.v1.projects.labels.destroy', 'tasks:write'],
        ['GET', 'api/v1/tasks/{task}/watchers', 'api.v1.tasks.watchers.index', 'tasks:read'], ['POST', 'api/v1/tasks/{task}/watchers', 'api.v1.tasks.watchers.store', 'tasks:write'], ['DELETE', 'api/v1/tasks/{task}/watchers/{user}', 'api.v1.tasks.watchers.destroy', 'tasks:write'],
        ['GET', 'api/v1/tasks/{task}/comments', 'api.v1.tasks.comments.index', 'tasks:read'], ['POST', 'api/v1/tasks/{task}/comments', 'api.v1.tasks.comments.store', 'comments:write'], ['DELETE', 'api/v1/tasks/{task}/comments/{comment}', 'api.v1.tasks.comments.destroy', 'comments:write'],
        ['GET', 'api/v1/tasks/{task}/media', 'api.v1.tasks.media.index', 'tasks:read'], ['POST', 'api/v1/tasks/{task}/media', 'api.v1.tasks.media.store', 'tasks:write'], ['GET', 'api/v1/tasks/{task}/media/{media}/preview', 'api.v1.tasks.media.preview', 'tasks:read'], ['GET', 'api/v1/tasks/{task}/media/{media}/download', 'api.v1.tasks.media.download', 'tasks:read'], ['DELETE', 'api/v1/tasks/{task}/media/{media}', 'api.v1.tasks.media.destroy', 'tasks:write'],
        ['GET', 'api/v1/activity', 'api.v1.activity.index', 'activity:read'], ['GET', 'api/v1/projects/{project}/activity', 'api.v1.projects.activity', 'activity:read'], ['GET', 'api/v1/tasks/{task}/activity', 'api.v1.tasks.activity', 'activity:read'],
        ['GET', 'api/v1/dashboard/summary', 'api.v1.dashboard.summary', 'dashboard:read'], ['GET', 'api/v1/dashboard/my-tasks', 'api.v1.dashboard.my-tasks', 'dashboard:read'], ['GET', 'api/v1/dashboard/reported', 'api.v1.dashboard.reported', 'dashboard:read'], ['GET', 'api/v1/dashboard/watched', 'api.v1.dashboard.watched', 'dashboard:read'], ['GET', 'api/v1/dashboard/overdue', 'api.v1.dashboard.overdue', 'dashboard:read'],
    ];
}

test('the v1 runtime route and ability matrix exactly matches the canonical contract', function (): void {
    $actual = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
        ->map(fn ($route) => [collect($route->methods())->reject(fn ($method) => $method === 'HEAD')->sole(), $route->uri(), $route->getName(), collect($route->gatherMiddleware())->first(fn ($middleware) => str_starts_with($middleware, 'abilities:'))])
        ->map(fn (array $route) => [$route[0], $route[1], $route[2], $route[3] ? substr($route[3], 10) : null])
        ->sort()
        ->values()
        ->all();
    $expected = collect(v1RouteMatrix())->sort()->values()->all();

    expect($actual)->toBe($expected);
});

test('every protected v1 endpoint rejects missing authentication', function (): void {
    foreach (v1RouteMatrix() as [$method, $uri, , $ability]) {
        if ($uri === 'api/v1/auth/token' && $method === 'POST') {
            continue;
        }

        $path = '/'.preg_replace('/\{[^}]+\}/', '999999', $uri);
        app('auth')->forgetGuards();
        $this->json($method, $path)->assertUnauthorized();

    }
});
