<?php

namespace Database\Factories;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSubscriber;
use App\Models\CommunicationTestSend;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationTestSend>
 */
class CommunicationTestSendFactory extends Factory
{
    protected $model = CommunicationTestSend::class;

    public function definition(): array
    {
        return [
            'campaign_id' => CommunicationCampaign::factory(),
            'subscriber_id' => CommunicationSubscriber::factory()->confirmed(),
            'status' => CommunicationTestSend::STATUS_ACCEPTED,
            'created_at' => now(),
        ];
    }
}
