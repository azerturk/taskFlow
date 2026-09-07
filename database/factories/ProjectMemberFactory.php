<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;

/** @extends Factory<ProjectMember> */
class ProjectMemberFactory extends Factory
{
    protected $model = ProjectMember::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'member_role' => ProjectMemberRole::Member,
            'joined_at' => now(),
        ];
    }

    public function manager(): static
    {
        return $this->state(fn (): array => ['member_role' => ProjectMemberRole::Manager]);
    }
}
