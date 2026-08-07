<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTask>
 */
class ProjectTaskFactory extends Factory
{
    protected $model = ProjectTask::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => ucfirst(fake()->sentence(4)),
            'type' => ProjectTask::TYPE_TASK,
            'manual_status' => ProjectTask::STATUS_TODO,
            'status_source' => ProjectTask::SOURCE_MANUAL,
            'priority' => ProjectTask::PRIORITY_MEDIUM,
            'sort_order' => 0,
        ];
    }

    public function publication(): static
    {
        return $this->state(fn () => [
            'type' => ProjectTask::TYPE_PUBLICATION,
        ]);
    }

    public function development(): static
    {
        return $this->state(fn () => [
            'type' => ProjectTask::TYPE_DEVELOPMENT,
        ]);
    }
}
