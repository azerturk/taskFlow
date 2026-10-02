<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'key' => fake()->unique()->bothify('PRJ##'),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->paragraph(),
            'status' => ProjectStatus::Draft,
            'owner_id' => User::factory(),
            'starts_at' => null,
            'due_at' => null,
            'next_issue_number' => 1,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => ProjectStatus::Active]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => ProjectStatus::Completed]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => ProjectStatus::Archived]);
    }
}
