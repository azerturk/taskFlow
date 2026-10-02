<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Data\TaskFiltersData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskLabel;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});
test('shared filter scope applies multi-field filters without leaking inaccessible data', function () {
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $p = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $other = Project::factory()->active()->create(['owner_id' => $manager->id]);
    app(ProjectMemberService::class)->addMember($p, $member, ProjectMemberRole::Member, actor: $manager);
    $label = TaskLabel::create(['project_id' => $p->id, 'name' => 'Filter', 'slug' => 'filter', 'color' => '#3B82F6']);
    $ok = Task::factory()->for($p)->for($manager, 'creator')->create(['title' => 'Needle', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::High, 'due_at' => today()->subDay()]);
    $ok->labels()->attach($label);
    $hidden = Task::factory()->for($other)->for($manager, 'creator')->create(['title' => 'Needle', 'status' => TaskStatus::Todo]);
    $filters = TaskFiltersData::fromArray(['search' => 'Needle', 'statuses' => ['todo'], 'priorities' => ['high'], 'label_ids' => [$label->id], 'overdue' => true, 'sort' => 'rank']);
    expect(app(TaskRepositoryInterface::class)->paginateFor($member, $filters)->pluck('id')->all())->toBe([$ok->id])->and(app(TaskRepositoryInterface::class)->paginateFor($outsider, $filters)->total())->toBe(0);
});
test('api validates tampered filters and restores signed filter URL state', function () {
    $user = User::factory()->asProjectManager()->create();
    $p = Project::factory()->active()->create(['owner_id' => $user->id]);
    Task::factory()->for($p)->for($user, 'creator')->create(['title' => 'Alpha']);
    $unassigned = Task::factory()->for($p)->for($user, 'creator')->create(['title' => 'Unassigned']);
    Sanctum::actingAs($user, ['tasks:read']);
    $this->getJson('/api/v1/tasks?sort=sql')->assertUnprocessable()->assertJsonValidationErrors('sort');
    $this->getJson('/api/v1/tasks?unknown=value')->assertUnprocessable()->assertJsonValidationErrors('unknown');
    $this->getJson('/api/v1/tasks?assignee_id=unassigned')->assertOk()->assertJsonFragment(['id' => $unassigned->id]);
    $this->getJson('/api/v1/tasks?search=Alpha&sort=-number&per_page=1')->assertOk()->assertJsonPath('data.0.title', 'Alpha');
    $this->actingAs($user)->get(route('tasks.index', ['q' => 'Alpha', 'sort' => '-number']))->assertOk()->assertSee('Alpha');
});
