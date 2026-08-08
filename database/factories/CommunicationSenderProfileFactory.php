<?php

namespace Database\Factories;

use App\Models\CommunicationSenderProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationSenderProfile>
 */
class CommunicationSenderProfileFactory extends Factory
{
    protected $model = CommunicationSenderProfile::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'from_name' => fake()->company(),
            'from_email' => fake()->unique()->safeEmail(),
            'provider' => CommunicationSenderProfile::PROVIDER_SMTP,
            'status' => CommunicationSenderProfile::STATUS_ACTIVE,
            'is_default' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => CommunicationSenderProfile::STATUS_ARCHIVED]);
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
