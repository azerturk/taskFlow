<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Dashboard\Data\DashboardSummaryData;
use Modules\Dashboard\Services\DashboardService;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Enums\TaskType;
use Modules\Tasks\Models\Task;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function dashboardMetricsContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $member = User::factory()->asMember()->create();
    $outsider = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id, 'key' => 'MET']);
    $completedProject = Project::factory()->completed()->create(['owner_id' => $manager->id, 'key' => 'CMP']);
    $archivedProject = Project::factory()->archived()->create(['owner_id' => $manager->id, 'key' => 'ARC']);
    $hiddenProject = Project::factory()->completed()->create(['owner_id' => $outsider->id, 'key' => 'HID']);
    app(ProjectMemberService::class)->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
    foreach ([$completedProject, $archivedProject] as $readOnlyProject) {
        $readOnlyProject->members()->attach($member, [
            'member_role' => ProjectMemberRole::Member->value,
            'joined_at' => now(),
        ]);
    }

    $assigned = Task::factory()->for($project)->for($manager, 'creator')->create([
        'title' => 'Assigned visible work', 'assignee_id' => $member->id, 'status' => TaskStatus::Todo, 'type' => TaskType::Bug, 'due_at' => now()->subDay(),
    ]);
    $reported = Task::factory()->for($project)->for($member, 'creator')->create([
        'title' => 'Reported visible work', 'status' => TaskStatus::InProgress, 'type' => TaskType::Story,
    ]);
    $completed = Task::factory()->for($project)->for($manager, 'creator')->create([
        'title' => 'Completed today visible work', 'status' => TaskStatus::Done, 'completed_at' => now(), 'type' => TaskType::Task,
    ]);
    $backlog = Task::factory()->for($project)->for($manager, 'creator')->create([
        'title' => 'Visible backlog subtask', 'status' => TaskStatus::Backlog, 'type' => TaskType::Subtask, 'parent_id' => $assigned->id,
    ]);
    $review = Task::factory()->for($project)->for($manager, 'creator')->create([
        'title' => 'Visible review work', 'status' => TaskStatus::Review, 'type' => TaskType::Bug,
    ]);
    $cancelled = Task::factory()->for($project)->for($manager, 'creator')->create([
        'title' => 'Visible cancelled work', 'status' => TaskStatus::Cancelled, 'type' => TaskType::Task,
    ]);
    $completed->watchers()->attach($member);
    $hidden = Task::factory()->for($hiddenProject)->for($outsider, 'creator')->create([
        'title' => 'Hidden dashboard work', 'assignee_id' => $member->id, 'status' => TaskStatus::Review,
    ]);

    return compact(
        'manager', 'member', 'outsider', 'project', 'completedProject', 'archivedProject',
        'assigned', 'reported', 'completed', 'backlog', 'review', 'cancelled', 'hidden',
    );
}

test('dashboard metrics and personal queues use the canonical project visibility scope', function (): void {
    ['member' => $member, 'assigned' => $assigned, 'reported' => $reported, 'completed' => $completed, 'hidden' => $hidden] = dashboardMetricsContext();

    $dashboard = app(DashboardService::class);
    $summary = $dashboard->summary($member);
    $page = $dashboard->page($member);

    expect($summary)->toBeInstanceOf(DashboardSummaryData::class)
        ->and($summary->activeProjects)->toBe(1)
        ->and($summary->completedProjects)->toBe(1)
        ->and($summary->archivedProjects)->toBe(1)
        ->and($summary->totalTasks)->toBe(6)
        ->and($summary->overdue)->toBe(1)
        ->and($summary->completedToday)->toBe(1)
        ->and($summary->projectStatusDistribution)->toBe([
            'draft' => 0, 'active' => 1, 'completed' => 1, 'archived' => 1,
        ])
        ->and($summary->taskStatusDistribution)->toBe([
            'backlog' => 1, 'todo' => 1, 'in_progress' => 1, 'review' => 1, 'done' => 1, 'cancelled' => 1,
        ])
        ->and($summary->taskTypeDistribution)->toBe([
            'task' => 2, 'bug' => 2, 'story' => 1, 'subtask' => 1,
        ])
        ->and($page['myTasks']->pluck('id')->all())->toBe([$assigned->id])
        ->and($page['reportedTasks']->pluck('id')->all())->toBe([$reported->id])
        ->and($page['watchedTasks']->pluck('id')->all())->toBe([$completed->id])
        ->and($page['overdueTasks']->pluck('id')->all())->toBe([$assigned->id])
        ->and($page['myTasks']->pluck('id')->all())->not->toContain($hidden->id);
});

test('admin, context manager and member dashboard totals follow the same visibility matrix as task lists', function (): void {
    ['manager' => $manager, 'member' => $member, 'outsider' => $outsider, 'hidden' => $hidden] = dashboardMetricsContext();
    $admin = User::factory()->asAdmin()->create();
    $outsiderSummary = app(DashboardService::class)->summary($outsider);

    expect(app(DashboardService::class)->summary($manager)->totalTasks)->toBe(6)
        ->and(app(DashboardService::class)->summary($member)->totalTasks)->toBe(6)
        ->and(app(DashboardService::class)->summary($admin)->totalTasks)->toBe(7)
        ->and(app(DashboardService::class)->summary($admin)->completedProjects)->toBe(2)
        ->and(app(DashboardService::class)->summary($manager)->projectStatusDistribution)->toBe([
            ProjectStatus::Draft->value => 0,
            ProjectStatus::Active->value => 1,
            ProjectStatus::Completed->value => 1,
            ProjectStatus::Archived->value => 1,
        ])
        ->and($outsiderSummary->projectStatusDistribution)->toBe([
            'draft' => 0, 'active' => 0, 'completed' => 1, 'archived' => 0,
        ])
        ->and($outsiderSummary->taskStatusDistribution)->toBe([
            'backlog' => 0, 'todo' => 0, 'in_progress' => 0, 'review' => 1, 'done' => 0, 'cancelled' => 0,
        ])
        ->and($outsiderSummary->taskTypeDistribution)->toBe([
            'task' => 1, 'bug' => 0, 'story' => 0, 'subtask' => 0,
        ])
        ->and(app(DashboardService::class)->paginateOverdue($member, 20)->pluck('id')->all())->not->toContain($hidden->id);
});

test('dashboard web and API queues have parity and do not disclose inaccessible work', function (): void {
    ['member' => $member, 'assigned' => $assigned, 'reported' => $reported, 'completed' => $completed, 'hidden' => $hidden] = dashboardMetricsContext();

    $this->actingAs($member)->get(route('dashboard.index'))
        ->assertOk()
        ->assertSee('My Assigned Work')
        ->assertSee('Reported by Me')
        ->assertSee('My Watched Work')
        ->assertSee('Completed projects')
        ->assertSee('Backlog')
        ->assertSee('Cancelled')
        ->assertSee($assigned->title)
        ->assertSee($reported->title)
        ->assertSee($completed->title)
        ->assertDontSee($hidden->title);

    Sanctum::actingAs($member, ['dashboard:read']);
    $this->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.active_projects', 1)
        ->assertJsonPath('data.completed_projects', 1)
        ->assertJsonPath('data.archived_projects', 1)
        ->assertJsonPath('data.total_tasks', 6)
        ->assertJsonPath('data.backlog', 1)
        ->assertJsonPath('data.todo', 1)
        ->assertJsonPath('data.in_progress', 1)
        ->assertJsonPath('data.review', 1)
        ->assertJsonPath('data.done', 1)
        ->assertJsonPath('data.cancelled', 1)
        ->assertJsonPath('data.project_status_distribution.active', 1)
        ->assertJsonPath('data.task_status_distribution.cancelled', 1)
        ->assertJsonPath('data.task_type_distribution.subtask', 1);
    $this->getJson('/api/v1/dashboard/my-tasks')->assertOk()->assertJsonPath('data.0.id', $assigned->id);
    $this->getJson('/api/v1/dashboard/reported')->assertOk()->assertJsonPath('data.0.id', $reported->id);
    $this->getJson('/api/v1/dashboard/watched')->assertOk()->assertJsonPath('data.0.id', $completed->id);
    $this->getJson('/api/v1/dashboard/overdue')->assertOk()->assertJsonPath('data.0.id', $assigned->id)->assertJsonMissing(['id' => $hidden->id]);
});

test('dashboard summary query count is bounded and independent of visible task volume', function (): void {
    ['member' => $member, 'project' => $project] = dashboardMetricsContext();
    $member->load('roles');

    $countQueries = function () use ($member): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            app(DashboardService::class)->page($member);

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    };

    $small = $countQueries();
    Task::factory()->count(30)->for($project)->for($member, 'creator')->create([
        'status' => TaskStatus::Backlog,
        'type' => TaskType::Task,
    ]);
    $large = $countQueries();

    expect($large)->toBe($small)
        ->and($large)->toBeLessThanOrEqual(18);
});

test('dashboard API requires both a dashboard ability and dashboard authorization', function (): void {
    ['member' => $member] = dashboardMetricsContext();

    Sanctum::actingAs($member, ['tasks:read']);
    $this->getJson('/api/v1/dashboard/summary')->assertForbidden();

    $unprivileged = User::factory()->create();
    Sanctum::actingAs($unprivileged, ['dashboard:read']);
    $this->getJson('/api/v1/dashboard/summary')->assertForbidden();
});
