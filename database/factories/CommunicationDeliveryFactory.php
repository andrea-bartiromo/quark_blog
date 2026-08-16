<?php

namespace Database\Factories;

use App\Models\CommunicationDelivery;
use App\Models\CommunicationSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunicationDelivery>
 */
class CommunicationDeliveryFactory extends Factory
{
    protected $model = CommunicationDelivery::class;

    public function definition(): array
    {
        return [
            'delivery_key' => (string) Str::uuid(),
            'channel' => 'email',
            'notification_type' => 'test_notification',
            'subscriber_id' => CommunicationSubscriber::factory(),
            'status' => CommunicationDelivery::STATUS_PENDING,
            'attempts' => 0,
        ];
    }

    public function sending(): static
    {
        return $this->state(fn () => [
            'status' => CommunicationDelivery::STATUS_SENDING,
            'claimed_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => CommunicationDelivery::STATUS_SENT,
            'claimed_at' => now(),
            'sent_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => CommunicationDelivery::STATUS_FAILED,
            'claimed_at' => now(),
            'failed_at' => now(),
            'failure_reason' => 'Errore simulato per i test.',
            'attempts' => 1,
        ]);
    }
}
