<?php

namespace Tests\Feature\ContentClusters;

use App\Models\CommunicationSubscriber;
use App\Models\ContentCluster;
use App\Models\ContentClusterSubscriber;
use App\Services\ContentClusters\PercorsoSubscriberNotificationReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missione 22 (secondo batch autonomo KAIRUS, Fase C — Percorsi Advanced
 * Operations): "Percorsi subscriber notification readiness."
 */
class PercorsoSubscriberNotificationReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PercorsoSubscriberNotificationReadinessService
    {
        return app(PercorsoSubscriberNotificationReadinessService::class);
    }

    public function test_a_cluster_with_no_subscribers_reports_zero_everywhere(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ]);

        $summary = $this->service()->summary($cluster);

        $this->assertTrue($summary['notifications_would_fire']);
        $this->assertSame(0, $summary['active_subscriptions']);
        $this->assertSame(0, $summary['eligible_now']);
        $this->assertSame(0, $summary['not_eligible_now']);
        $this->assertSame(0, $summary['unsubscribed']);
    }

    public function test_only_confirmed_subscribers_count_as_eligible_now(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ]);
        ContentClusterSubscriber::factory()->for(
            CommunicationSubscriber::factory()->confirmed(), 'subscriber'
        )->create(['content_cluster_id' => $cluster->id]);
        ContentClusterSubscriber::factory()->for(
            CommunicationSubscriber::factory(), 'subscriber'
        )->create(['content_cluster_id' => $cluster->id]);
        ContentClusterSubscriber::factory()->for(
            CommunicationSubscriber::factory()->state(['status' => CommunicationSubscriber::STATUS_BOUNCED]), 'subscriber'
        )->create(['content_cluster_id' => $cluster->id]);

        $summary = $this->service()->summary($cluster);

        $this->assertSame(3, $summary['active_subscriptions']);
        $this->assertSame(1, $summary['eligible_now']);
        $this->assertSame(2, $summary['not_eligible_now']);
    }

    public function test_unsubscribed_path_subscriptions_are_counted_separately_and_never_as_active(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ]);
        ContentClusterSubscriber::factory()->unsubscribed()->for(
            CommunicationSubscriber::factory()->confirmed(), 'subscriber'
        )->create(['content_cluster_id' => $cluster->id]);

        $summary = $this->service()->summary($cluster);

        $this->assertSame(0, $summary['active_subscriptions']);
        $this->assertSame(0, $summary['eligible_now']);
        $this->assertSame(1, $summary['unsubscribed']);
    }

    /**
     * ContentCluster::acceptsPathSubscriptions() — la stessa condizione già
     * usata da PathContinuationNotifier::notifyIfPublished() — deve
     * riflettersi qui: un Percorso concluso non invierà mai nulla, anche
     * con abbonati confermati.
     */
    public function test_a_complete_percorso_reports_that_notifications_would_not_fire_despite_eligible_subscribers(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE,
        ]);
        ContentClusterSubscriber::factory()->for(
            CommunicationSubscriber::factory()->confirmed(), 'subscriber'
        )->create(['content_cluster_id' => $cluster->id]);

        $summary = $this->service()->summary($cluster);

        $this->assertFalse($summary['notifications_would_fire']);
        $this->assertSame(1, $summary['eligible_now']);
    }

    public function test_an_inactive_percorso_reports_that_notifications_would_not_fire(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => false,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ]);

        $summary = $this->service()->summary($cluster);

        $this->assertFalse($summary['notifications_would_fire']);
    }
}
