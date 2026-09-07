<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskComment;
use Modules\Tasks\Services\TaskCommentService;
use Modules\Tasks\Services\TaskWatcherService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

function commentFlowContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $author = User::factory()->asMember()->create();
    $member = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $members = app(ProjectMemberService::class);
    $members->addMember($project, $author, ProjectMemberRole::Member, actor: $manager);
    $members->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    $task = Task::factory()->for($project)->for($author, 'creator')->create();

    return [$manager, $author, $member, $outsider, $project, $task];
}

test('members create and read plain-text comments through web and API contracts', function (): void {
    [, $author, $watcher, , , $task] = commentFlowContext();
    app(TaskWatcherService::class)->watch($task->load('project'), $watcher, $watcher);

    Sanctum::actingAs($author, ['comments:write']);
    $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => ' <script>alert(1)</script> '])
        ->assertCreated()
        ->assertJsonPath('data.body', '<script>alert(1)</script>')
        ->assertJsonPath('data.author.id', $author->id)
        ->assertJsonStructure(['data' => ['id', 'body', 'author' => ['id', 'name'], 'created_at', 'updated_at']]);

    expect(DB::table('notifications')->where('notifiable_id', $watcher->id)->count())->toBe(1);

    Sanctum::actingAs($author, ['tasks:read']);
    $this->getJson('/api/v1/tasks/'.$task->id.'/comments')
        ->assertOk()
        ->assertJsonPath('data.0.body', '<script>alert(1)</script>');

    $this->actingAs($author)->get(route('tasks.show', $task))
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);

    $this->actingAs($author)->post(route('tasks.comments.store', $task), ['body' => 'web comment'])
        ->assertRedirect();
    expect(TaskComment::query()->count())->toBe(2)
        ->and(DB::table('notifications')->where('notifiable_id', $watcher->id)->count())->toBe(2);
});

test('comment requests reject blank input without creating notification or activity', function (): void {
    [, $author, $watcher, , , $task] = commentFlowContext();
    app(TaskWatcherService::class)->watch($task->load('project'), $watcher, $watcher);

    Sanctum::actingAs($author, ['comments:write']);
    $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => str_repeat('a', 5001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
    $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => " \n\t "])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    expect(TaskComment::query()->count())->toBe(0)
        ->and(DB::table('notifications')->count())->toBe(0)
        ->and(Activity::query()->where('event', 'comment.created')->count())->toBe(0);
});

test('comment authorization scopes nested resources and permits author or manager deletion only', function (): void {
    [$manager, $author, $member, $outsider, $project, $task] = commentFlowContext();
    $comment = TaskComment::factory()->for($task)->for($author)->create(['body' => 'author comment']);
    $otherTask = Task::factory()->for($project)->for($author, 'creator')->create();

    Sanctum::actingAs($outsider, ['comments:write']);
    $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => 'blocked'])->assertNotFound();

    Sanctum::actingAs($member, ['comments:write']);
    $this->deleteJson('/api/v1/tasks/'.$task->id.'/comments/'.$comment->id)->assertForbidden();

    Sanctum::actingAs($author, ['comments:write']);
    $this->deleteJson('/api/v1/tasks/'.$otherTask->id.'/comments/'.$comment->id)->assertNotFound();
    $this->deleteJson('/api/v1/tasks/'.$task->id.'/comments/'.$comment->id)->assertNoContent();

    $managerComment = TaskComment::factory()->for($task)->for($author)->create(['body' => 'manager deletion']);
    Sanctum::actingAs($manager, ['comments:write']);
    $this->deleteJson('/api/v1/tasks/'.$task->id.'/comments/'.$managerComment->id)->assertNoContent();
});

test('comments are immutable in read-only projects and activities omit comment text', function (): void {
    [$manager, $author, , , $project, $task] = commentFlowContext();
    $comment = app(TaskCommentService::class)->create($task->load('project'), $author, 'private <b>body</b>');
    $properties = Activity::query()->where('event', 'comment.created')->latest()->firstOrFail()->properties->toArray();

    expect($properties)->toHaveKeys(['project_id', 'task_id', 'comment_id'])
        ->not->toHaveKey('body')
        ->not->toContain('private <b>body</b>');

    $project->update(['status' => ProjectStatus::Completed]);
    Sanctum::actingAs($author, ['comments:write']);
    $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => 'blocked'])->assertForbidden();

    Sanctum::actingAs($manager, ['comments:write']);
    $this->deleteJson('/api/v1/tasks/'.$task->id.'/comments/'.$comment->id)->assertForbidden();
});
