<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Modules\Projects\Data\ChangeProjectStatusData;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Exceptions\InvalidProjectTransition;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Projects\Services\ProjectService;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Exceptions\InvalidAssignee;
use Modules\Tasks\Exceptions\InvalidTaskStatusTransition;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskComment;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function securedTaskContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    app(ProjectMemberService::class)->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    $task = Task::factory()->for($project)->for($manager, 'creator')->create();

    return [$manager, $member, $outsider, $project, $task];
}

test('ability denial precedes binding while admitted inaccessible and missing records share one safe 404 contract', function (): void {
    [, , $outsider, , $task] = securedTaskContext();

    Sanctum::actingAs($outsider, ['projects:read']);
    $this->getJson('/api/v1/tasks/'.$task->id)->assertForbidden();
    $this->getJson('/api/v1/tasks/987654')->assertForbidden();

    Sanctum::actingAs($outsider, ['tasks:read']);
    $hidden = $this->getJson('/api/v1/tasks/'.$task->id)->assertNotFound();
    $missing = $this->getJson('/api/v1/tasks/987654')->assertNotFound();

    expect($hidden->json())->toBe($missing->json())
        ->and($hidden->json('code'))->toBe('resource_not_found')
        ->and(json_encode($hidden->json()))->not->toContain((string) $task->id)
        ->not->toContain(Task::class);
});

test('wrong-parent removed-access and soft-deleted resources do not disclose record existence', function (): void {
    [$manager, $member, , $project, $task] = securedTaskContext();
    $otherTask = Task::factory()->for($project)->for($manager, 'creator')->create();
    $comment = TaskComment::factory()->for($task)->for($member)->create();

    Sanctum::actingAs($member, ['comments:write']);
    $wrongParent = $this->deleteJson('/api/v1/tasks/'.$otherTask->id.'/comments/'.$comment->id)->assertNotFound();
    $missingChild = $this->deleteJson('/api/v1/tasks/'.$otherTask->id.'/comments/987654')->assertNotFound();
    expect($wrongParent->json())->toBe($missingChild->json());

    DB::table('project_members')->where(['project_id' => $project->id, 'user_id' => $member->id])->delete();
    Sanctum::actingAs($member, ['tasks:read']);
    $removed = $this->getJson('/api/v1/tasks/'.$task->id)->assertNotFound();

    $task->delete();
    Sanctum::actingAs($manager, ['tasks:read']);
    $deleted = $this->getJson('/api/v1/tasks/'.$task->id)->assertNotFound();
    $random = $this->getJson('/api/v1/tasks/987654')->assertNotFound();

    expect($removed->json())->toBe($random->json())
        ->and($deleted->json())->toBe($random->json());
});

test('search and media uploads have independent deterministic named limits', function (): void {
    [$manager, , , , $task] = securedTaskContext();

    Sanctum::actingAs($manager, ['tasks:read', 'tasks:write']);
    foreach (range(1, 30) as $attempt) {
        $this->getJson('/api/v1/tasks?search=security')->assertOk();
    }
    $this->getJson('/api/v1/tasks?search=security')->assertTooManyRequests();

    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/v1/tasks/'.$task->id.'/media', [
            'media' => [UploadedFile::fake()->create('blocked.exe', 1, 'application/octet-stream')],
        ])->assertUnprocessable();
    }
    $this->postJson('/api/v1/tasks/'.$task->id.'/media', [
        'media' => [UploadedFile::fake()->create('blocked.exe', 1, 'application/octet-stream')],
    ])->assertTooManyRequests();
});

test('the normal API limiter remains independently enforceable', function (): void {
    $user = User::factory()->asMember()->create();
    Sanctum::actingAs($user, ['tasks:read']);

    foreach (range(1, 120) as $attempt) {
        $this->getJson('/api/v1/me')->assertOk();
    }

    $this->getJson('/api/v1/me')->assertTooManyRequests();
});

test('named domain conflicts have stable mappings and direct services remain framework neutral', function (): void {
    [$manager, , , $project, $task] = securedTaskContext();
    Sanctum::actingAs($manager, ['tasks:write', 'projects:write']);

    $this->patchJson('/api/v1/tasks/'.$task->id.'/status', [
        'status' => TaskStatus::Review->value,
        'expected_version' => $task->version,
    ])->assertConflict()
        ->assertJsonPath('code', 'invalid_task_status_transition')
        ->assertJsonPath('errors.status.0', 'Choose one of the allowed next statuses.');

    $this->patchJson('/api/v1/projects/'.$project->id.'/status', [
        'status' => ProjectStatus::Active->value,
    ])->assertConflict()
        ->assertJsonPath('code', 'invalid_project_transition');

    expect(fn () => app(ProjectService::class)->changeStatus(
        $project,
        new ChangeProjectStatusData(ProjectStatus::Draft),
        $manager,
    ))->toThrow(InvalidProjectTransition::class)
        ->and(InvalidProjectTransition::class)->toExtend(DomainException::class)
        ->and(InvalidAssignee::class)->toExtend(DomainException::class)
        ->and(InvalidTaskStatusTransition::class)->toExtend(DomainException::class);
});

test('named limits are attached only to their Web and API endpoint classes', function (): void {
    foreach (['login.store'] as $routeName) {
        expect(Route::getRoutes()->getByName($routeName)->gatherMiddleware())->toContain('throttle:taskflow-login');
    }

    foreach (['projects.index', 'tasks.index', 'projects.backlog', 'projects.board', 'api.v1.projects.index', 'api.v1.tasks.index', 'api.v1.projects.backlog', 'api.v1.projects.board'] as $routeName) {
        expect(Route::getRoutes()->getByName($routeName)->gatherMiddleware(), $routeName)->toContain('throttle:taskflow-search');
    }

    foreach (['tasks.media.store', 'api.v1.tasks.media.store'] as $routeName) {
        expect(Route::getRoutes()->getByName($routeName)->gatherMiddleware(), $routeName)->toContain('throttle:taskflow-media-upload');
    }

    expect(Route::getRoutes()->getByName('tasks.show')->gatherMiddleware())->not->toContain('throttle:taskflow-search')
        ->and(Route::getRoutes()->getByName('api.v1.tasks.media.download')->gatherMiddleware())->not->toContain('throttle:taskflow-media-upload');
});

test('unexpected failures remain opaque 500 responses', function (): void {
    config(['app.debug' => false]);
    Route::middleware('api')->get('/api/_test/safe-error', fn () => throw new LogicException(
        'INTERNAL_SECRET E:/private/path database=production',
    ));

    $response = $this->getJson('/api/_test/safe-error')->assertStatus(500);
    $payload = $response->getContent();

    expect($payload)->not->toContain('INTERNAL_SECRET')
        ->not->toContain('E:/private/path')
        ->not->toContain('database=production');
});

test('active-user middleware adds no duplicate user refresh query for a real token', function (): void {
    $user = User::factory()->asMember()->create();
    $token = $user->createToken('query-count', ['tasks:read'])->plainTextToken;
    Route::middleware(['api', 'auth:sanctum', 'active-user'])
        ->get('/api/_test/active-user', fn () => response()->json(['ok' => true]));
    $this->app['auth']->forgetGuards();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->withToken($token)->getJson('/api/_test/active-user')->assertOk();

    $actorQueries = collect(DB::getQueryLog())->filter(function (array $query): bool {
        $sql = strtolower($query['query']);

        $quotes = '[`"]';

        return preg_match("/from {$quotes}users{$quotes}/", $sql) === 1
            && preg_match("/where {$quotes}users{$quotes}\.{$quotes}id{$quotes}/", $sql) === 1;
    });

    expect($actorQueries)->toHaveCount(1);
});
