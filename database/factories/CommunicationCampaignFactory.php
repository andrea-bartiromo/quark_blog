<?php

namespace Database\Factories;

use App\Models\CommunicationCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationCampaign>
 */
class CommunicationCampaignFactory extends Factory
{
    protected $model = CommunicationCampaign::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(array_keys(CommunicationCampaign::typeOptions())),
            'status' => CommunicationCampaign::STATUS_DRAFT,
            'title' => ucfirst(fake()->unique()->words(3, true)),
            'subject' => fake()->sentence(),
            'content' => ['blocks' => []],
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => CommunicationCampaign::STATUS_DRAFT]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => CommunicationCampaign::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDays(3),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => CommunicationCampaign::STATUS_COMPLETED,
            'sending_started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHour(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => CommunicationCampaign::STATUS_FAILED]);
    }
}
