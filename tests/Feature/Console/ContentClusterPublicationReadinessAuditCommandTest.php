<?php

namespace Tests\Feature\Console;

use App\Models\ContentCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Cantiere 47 (programma "100 cantieri Kairus", dipende dal Cantiere 46):
 * copertura dedicata del comando Artisan
 * `content-clusters:publication-readiness` — la metà "comando" del
 * pattern già costruito per Category ai Cantieri 13-14, qui applicata a
 * ContentCluster riusando `PercorsoPublicationReadinessService` (già
 * coperto in `tests/Feature/PercorsoPublicationReadinessServiceTest.php`,
 * che questo file non duplica: qui si verifica solo che il comando
 * esponga correttamente il servizio, non la logica di readiness stessa).
 */
class ContentClusterPublicationReadinessAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_non_public_clusters_shows_the_empty_state_message(): void
    {
        ContentCluster::factory()->create(['is_active' => true]);

        $this->artisan('content-clusters:publication-readiness')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessun Percorso non pubblico al momento.');
    }

    public function test_a_publicly_visible_cluster_never_appears_in_the_report(): void
    {
        ContentCluster::factory()->create(['is_active' => true, 'slug' => 'pubblico-escluso']);

        Artisan::call('content-clusters:publication-readiness --json');
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame([], $decoded);
    }

    /**
     * Stessa fixture minima già verificata da
     * PercorsoPublicationReadinessServiceTest::test_readiness_is_warning_first_read_only_and_honest_about_missing_scheduling_runtime()
     * — qui verifichiamo solo che il comando esponga fedelmente lo stesso
     * status/findings del servizio, non li ricalcoliamo.
     */
    public function test_json_output_reports_the_exact_top_level_fields_for_a_minimal_inactive_cluster(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Readiness Comando',
            'slug' => 'readiness-comando',
            'is_active' => false,
        ]);

        Artisan::call('content-clusters:publication-readiness --json');
        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $report = $decoded[0];

        $this->assertSame($cluster->id, $report['content_cluster_id']);
        $this->assertSame('Readiness Comando', $report['name']);
        $this->assertSame('readiness-comando', $report['slug']);
        $this->assertSame('Inattivo', $report['visibility_label']);
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $report['lifecycle_status']);
        $this->assertSame('NOT READY', $report['status']);

        $codes = array_column($report['findings'], 'code');
        $this->assertContains('SHORT_DESCRIPTION_MISSING', $codes);
        $this->assertContains('HEALTH_NO_PILLAR', $codes);
        $this->assertContains('SCHEDULING_NOT_AVAILABLE', $codes);

        foreach ($report['findings'] as $finding) {
            $this->assertArrayHasKey('code', $finding);
            $this->assertArrayHasKey('severity', $finding);
            $this->assertArrayHasKey('message', $finding);
        }
    }

    /**
     * Verifica end-to-end diretta col Cantiere 46: un pacchetto appena
     * provisionato tramite content-clusters:provision-non-public-package
     * deve comparire qui con lifecycle "updating" (non il default di
     * colonna 'complete' che il fix Codex del Cantiere 46 ha evitato) e
     * status NOT READY, dato che non ha ancora nessun contenuto reale.
     */
    public function test_a_package_provisioned_by_the_cantiere_46_command_appears_here_as_not_ready(): void
    {
        Artisan::call('content-clusters:provision-non-public-package mente-e-comportamento "Mente e comportamento" --apply');

        Artisan::call('content-clusters:publication-readiness --json');
        $decoded = json_decode(Artisan::output(), true);

        $this->assertCount(1, $decoded);
        $this->assertSame('mente-e-comportamento', $decoded[0]['slug']);
        $this->assertSame(ContentCluster::LIFECYCLE_UPDATING, $decoded[0]['lifecycle_status']);
        $this->assertSame('NOT READY', $decoded[0]['status']);
    }

    public function test_clusters_with_the_same_sort_order_fall_back_to_name(): void
    {
        ContentCluster::create(['name' => 'Zeta Bozza', 'slug' => 'zeta-bozza-cluster', 'is_active' => false, 'sort_order' => 0]);
        ContentCluster::create(['name' => 'Alfa Bozza', 'slug' => 'alfa-bozza-cluster', 'is_active' => false, 'sort_order' => 0]);

        Artisan::call('content-clusters:publication-readiness --json');
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(['Alfa Bozza', 'Zeta Bozza'], array_column($decoded, 'name'));
    }

    public function test_sort_order_takes_precedence_over_name(): void
    {
        ContentCluster::create(['name' => 'Zeta Priorita', 'slug' => 'zeta-priorita-cluster', 'is_active' => false, 'sort_order' => 0]);
        ContentCluster::create(['name' => 'Alfa Priorita', 'slug' => 'alfa-priorita-cluster', 'is_active' => false, 'sort_order' => 1]);

        Artisan::call('content-clusters:publication-readiness --json');
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(['Zeta Priorita', 'Alfa Priorita'], array_column($decoded, 'name'));
    }

    public function test_the_summary_counts_only_clusters_with_findings(): void
    {
        ContentCluster::create(['name' => 'Incompleto Riepilogo', 'slug' => 'incompleto-riepilogo', 'is_active' => false]);

        $this->artisan('content-clusters:publication-readiness')
            ->assertExitCode(0)
            ->expectsOutputToContain('Percorsi non pubblici: 1')
            ->expectsOutputToContain('Con almeno una criticità: 1');
    }

    public function test_the_command_never_writes_to_the_database(): void
    {
        $cluster = ContentCluster::create(['name' => 'Sola Lettura', 'slug' => 'sola-lettura', 'is_active' => false]);
        $before = $cluster->fresh()->getAttributes();

        $this->artisan('content-clusters:publication-readiness')->assertExitCode(0);

        $this->assertSame($before, $cluster->fresh()->getAttributes());
    }
}
