<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Modules\Projects\Models\Project;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Support\TaskRankBackfill;

test('rank migration preflight reports and deterministically repairs preserved bad columns before uniqueness', function (): void {
    $migration = require module_path('Tasks', 'database/migrations/2026_09_07_100000_enforce_unique_task_column_ranks.php');
    $migration->down();

    $user = User::factory()->create();
    $project = Project::factory()->active()->create(['owner_id' => $user->id]);
    $healthyProject = Project::factory()->active()->create(['owner_id' => $user->id]);
    $first = Task::factory()->for($project)->for($user, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 0]);
    $second = Task::factory()->for($project)->for($user, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 0]);
    $third = Task::factory()->for($project)->for($user, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 1000]);
    $healthy = Task::factory()->for($healthyProject)->for($user, 'creator')->create(['status' => TaskStatus::Backlog, 'rank' => 2500]);

    expect(TaskRankBackfill::preflight())->toMatchArray([
        'affected_columns' => 1,
        'affected_tasks' => 3,
        'duplicate_tasks' => 2,
        'non_positive_tasks' => 2,
    ]);

    TaskRankBackfill::run();
    expect(Task::query()->whereKey([$first->id, $second->id, $third->id])->orderBy('rank')->pluck('rank')->all())
        ->toBe([1000, 2000, 3000])
        ->and($healthy->fresh()->rank)->toBe(2500)
        ->and(TaskRankBackfill::preflight()['affected_columns'])->toBe(0);

    $migration->up();
    $unique = collect(Schema::getIndexes('tasks'))->firstWhere('name', 'tasks_project_status_rank_unique');
    expect($unique)->not->toBeNull()->and($unique['unique'])->toBeTrue();

    expect(fn () => Task::factory()->for($project)->for($user, 'creator')->create([
        'status' => TaskStatus::Backlog,
        'rank' => 1000,
    ]))->toThrow(QueryException::class);
});
