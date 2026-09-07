<?php

use App\Models\User;
use App\Notifications\TaskWatcherNotification;
use App\Services\AdminUserService;
use App\Services\NotificationCenterService;
use App\Services\WorkspaceHeaderQueryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Activity\Data\ActivityFiltersData;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Activity\Services\ActivityQueryService;
use Modules\Dashboard\Services\DashboardService;
use Modules\Projects\Data\ProjectFiltersData;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectQueryService;
use Modules\Tasks\Data\TaskFiltersData;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\BacklogQueryService;
use Modules\Tasks\Services\TaskBoardQueryService;
use Modules\Tasks\Services\TaskQueryService;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function boundedQueryCount(Closure $operation): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $operation();

        return count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}

test('primary read use cases have explicit bounded query budgets', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $project = Project::factory()->active()->create(['owner_id' => $admin->id]);
    $tasks = Task::factory()->count(12)->for($project)->for($admin, 'creator')->create(['assignee_id' => $admin->id]);
    User::factory()->count(12)->asMember()->create();
    $admin->load('roles');

    $counts = [
        'task_list' => boundedQueryCount(fn () => app(TaskQueryService::class)->paginateFor($admin, TaskFiltersData::fromArray([]))),
        'task_show' => boundedQueryCount(fn () => app(TaskQueryService::class)->detailPage($admin, $tasks->first(), false)),
        'project_list' => boundedQueryCount(fn () => app(ProjectQueryService::class)->indexPage($admin, ProjectFiltersData::fromArray([]))),
        'project_show' => boundedQueryCount(fn () => app(ProjectQueryService::class)->detailPage($admin, $project, false)),
        'board' => boundedQueryCount(fn () => app(TaskBoardQueryService::class)->page($project, $admin, TaskFiltersData::fromArray(['sort' => 'rank']))),
        'backlog' => boundedQueryCount(fn () => app(BacklogQueryService::class)->page($project, $admin, TaskFiltersData::fromArray(['sort' => 'rank']))),
        'dashboard' => boundedQueryCount(fn () => app(DashboardService::class)->page($admin)),
        'activity' => boundedQueryCount(fn () => app(ActivityQueryService::class)->page($admin, ActivityFiltersData::fromArray([]))),
        'admin_users' => boundedQueryCount(fn () => app(AdminUserService::class)->paginate(null, null)),
    ];

    expect($counts['task_list'])->toBeLessThanOrEqual(9)
        ->and($counts['task_show'])->toBeLessThanOrEqual(13)
        ->and($counts['project_list'])->toBeLessThanOrEqual(5)
        ->and($counts['project_show'])->toBeLessThanOrEqual(5)
        ->and($counts['board'])->toBeLessThanOrEqual(8)
        ->and($counts['backlog'])->toBeLessThanOrEqual(9)
        ->and($counts['dashboard'])->toBeLessThanOrEqual(25)
        ->and($counts['activity'])->toBeLessThanOrEqual(10)
        ->and($counts['admin_users'])->toBeLessThanOrEqual(5);
});

test('notification presentation resolves task visibility in a batch', function (): void {
    $user = User::factory()->asAdmin()->create();
    $project = Project::factory()->active()->create(['owner_id' => $user->id]);
    $task = Task::factory()->for($project)->for($user, 'creator')->create();
    $user->load('roles');
    $notification = fn () => new TaskWatcherNotification($task, $user, ActivityEvent::TaskAssigned->value);

    $user->notify($notification());
    $single = boundedQueryCount(fn () => app(NotificationCenterService::class)->paginate($user));

    foreach (range(1, 18) as $_) {
        $user->notify($notification());
    }
    $many = boundedQueryCount(fn () => app(NotificationCenterService::class)->paginate($user));

    expect($many)->toBeLessThanOrEqual($single + 1)
        ->and($many)->toBeLessThanOrEqual(6);
});

test('workspace header projection is bounded and request cached', function (): void {
    $user = User::factory()->asAdmin()->create();
    $user->load('roles');

    $count = boundedQueryCount(function () use ($user): void {
        $first = app(WorkspaceHeaderQueryService::class)->forUser($user);
        $second = app(WorkspaceHeaderQueryService::class)->forUser($user);

        expect($second)->toBe($first);
    });

    expect($count)->toBeLessThanOrEqual(3);
});

test('prepared detail and dashboard read models render with lazy loading prevention', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $project = Project::factory()->active()->create(['owner_id' => $admin->id]);
    $task = Task::factory()->for($project)->for($admin, 'creator')->create(['assignee_id' => $admin->id]);

    Model::preventLazyLoading();

    try {
        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk();
        $this->actingAs($admin)->get(route('tasks.show', $task))->assertOk();
        $this->actingAs($admin)->get(route('dashboard.index'))->assertOk();
        $this->actingAs($admin)->get(route('notifications.index'))->assertOk();
    } finally {
        Model::preventLazyLoading(false);
    }
});
