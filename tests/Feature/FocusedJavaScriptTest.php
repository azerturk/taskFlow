<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('focused JavaScript uses accessible dialogs, no persistent token storage, and safe board requests', function (): void {
    $script = file_get_contents(resource_path('js/app.js'));
    $modal = file_get_contents(resource_path('views/components/modal.blade.php'));

    expect($script)->toContain('data-confirm-modal')
        ->toContain('returnFocus')
        ->toContain("event.key === 'Escape'")
        ->toContain("'X-CSRF-TOKEN'")
        ->toContain('data-one-time-token')
        ->toContain('container.remove()')
        ->not->toContain('localStorage')
        ->not->toContain('sessionStorage')
        ->not->toContain('console.')
        ->and($modal)->toContain('data-preview-modal')
        ->toContain('data-preview-frame');
});

test('core task and board forms retain safe no JavaScript fallbacks while enhancing media and copy controls', function (): void {
    $manager = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $task = Task::factory()->for($project)->for($manager, 'creator')->create();
    $board = file_get_contents(module_path('Tasks', 'resources/views/board.blade.php'));
    $media = file_get_contents(resource_path('views/components/media-links.blade.php'));
    $script = file_get_contents(resource_path('js/app.js'));

    $this->actingAs($manager)->get(route('tasks.show', $task))
        ->assertOk()
        ->assertSee('Copy key')
        ->assertSee('data-copy-text', false)
        ->assertSee('method="POST" action="'.route('tasks.comments.store', $task).'"', false);
    $this->actingAs($manager)->get(route('projects.board', $project))
        ->assertOk()
        ->assertSee('data-board-feedback', false)
        ->assertSee('data-status-url', false)
        ->assertSee('name="expected_version"', false);

    expect($board)->toContain('method="POST" action="{{ route(\'tasks.status\', $task) }}"')
        ->toContain('data-board-card')
        ->toContain('data-rank-form')
        ->not->toContain('<script>')
        ->and($script)->toContain('before_task_id')
        ->toContain('after_task_id')
        ->toContain('response.status === 409')
        ->and($media)->toContain('data-media-preview');
});
