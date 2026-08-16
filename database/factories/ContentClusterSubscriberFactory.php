<?php

namespace Database\Factories;

use App\Models\CommunicationSubscriber;
use App\Models\ContentCluster;
use App\Models\ContentClusterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentClusterSubscriber>
 */
class ContentClusterSubscriberFactory extends Factory
{
    protected $model = ContentClusterSubscriber::class;

    public function definition(): array
    {
        return [
            'subscriber_id' => CommunicationSubscriber::factory()->confirmed(),
            'content_cluster_id' => ContentCluster::factory(),
            'status' => ContentClusterSubscriber::STATUS_ACTIVE,
            'unsubscribe_token' => Str::random(32),
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn () => [
            'status' => ContentClusterSubscriber::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ]);
    }
}
