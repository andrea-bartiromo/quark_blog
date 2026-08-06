<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectDocument>
 */
class ProjectDocumentFactory extends Factory
{
    protected $model = ProjectDocument::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => ucfirst(fake()->sentence(3)),
            'content' => fake()->paragraphs(3, true),
            'type' => ProjectDocument::TYPE_NOTE,
            'version' => 1,
            'status' => ProjectDocument::STATUS_DRAFT,
        ];
    }
}
