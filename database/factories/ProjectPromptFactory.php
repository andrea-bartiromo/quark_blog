<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectPrompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectPrompt>
 */
class ProjectPromptFactory extends Factory
{
    protected $model = ProjectPrompt::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => ucfirst(fake()->sentence(3)),
            'agent' => 'Claude Code',
            'content' => fake()->paragraph(),
            'status' => ProjectPrompt::STATUS_DRAFT,
        ];
    }
}
