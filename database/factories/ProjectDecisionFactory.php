<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectDecision>
 */
class ProjectDecisionFactory extends Factory
{
    protected $model = ProjectDecision::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => ucfirst(fake()->sentence(4)),
            'context' => fake()->paragraph(),
            'decision' => fake()->sentence(8),
            'rationale' => fake()->paragraph(),
            'status' => ProjectDecision::STATUS_PROPOSED,
        ];
    }
}
