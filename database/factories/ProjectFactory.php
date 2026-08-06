<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(array_keys(Project::typeOptions())),
            'operational_status' => Project::STATUS_IDEA,
            'priority' => Project::PRIORITY_MEDIUM,
            'progress' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['operational_status' => Project::STATUS_IN_PROGRESS]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['operational_status' => Project::STATUS_BLOCKED]);
    }

    public function highPriority(): static
    {
        return $this->state(fn () => ['priority' => Project::PRIORITY_HIGH]);
    }
}
