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

    /**
     * Codex (PR #622, P2): un controllo sulla sola sottostringa letterale
     * "metodo-scientifico" non rileva un nuovo membro con uno slug legato
     * a uno dei temi candidati che l'audit stesso elenca (ipotesi,
     * esperimento, falsificabilità, peer review, bias cognitivi, pensiero
     * critico) — es. un futuro `peer-review` o `pensiero-critico`
     * aggiunto a un cluster esistente non veniva rilevato. Corretto
     * controllando l'intera lista di parole chiave, non solo lo slug
     * esatto del Percorso.
     */
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

        $topicKeywords = ['metodo', 'scientific', 'ipotesi', 'esperiment', 'falsificabil', 'peer-review', 'peer review', 'bias-cognitiv', 'bias cognitiv', 'pensiero-critico', 'pensiero critico'];

        foreach ($clusters as $cluster) {
            $haystack = mb_strtolower($cluster['name'].' '.($cluster['slug'] ?? ''));

            foreach ($topicKeywords as $keyword) {
                $this->assertStringNotContainsString($keyword, $haystack);
            }

            foreach ($cluster['articles'] as $article) {
                $articleHaystack = mb_strtolower($article['slug']);

                foreach ($topicKeywords as $keyword) {
                    $this->assertStringNotContainsString(
                        $keyword,
                        $articleHaystack,
                        "Il membro '{$article['slug']}' sembra riconducibile a un tema candidato per il metodo scientifico elencato dall'audit — il verdetto NEEDS CONTENT va rivalutato."
                    );
                }
            }
        }

        $this->assertStringContainsString('content-clusters-initial.php', $doc);
    }

    /**
     * Codex (PR #622, P2): con solo RefreshDatabase (migrazioni fresche,
     * nessun seeder) questo tripwire dimostrava soltanto che le
     * migrazioni stesse non inseriscono un cluster — banalmente vero —
     * non che DatabaseSeeder (la fonte dati versionata reale) non ne
     * abbia mai seminato uno. Corretto eseguendo il seeder prima
     * dell'asserzione.
     */
    public function test_no_scientific_method_content_cluster_exists_in_the_database(): void
    {
        $doc = $this->doc();
        $this->seed();

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

    /**
     * Codex (PR #622, P2): l'audit affermava "nessuno slug adiacente al
     * tema in un altro Percorso" senza aver ispezionato gli snapshot
     * SQLite versionati (`storage/backups/database-2026-05-02-*.sqlite`)
     * — la stessa categoria di evidenza già usata dall'audit Fisica
     * Fondamentale. Quegli snapshot contengono davvero l'articolo
     * `ia-ricerca-scientifica-cnr-agenti-2025`, il cui corpo tocca
     * ipotesi/esperimenti/riproducibilità/verifica. L'audit è stato
     * corretto per citarlo come candidato debole/condizionale (non un
     * pillar): questo test verifica che il fatto citato resti vero, così
     * se lo snapshot o l'articolo sparissero senza aggiornare l'audit,
     * lo si noterebbe.
     */
    public function test_the_conditional_candidate_article_cited_by_the_audit_still_exists_in_the_versioned_snapshots(): void
    {
        $doc = $this->doc();
        $this->assertStringContainsString('ia-ricerca-scientifica-cnr-agenti-2025', $doc);

        $snapshots = glob(base_path('storage/backups/database-2026-05-02-*.sqlite'));
        $this->assertNotEmpty($snapshots, 'Gli snapshot SQLite versionati citati dall\'audit non esistono più.');

        foreach ($snapshots as $snapshot) {
            $pdo = new \PDO('sqlite:'.$snapshot);
            $stmt = $pdo->query("SELECT status FROM articles WHERE slug = 'ia-ricerca-scientifica-cnr-agenti-2025'");
            $status = $stmt->fetchColumn();

            $this->assertSame(
                'published',
                $status,
                "Lo snapshot {$snapshot} non contiene più (o non ha più status 'published') l'articolo citato dall'audit come candidato debole — l'inventario va rivalutato."
            );
        }
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
