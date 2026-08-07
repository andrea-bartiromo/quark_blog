<?php

namespace Database\Factories;

use App\Models\CommunicationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationTemplate>
 */
class CommunicationTemplateFactory extends Factory
{
    protected $model = CommunicationTemplate::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(),
            'status' => CommunicationTemplate::STATUS_ACTIVE,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => CommunicationTemplate::STATUS_ARCHIVED]);
    }
}
