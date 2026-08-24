<?php

namespace Tests\Feature;

use App\Models\ContentCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Batch 05 Mission 7 — Percorsi Scheduling V1 (docs/PERCORSI_SCHEDULING_V1_SPEC.md).
 * Model-level contract only: scopePubliclyVisible()/isPubliclyVisible() must
 * agree with each other and with the four-branch policy. Wiring these into
 * the public controller/sitemap/ArticlePathNavigation is deliberately out of
 * scope here (see the model docblock) — this suite proves the policy itself,
 * not yet its integration.
 */
class ContentClusterPubliclyVisibleScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_legacy_cluster_with_no_publish_at_is_visible(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => null]);

        $this->assertTrue($cluster->isPubliclyVisible());
        $this->assertTrue(ContentCluster::publiclyVisible()->whereKey($cluster->id)->exists());
    }

    public function test_active_cluster_scheduled_in_the_future_is_not_visible(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->addDay()]);

        $this->assertFalse($cluster->isPubliclyVisible());
        $this->assertFalse(ContentCluster::publiclyVisible()->whereKey($cluster->id)->exists());
    }

    public function test_active_cluster_scheduled_in_the_past_is_visible(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->subDay()]);

        $this->assertTrue($cluster->isPubliclyVisible());
        $this->assertTrue(ContentCluster::publiclyVisible()->whereKey($cluster->id)->exists());
    }

    /**
     * The exact boundary instant counts as public (<=), matching the same
     * "already due" convention used by Article::published().
     */
    public function test_publish_at_exactly_now_is_visible(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->subSecond()]);

        $this->assertTrue($cluster->isPubliclyVisible());
        $this->assertTrue(ContentCluster::publiclyVisible()->whereKey($cluster->id)->exists());
    }

    /**
     * A publish_at frozen to the exact same instant as "now" (not merely
     * a second in the past) must count as due, proving the <= boundary
     * holds on a true tie rather than only once the clock has moved past it.
     */
    public function test_publish_at_landing_on_the_exact_frozen_instant_is_visible(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:00:00', 'UTC'));

        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()]);

        $this->assertTrue($cluster->fresh()->isPubliclyVisible());
        $this->assertTrue(ContentCluster::publiclyVisible()->whereKey($cluster->id)->exists());

        Carbon::setTestNow();
    }

    /**
     * Regression guard for the 2026 Europe/Rome spring-forward transition
     * (02:00 CET jumps to 03:00 CEST on 2026-03-29, so 02:30 local never
     * exists). A publish_at set to a Rome-local instant either side of that
     * gap must still resolve correctly once stored as UTC.
     */
    public function test_publish_at_around_the_europe_rome_dst_spring_forward_gap_resolves_correctly(): void
    {
        // 2026-03-29 03:30 CEST == 01:30 UTC, one hour after the jump.
        Carbon::setTestNow(Carbon::parse('2026-03-29 01:30:00', 'UTC'));

        $justBefore = ContentCluster::factory()->create([
            'is_active' => true,
            'publish_at' => now()->subMinute(),
        ]);
        $stillFuture = ContentCluster::factory()->create([
            'is_active' => true,
            'publish_at' => now()->addHour(),
        ]);

        $this->assertTrue($justBefore->fresh()->isPubliclyVisible());
        $this->assertFalse($stillFuture->fresh()->isPubliclyVisible());

        Carbon::setTestNow();
    }

    public function test_inactive_cluster_is_never_visible_even_with_a_past_publish_at(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => false, 'publish_at' => now()->subDay()]);

        $this->assertFalse($cluster->isPubliclyVisible());
        $this->assertFalse(ContentCluster::publiclyVisible()->whereKey($cluster->id)->exists());
    }

    public function test_inactive_cluster_with_no_publish_at_is_not_visible(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => false, 'publish_at' => null]);

        $this->assertFalse($cluster->isPubliclyVisible());
        $this->assertFalse(ContentCluster::publiclyVisible()->whereKey($cluster->id)->exists());
    }

    public function test_rescheduling_from_one_future_date_to_another_stays_hidden(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->addWeek()]);
        $this->assertFalse($cluster->isPubliclyVisible());

        $cluster->update(['publish_at' => now()->addMonth()]);

        $this->assertFalse($cluster->fresh()->isPubliclyVisible());
    }

    public function test_clearing_publish_at_while_active_restores_immediate_legacy_visibility(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->addWeek()]);
        $this->assertFalse($cluster->isPubliclyVisible());

        $cluster->update(['publish_at' => null]);

        $this->assertTrue($cluster->fresh()->isPubliclyVisible());
    }

    public function test_deactivating_a_scheduled_cluster_cancels_visibility_regardless_of_publish_at(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->subHour()]);
        $this->assertTrue($cluster->isPubliclyVisible());

        $cluster->update(['is_active' => false]);

        $this->assertFalse($cluster->fresh()->isPubliclyVisible());
    }

    /**
     * lifecycle_status must never influence public reachability — it
     * describes editorial maturity (updating/complete), not the
     * publication-time gate.
     */
    public function test_lifecycle_status_never_influences_visibility(): void
    {
        $updating = ContentCluster::factory()->create([
            'is_active' => true,
            'publish_at' => null,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ]);
        $complete = ContentCluster::factory()->create([
            'is_active' => true,
            'publish_at' => now()->addDay(),
            'lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE,
        ]);

        $this->assertTrue($updating->isPubliclyVisible());
        $this->assertFalse($complete->isPubliclyVisible());
    }

    /**
     * A Rome-local instant that has already passed must resolve as visible
     * regardless of the current UTC/Europe-Rome offset (DST or not) — the
     * comparison happens entirely in UTC via the 'datetime' cast, so this
     * is a regression guard against ever comparing a naive/local string.
     */
    public function test_a_past_europe_rome_instant_is_visible_independent_of_dst_offset(): void
    {
        $romeNow = now()->timezone('Europe/Rome');
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'publish_at' => $romeNow->clone()->subHour(),
        ]);

        $this->assertTrue($cluster->fresh()->isPubliclyVisible());
    }

    public function test_scope_and_instance_method_agree_across_a_mixed_batch(): void
    {
        $visible = [
            ContentCluster::factory()->create(['is_active' => true, 'publish_at' => null]),
            ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->subMinute()]),
        ];
        $hidden = [
            ContentCluster::factory()->create(['is_active' => false, 'publish_at' => null]),
            ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->addMinute()]),
            ContentCluster::factory()->create(['is_active' => false, 'publish_at' => now()->subMinute()]),
        ];

        $visibleIds = ContentCluster::publiclyVisible()->pluck('id')->sort()->values()->all();

        $this->assertSame(collect($visible)->pluck('id')->sort()->values()->all(), $visibleIds);

        foreach ($visible as $cluster) {
            $this->assertTrue($cluster->fresh()->isPubliclyVisible());
        }
        foreach ($hidden as $cluster) {
            $this->assertFalse($cluster->fresh()->isPubliclyVisible());
        }
    }
}
