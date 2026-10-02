<?php

use App\Enums\AccountStatus;
use App\Http\Middleware\EnsureActiveUser;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Drawer\Utils;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

function dashboardLivewirePayload(string $html): array
{
    $snapshot = Utils::extractAttributeDataFromHtml($html, 'wire:snapshot');

    return [
        'components' => [[
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'updates' => [],
            'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]],
        ]],
    ];
}

function sendDashboardLivewireUpdate($test, array $payload)
{
    return $test
        ->withHeader('X-Livewire', 'true')
        ->postJson(app('livewire')->getUpdateUri(), $payload);
}

test('the active-user boundary is registered as persistent Livewire middleware', function (): void {
    expect(app(PersistentMiddleware::class)->getPersistentMiddleware())
        ->toContain(EnsureActiveUser::class);
});

test('an active user can perform a real Livewire update request', function (): void {
    $manager = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $html = $this->actingAs($manager)->get(route('dashboard.index'))->assertOk()->getContent();

    sendDashboardLivewireUpdate($this, dashboardLivewirePayload($html))
        ->assertOk()
        ->assertSee($project->name, false);
});

test('a suspended user cannot use a stale Livewire snapshot to read protected data', function (): void {
    $manager = User::factory()->asProjectManager()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $html = $this->actingAs($manager)->get(route('dashboard.index'))->assertOk()->getContent();

    $manager->status = AccountStatus::Suspended;
    $manager->save();

    sendDashboardLivewireUpdate($this, dashboardLivewirePayload($html))
        ->assertUnauthorized()
        ->assertDontSee($project->name, false);
});

test('a removed project member cannot recover project data from a stale Livewire snapshot', function (): void {
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $members = app(ProjectMemberService::class);
    $members->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    $html = $this->actingAs($member)->get(route('dashboard.index'))->assertOk()->getContent();

    $members->removeMember($project, $member, $manager);

    sendDashboardLivewireUpdate($this, dashboardLivewirePayload($html))
        ->assertOk()
        ->assertDontSee($project->name, false);
});
