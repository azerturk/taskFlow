<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\Project;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Enums\TaskType;
use Modules\Tasks\Models\Task;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numerify('TSK-######'),
            'issue_number' => fake()->unique()->numberBetween(1, 999999),
            'type' => TaskType::Task,
            'parent_id' => null,
            'project_id' => Project::factory(),
            'creator_id' => User::factory(),
            'assignee_id' => null,
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => TaskStatus::Todo,
            'rank' => fake()->unique()->numberBetween(1_000_000, 9_000_000) * 1000,
            'priority' => TaskPriority::Medium,
            'due_at' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn (): array => ['assignee_id' => User::factory()]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function done(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::Done,
            'started_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }
}
