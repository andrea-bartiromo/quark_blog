<?php

namespace Database\Factories;

use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationTemplateVersion>
 */
class CommunicationTemplateVersionFactory extends Factory
{
    protected $model = CommunicationTemplateVersion::class;

    public function definition(): array
    {
        return [
            'template_id' => CommunicationTemplate::factory(),
            'version_number' => 1,
            'subject' => fake()->sentence(),
            'preheader' => fake()->sentence(),
            'content' => ['body' => fake()->paragraphs(3, true)],
            'created_at' => now(),
        ];
    }
}
