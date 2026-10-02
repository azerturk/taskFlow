<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Livewire\TaskCommentForm;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskComment;
use Modules\Tasks\Services\TaskWatcherService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function livewireCommentContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $author = User::factory()->asMember()->create();
    $watcher = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $members = app(ProjectMemberService::class);
    $members->addMember($project, $author, ProjectMemberRole::Member, actor: $manager);
    $members->addMember($project, $watcher, ProjectMemberRole::Member, actor: $manager);
    $task = Task::factory()->for($project)->for($author, 'creator')->create();
    app(TaskWatcherService::class)->watch($task->load('project'), $watcher, $watcher);

    return [$author, $watcher, $task];
}

test('the comment form refreshes its list and records exactly one action after a successful submit', function (): void {
    [$author, $watcher, $task] = livewireCommentContext();
    $this->actingAs($author);

    $component = Livewire::test(TaskCommentForm::class, ['task' => $task])
        ->assertSee('No comments yet.')
        ->set('body', 'Livewire comment')
        ->call('submit')
        ->assertSet('body', '')
        ->assertSee('Livewire comment')
        ->assertSee('Comment added.');

    expect(TaskComment::query()->count())->toBe(1)
        ->and(Activity::query()->where('event', 'comment.created')->count())->toBe(1)
        ->and(DB::table('notifications')->where('notifiable_id', $watcher->id)->count())->toBe(1);

    $component->call('submit')->assertHasErrors('body');
    expect(TaskComment::query()->count())->toBe(1)
        ->and(Activity::query()->where('event', 'comment.created')->count())->toBe(1)
        ->and(DB::table('notifications')->where('notifiable_id', $watcher->id)->count())->toBe(1);
});

test('the comment form validates plain text and renders double-submit protection', function (): void {
    [$author, , $task] = livewireCommentContext();
    $this->actingAs($author);

    Livewire::test(TaskCommentForm::class, ['task' => $task])
        ->set('body', " \n\t ")
        ->call('submit')
        ->assertHasErrors('body')
        ->assertSee('wire:loading.attr="disabled"', false)
        ->set('body', str_repeat('a', 5001))
        ->call('submit')
        ->assertHasErrors('body');
});
