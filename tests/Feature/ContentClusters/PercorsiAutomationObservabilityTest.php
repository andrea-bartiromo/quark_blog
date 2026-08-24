<?php

namespace Tests\Feature\ContentClusters;

use App\Models\ActivityLog;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContentClusters\PercorsiAutomationObservability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission 09 — Percorsi Automation Observability.
 */
class PercorsiAutomationObservabilityTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    public function test_summary_counts_zero_clusters_and_reports_no_promotion(): void
    {
        $summary = app(PercorsiAutomationObservability::class)->summary();

        $this->assertSame(0, $summary['updating']);
        $this->assertSame(0, $summary['complete']);
        $this->assertNull($summary['last_promotion']);
    }

    public function test_summary_counts_clusters_by_lifecycle_status(): void
    {
        ContentCluster::factory()->count(3)->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        ContentCluster::factory()->count(2)->create(['lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE]);

        $summary = app(PercorsiAutomationObservability::class)->summary();

        $this->assertSame(3, $summary['updating']);
        $this->assertSame(2, $summary['complete']);
    }

    public function test_summary_surfaces_the_most_recent_automatic_promotion_only(): void
    {
        ActivityLog::record(
            'Percorso concluso automaticamente (tutte le tappe configurate sono pubbliche)',
            'content_cluster',
            1,
            'Percorso più vecchio'
        );
        $older = ActivityLog::latest('id')->first();
        $older->forceFill(['created_at' => now()->subHour()])->save();

        ActivityLog::record(
            'Percorso concluso automaticamente (tutte le tappe configurate sono pubbliche)',
            'content_cluster',
            2,
            'Percorso più recente'
        );

        $summary = app(PercorsiAutomationObservability::class)->summary();

        $this->assertNotNull($summary['last_promotion']);
        $this->assertSame('Percorso più recente', $summary['last_promotion']['cluster_name']);
    }

    /**
     * Manual edits to lifecycle_status (via the admin form) and unrelated
     * ActivityLog rows (e.g. an editor manually saving a cluster) must not
     * be mistaken for an automatic promotion — only the exact action string
     * written by ReconcileContentClusterLifecycle counts.
     */
    public function test_summary_ignores_activity_log_rows_from_other_actions(): void
    {
        ActivityLog::record('Percorso creato', 'content_cluster', 1, 'Percorso creato a mano');

        $summary = app(PercorsiAutomationObservability::class)->summary();

        $this->assertNull($summary['last_promotion']);
    }

    public function test_admin_index_renders_the_automation_panel(): void
    {
        $editor = $this->editor();
        ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE]);
        ActivityLog::record(
            'Percorso concluso automaticamente (tutte le tappe configurate sono pubbliche)',
            'content_cluster',
            999,
            'Percorso osservato'
        );

        $this->actingAs($editor)
            ->get(route('admin.content-clusters.index'))
            ->assertOk()
            ->assertSee('Automazione lifecycle')
            ->assertSee('Percorso osservato');
    }

    public function test_admin_index_renders_the_automation_panel_with_no_promotions_yet(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->get(route('admin.content-clusters.index'))
            ->assertOk()
            ->assertSee('Nessuna conclusione automatica registrata finora');
    }
}
