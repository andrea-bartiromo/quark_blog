<?php

namespace Database\Factories;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationSend>
 */
class CommunicationSendFactory extends Factory
{
    protected $model = CommunicationSend::class;

    public function definition(): array
    {
        return [
            'campaign_id' => CommunicationCampaign::factory(),
            'subscriber_id' => CommunicationSubscriber::factory(),
            'status' => CommunicationSend::STATUS_QUEUED,
            'attempts' => 0,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => CommunicationSend::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => CommunicationSend::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => 'Errore SMTP simulato per i test.',
            'attempts' => 1,
        ]);
    }
}
