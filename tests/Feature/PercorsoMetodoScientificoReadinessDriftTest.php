<?php

namespace Tests\Feature;

use App\Models\ContentCluster;
use App\Services\ContentClusters\ContentClusterLifecycleReconciler;
use App\Services\ContentClusters\PercorsiActivationCalendarService;
use App\Services\ContentClusters\PercorsoCoverageAuditService;
use App\Services\ContentClusters\PercorsoPublicationReadinessService;
use App\Services\ContentClusters\PercorsoReorderSimulationService;
use App\Services\ContentClusters\PercorsoSubscriberNotificationReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Cantiere 79 (programma "100 cantieri Kairus", documentazione pura).
 * docs/PERCORSI_METODO_SCIENTIFICO_READINESS.md conclude NEEDS CONTENT e
 * non crea alcun ContentCluster: questo test verifica che i fatti citati
 * a sostegno di quel verdetto restino veri nel tempo — stesso principio
 * già in uso in RollbackRunbookDriftTest/BackupRestoreCiEvidenceDriftTest
 * — e che nessun contenuto reale sul metodo scientifico sia comparso nel
 * frattempo senza che il verdetto venga rivalutato di conseguenza (stesso
 * principio del tripwire "no artisan backup:restore*" di Cantiere 73).
 */
class PercorsoMetodoScientificoReadinessDriftTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_scientific_method_category_exists_in_the_taxonomy(): void
    {
        $doc = $this->doc();
        $categories = array_keys(config('laboratorio.categories'));

        $this->assertSame(
            ['intelligenza-artificiale', 'energia', 'salute', 'societa', 'spazio', 'fisica', 'ambiente'],
            $categories,
            'La tassonomia citata dall\'audit come "nessuna categoria metodo scientifico" è cambiata: il verdetto NEEDS CONTENT va rivalutato.'
        );

        foreach ($categories as $category) {
            $this->assertStringNotContainsStringIgnoringCase('metodo', $category);
            $this->assertStringNotContainsStringIgnoringCase('scientific', $category);
        }

        $this->assertStringContainsString('intelligenza-artificiale', $doc);
    }

    public function test_the_initial_content_clusters_map_still_has_no_scientific_method_candidate(): void
    {
        $doc = $this->doc();
        $clusters = config('content-clusters-initial');

        $this->assertCount(
            4,
            $clusters,
            'config/content-clusters-initial.php ha un numero diverso di Percorsi rispetto a quanto citato dall\'audit — verificare se il nuovo cluster riguarda il metodo scientifico.'
        );

        $slugs = array_column($clusters, 'slug');
        $this->assertSame(['ia-spiegata', 'spazio', 'scienza-quotidiana', 'energia-e-batterie'], $slugs);

        foreach ($clusters as $cluster) {
            $haystack = mb_strtolower($cluster['name'].' '.($cluster['slug'] ?? ''));
            $this->assertStringNotContainsString('metodo', $haystack);
            $this->assertStringNotContainsString('scientific', $haystack);

            foreach ($cluster['articles'] as $article) {
                $this->assertStringNotContainsString('metodo-scientifico', $article['slug']);
            }
        }

        $this->assertStringContainsString('content-clusters-initial.php', $doc);
    }

    public function test_no_scientific_method_content_cluster_exists_in_the_database(): void
    {
        $doc = $this->doc();

        $this->assertSame(
            0,
            ContentCluster::query()
                ->where('slug', 'like', '%metodo%')
                ->orWhere('name', 'like', '%metodo scientifico%')
                ->count(),
            'Esiste già un ContentCluster relativo al metodo scientifico: il verdetto NEEDS CONTENT di questo audit va rivalutato, non lasciato stantio.'
        );

        $this->assertStringContainsString('NEEDS CONTENT', $doc);
    }

    public function test_the_generic_percorso_tooling_cited_by_the_audit_still_exists(): void
    {
        $doc = $this->doc();

        foreach ([
            PercorsoCoverageAuditService::class,
            PercorsoPublicationReadinessService::class,
            PercorsoReorderSimulationService::class,
            PercorsoSubscriberNotificationReadinessService::class,
            PercorsiActivationCalendarService::class,
            ContentClusterLifecycleReconciler::class,
        ] as $class) {
            $this->assertTrue(class_exists($class), "{$class}, citato dall'audit come tooling generico già riusabile, non esiste più.");
            $this->assertStringContainsString(class_basename($class), $doc);
        }
    }

    public function test_the_backfill_command_cited_by_the_audit_is_still_registered_and_dry_run_by_default(): void
    {
        $doc = $this->doc();

        $this->assertContains('content-clusters:backfill-initial', array_keys(Artisan::all()));
        $this->assertStringContainsString('content-clusters:backfill-initial', $doc);
        $this->assertStringContainsString('--apply', $doc);
    }

    private function doc(): string
    {
        $content = file_get_contents(base_path('docs/PERCORSI_METODO_SCIENTIFICO_READINESS.md'));
        $this->assertIsString($content);

        return $content;
    }
}
