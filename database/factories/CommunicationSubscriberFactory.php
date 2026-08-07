<?php

namespace Database\Factories;

use App\Models\CommunicationSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunicationSubscriber>
 */
class CommunicationSubscriberFactory extends Factory
{
    protected $model = CommunicationSubscriber::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'status' => CommunicationSubscriber::STATUS_PENDING,
            'token' => Str::random(64),
            'unsubscribe_token' => Str::random(32),
            'source' => 'popup',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => CommunicationSubscriber::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'token' => null,
        ]);
    }
}
