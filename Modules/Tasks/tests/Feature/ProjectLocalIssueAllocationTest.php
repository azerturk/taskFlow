<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Tasks\Data\CreateTaskData;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Http\Resources\TaskResource;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Tasks\Services\TaskService;
use Modules\Tasks\Support\TaskDisplayNumberBackfill;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function activeIssueProject(User $actor, string $key): Project
{
    return Project::factory()->active()->create(['owner_id' => $actor->id, 'key' => $key, 'next_issue_number' => 1]);
}

it('allocates a non-null local sequence and globally readable display key per project', function (): void {
    $actor = User::factory()->asProjectManager()->create();
    $payments = activeIssueProject($actor, 'PAY');
    $operations = activeIssueProject($actor, 'OPS');
    $service = app(TaskService::class);

    $payOne = $service->create($actor, $payments, new CreateTaskData('Pay one', null, null, TaskPriority::Medium, null));
    $payTwo = $service->create($actor, $payments, new CreateTaskData('Pay two', null, null, TaskPriority::Medium, null));
    $opsOne = $service->create($actor, $operations, new CreateTaskData('Ops one', null, null, TaskPriority::Medium, null));
    $resource = (new TaskResource($payOne))->resolve();

    expect([$payOne->issue_number, $payOne->display_key])->toBe([1, 'PAY-1'])
        ->and([$payTwo->issue_number, $payTwo->display_key])->toBe([2, 'PAY-2'])
        ->and([$opsOne->issue_number, $opsOne->display_key])->toBe([1, 'OPS-1'])
        ->and($resource['display_key'])->toBe('PAY-1')
        ->and($resource['issue_number'])->toBe(1)
        ->and($payments->fresh()->next_issue_number)->toBe(3)
        ->and($operations->fresh()->next_issue_number)->toBe(2);
});

it('rolls the locked project sequence back when task persistence fails', function (): void {
    $actor = User::factory()->asProjectManager()->create();
    $project = activeIssueProject($actor, 'PAY');
    $repository = Mockery::mock(TaskRepositoryInterface::class);
    $repository->shouldReceive('appendToStatusColumn')->once()->andThrow(new RuntimeException('persistence failed'));
    app()->instance(TaskRepositoryInterface::class, $repository);

    expect(fn () => app(TaskService::class)->create(
        $actor, $project, new CreateTaskData('Cannot persist', null, null, TaskPriority::Medium, null),
    ))->toThrow(RuntimeException::class)
        ->and($project->fresh()->next_issue_number)->toBe(1)
        ->and(Task::count())->toBe(0);
});

it('rechecks project mutability after taking the sequence lock', function (): void {
    $actor = User::factory()->asProjectManager()->create();
    $project = activeIssueProject($actor, 'PAY');
    $staleActiveProject = clone $project;
    $project->update(['status' => ProjectStatus::Archived]);

    expect(fn () => app(TaskService::class)->create(
        $actor, $staleActiveProject, new CreateTaskData('Cannot persist', null, null, TaskPriority::Medium, null),
    ))->toThrow(LogicException::class)
        ->and($project->fresh()->next_issue_number)->toBe(1)
        ->and(Task::count())->toBe(0);
});

it('backfills inherited TSK numbers deterministically and keeps a report mapping', function (): void {
    $actor = User::factory()->asProjectManager()->create();
    $project = activeIssueProject($actor, 'PAY');
    $legacy = Task::factory()->for($project)->create(['number' => 'TSK-000123', 'issue_number' => 99]);

    TaskDisplayNumberBackfill::run();
    $legacy->refresh();

    expect($legacy->number)->toBe('PAY-1')
        ->and($legacy->issue_number)->toBe(1)
        ->and($project->fresh()->next_issue_number)->toBe(2)
        ->and((string) DB::table('task_number_migration_reports')->where('task_id', $legacy->id)->value('old_number'))->toBe('TSK-000123')
        ->and((string) DB::table('task_number_migration_reports')->where('task_id', $legacy->id)->value('new_display_key'))->toBe('PAY-1');
});

it('enforces one issue number per project while allowing sequence one in different projects', function (): void {
    $actor = User::factory()->asProjectManager()->create();
    $payments = activeIssueProject($actor, 'PAY');
    $operations = activeIssueProject($actor, 'OPS');
    Task::factory()->for($payments)->create(['number' => 'PAY-1', 'issue_number' => 1]);

    expect(fn () => Task::factory()->for($payments)->create(['number' => 'PAY-2', 'issue_number' => 1]))->toThrow(QueryException::class);
    expect(fn () => Task::factory()->for($payments)->create(['number' => 'PAY-3', 'issue_number' => null]))->toThrow(QueryException::class);
    expect(Task::factory()->for($operations)->create(['number' => 'OPS-1', 'issue_number' => 1])->display_key)->toBe('OPS-1');
});
