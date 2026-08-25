<?php

namespace Tests\Feature\ContentClusters;

use App\Models\ContentCluster;
use App\Services\ContentClusters\PercorsiActivationCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missione 12 (secondo batch autonomo KAIRUS, Fase C — Percorsi Advanced
 * Operations).
 */
class PercorsiActivationCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_is_all_zero_with_no_percorsi_and_no_next_activation(): void
    {
        $summary = app(PercorsiActivationCalendarService::class)->summary();

        $this->assertSame(0, $summary['active_now']);
        $this->assertSame(0, $summary['scheduled']);
        $this->assertSame(0, $summary['inactive']);
        $this->assertNull($summary['next_activation']);
    }

    public function test_summary_buckets_each_percorso_into_exactly_one_category(): void
    {
        // Attivo ora, senza data (legacy, pubblico subito).
        ContentCluster::factory()->create(['is_active' => true, 'publish_at' => null]);
        // Attivo ora, data già passata.
        ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->subDay()]);
        // Programmato, nel futuro.
        ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->addDays(3)]);
        // Inattivo, indipendentemente da publish_at.
        ContentCluster::factory()->create(['is_active' => false, 'publish_at' => now()->addDays(1)]);
        ContentCluster::factory()->create(['is_active' => false, 'publish_at' => null]);

        $summary = app(PercorsiActivationCalendarService::class)->summary();

        $this->assertSame(2, $summary['active_now']);
        $this->assertSame(1, $summary['scheduled']);
        $this->assertSame(2, $summary['inactive']);
    }

    public function test_next_activation_is_the_earliest_future_publish_at_in_europe_rome(): void
    {
        $sooner = ContentCluster::factory()->create([
            'is_active' => true,
            'name' => 'Percorso più vicino',
            'publish_at' => now()->addDays(2),
        ]);
        ContentCluster::factory()->create([
            'is_active' => true,
            'name' => 'Percorso più lontano',
            'publish_at' => now()->addDays(10),
        ]);

        $summary = app(PercorsiActivationCalendarService::class)->summary();

        $this->assertNotNull($summary['next_activation']);
        $this->assertSame('Percorso più vicino', $summary['next_activation']['cluster_name']);
        $this->assertSame($sooner->slug, $summary['next_activation']['slug']);
        $this->assertSame('Europe/Rome', $summary['next_activation']['at']->timezoneName);
        $this->assertTrue($summary['next_activation']['at']->equalTo($sooner->publish_at));
    }

    public function test_a_percorso_scheduled_in_the_past_second_is_no_longer_counted_as_scheduled(): void
    {
        // Boundary esatto della stessa policy di scopePubliclyVisible():
        // publish_at <= now() è già pubblico, non più "programmato".
        ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()]);

        $summary = app(PercorsiActivationCalendarService::class)->summary();

        $this->assertSame(1, $summary['active_now']);
        $this->assertSame(0, $summary['scheduled']);
        $this->assertNull($summary['next_activation']);
    }
}
