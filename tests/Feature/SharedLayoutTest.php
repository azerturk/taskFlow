<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the authenticated shell uses accessible shared navigation header feedback and confirmation components', function (): void {
    $manager = User::factory()->asProjectManager()->create();

    $this->actingAs($manager)->get(route('home'))
        ->assertOk()
        ->assertSee('id="workspace-navigation"', false)
        ->assertSee('aria-label="Workspace navigation"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee('Dashboard')
        ->assertSee('Projects')
        ->assertSee('Tasks')
        ->assertSee('data-confirm-modal', false)
        ->assertSee('<dialog', false);
});

test('guest pages remain separate from the authenticated workspace shell', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertDontSee('id="workspace-navigation"', false)
        ->assertDontSee('data-confirm-modal', false);
});

test('shared forms, responsive views, focus treatment and media controls use the component system', function (): void {
    $root = base_path();
    $taskCreate = file_get_contents($root.'/Modules/Tasks/resources/views/create.blade.php');
    $taskEdit = file_get_contents($root.'/Modules/Tasks/resources/views/edit.blade.php');
    $projectCreate = file_get_contents($root.'/Modules/Projects/resources/views/create.blade.php');
    $projectEdit = file_get_contents($root.'/Modules/Projects/resources/views/edit.blade.php');
    $board = file_get_contents($root.'/Modules/Tasks/resources/views/board.blade.php');
    $taskShow = file_get_contents($root.'/Modules/Tasks/resources/views/show.blade.php');
    $css = file_get_contents($root.'/resources/css/app.css');

    expect($taskCreate)->toContain("@include('tasks::_form')")
        ->and($taskEdit)->toContain("@include('tasks::_form')")
        ->and($projectCreate)->toContain("@include('projects::_form'")
        ->and($projectEdit)->toContain("@include('projects::_form'")
        ->and($board)->toContain('sm:grid-cols-2 xl:grid-cols-6')
        ->and($board)->toContain('class="sr-only"')
        ->and($taskShow)->toContain('<x-media-links')
        ->and($css)->toContain(':focus-visible')
        ->and(file_exists($root.'/resources/views/welcome.blade.php'))->toBeFalse();
});
