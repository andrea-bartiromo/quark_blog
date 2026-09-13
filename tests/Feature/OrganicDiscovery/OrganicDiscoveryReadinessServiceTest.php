<?php

namespace Tests\Feature\OrganicDiscovery;

use App\Models\Article;
use App\Models\ArticleSearchProfile;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\SearchConsoleQuery;
use App\Models\User;
use App\Services\EditorialQuality\EditorialQualityChecker;
use App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cantiere 3 (programma "Kairus Organic Discovery"). Copre lo stato
 * spiegabile prodotto da OrganicDiscoveryReadinessService — mai un
 * punteggio opaco — e verifica rigorosamente che nessun contenuto non
 * pubblico venga mai incluso.
 */
class OrganicDiscoveryReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OrganicDiscoveryReadinessService
    {
        return app(OrganicDiscoveryReadinessService::class);
    }

    /** Categoria pubblica condivisa da tutti gli articoli "pronti" di questo test. */
    private function publicCategory(): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => 'organic-readiness-category'],
            ['name' => 'Organic Readiness Category'],
        );
    }

    /**
     * Articolo che supera TUTTI i controlli essenziali (e la maggior
     * parte dei controlli raccomandati) di qualità editoriale e ha un
     * percorso di scoperta interna di base (categoria pubblica) — stessa
     * composizione minima già usata da
     * EditorialQualityCheckerTest::completeArticle(), incluso un
     * collegamento interno in uscita nel corpo (altrimenti
     * internalLinksCheck segnala WARNING anche su un articolo altrimenti
     * completo).
     */
    private function readyArticle(string $slug, array $overrides = []): Article
    {
        $category = $this->publicCategory();

        return Article::withoutEvents(fn () => Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'La scoperta di un nuovo esopianeta abitabile',
            'slug' => $slug,
            'excerpt' => 'Un team internazionale ha individuato un pianeta nella zona abitabile di una stella vicina.',
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso sulla scoperta. ', 15).'</p><a href="/articolo/altro">Approfondisci</a>',
            'category' => $category->slug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'cover_image' => 'copertina.webp',
            'cover_alt' => 'Rappresentazione artistica dell\'esopianeta',
            'primary_sources' => 'https://www.nasa.gov/press-release/esopianeta',
            'read_minutes' => 3,
        ], $overrides)));
    }

    private function withCompleteSearchProfile(Article $article, array $overrides = []): ArticleSearchProfile
    {
        return ArticleSearchProfile::create(array_merge([
            'article_id' => $article->id,
            'primary_query' => 'esopianeta abitabile',
            'last_editorial_review_at' => now()->subDay(),
        ], $overrides));
    }

    /**
     * Completa i percorsi di scoperta interna dell'articolo con un
     * collegamento in entrata reale (da un secondo articolo pubblicato) e
     * un Percorso attivo — stessa composizione di
     * ArticleDiscoveryAuditServiceTest::test_public_percorso_prefix_and_real_body_incoming_link_are_counted_separately(),
     * indispensabile per azzerare NO_BODY_INCOMING_LINKS/NO_ACTIVE_PATH.
     */
    private function wireFullInternalDiscovery(Article $article): void
    {
        $this->readyArticle('linking-source-for-'.$article->id, [
            'title' => 'Un approfondimento correlato distinto '.$article->id,
            'excerpt' => 'Un contenuto editoriale distinto che collega ad approfondimenti correlati.',
            'body' => '<p>'.str_repeat('Testo di collegamento editoriale reale. ', 15).'</p><a href="/articolo/'.$article->slug.'">Approfondisci</a>',
        ]);

        $cluster = ContentCluster::create([
            'name' => 'Percorso di prova '.$article->id,
            'slug' => 'percorso-prova-'.$article->id,
            'is_active' => true,
        ]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
    }

    public function test_a_fully_compliant_article_with_no_search_console_data_is_ready(): void
    {
        $article = $this->readyArticle('organic-ready-1');
        $this->withCompleteSearchProfile($article);
        $this->wireFullInternalDiscovery($article);

        $row = $this->service()->forArticle($article);

        $this->assertNotNull($row);
        $this->assertSame([], $row['findings']);
        $this->assertSame(OrganicDiscoveryReadinessService::STATE_READY, $row['state']);
        $this->assertFalse($row['has_search_console_data']);
    }

    public function test_a_fully_compliant_article_with_search_console_data_is_measured(): void
    {
        $article = $this->readyArticle('organic-ready-measured');
        $this->withCompleteSearchProfile($article);
        $this->wireFullInternalDiscovery($article);

        SearchConsoleQuery::create([
            'query' => 'esopianeta abitabile',
            'page_url' => '',
            'article_id' => $article->id,
            'clicks' => 5,
            'impressions' => 100,
            'ctr' => 0.05,
            'position' => 8.0,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'import_batch' => 'batch-1',
            'imported_at' => now(),
        ]);

        $row = $this->service()->forArticle($article);

        $this->assertSame([], $row['findings']);
        $this->assertSame(OrganicDiscoveryReadinessService::STATE_MEASURED, $row['state']);
        $this->assertTrue($row['has_search_console_data']);
    }

    public function test_a_missing_search_profile_is_a_needs_work_finding_not_a_block(): void
    {
        $article = $this->readyArticle('organic-missing-profile');

        $row = $this->service()->forArticle($article);

        $this->assertSame(OrganicDiscoveryReadinessService::STATE_NEEDS_WORK, $row['state']);
        $this->assertContains('SEARCH_PROFILE_MISSING', $row['findings']);
    }

    public function test_a_search_profile_without_a_primary_query_is_incomplete_not_missing(): void
    {
        $article = $this->readyArticle('organic-incomplete-profile');
        $this->withCompleteSearchProfile($article, ['primary_query' => null]);

        $row = $this->service()->forArticle($article);

        $this->assertContains('SEARCH_PROFILE_INCOMPLETE', $row['findings']);
        $this->assertNotContains('SEARCH_PROFILE_MISSING', $row['findings']);
    }

    public function test_no_freshness_signal_at_all_is_flagged_and_nothing_else(): void
    {
        $article = $this->readyArticle('organic-no-freshness');
        $this->withCompleteSearchProfile($article, ['last_editorial_review_at' => null]);
        $this->wireFullInternalDiscovery($article);

        $row = $this->service()->forArticle($article);

        $this->assertSame(['FRESHNESS_NOT_TRACKED'], $row['findings']);
        $this->assertSame(OrganicDiscoveryReadinessService::STATE_NEEDS_WORK, $row['state']);
    }

    public function test_a_qualifying_post_publication_revision_counts_as_a_freshness_signal(): void
    {
        $article = $this->readyArticle('organic-revision-freshness');
        $this->withCompleteSearchProfile($article, ['last_editorial_review_at' => null]);

        // Stessa regola di ArticleRevisionTransparencyService: una
        // revisione dopo la pubblicazione che cambia davvero title/excerpt/
        // body/category conta come segnale di freschezza, anche senza una
        // data esplicita nel profilo di ricerca.
        $article->revisions()->create([
            'title' => 'Titolo precedente diverso',
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
            'created_at' => $article->published_at->copy()->addHour(),
        ]);

        $row = $this->service()->forArticle($article);

        $this->assertNotContains('FRESHNESS_NOT_TRACKED', $row['findings']);
    }

    public function test_missing_cover_is_an_essential_quality_failure_that_blocks(): void
    {
        $article = $this->readyArticle('organic-blocked', ['cover_image' => null, 'cover_alt' => null]);
        $this->withCompleteSearchProfile($article);

        $row = $this->service()->forArticle($article);

        $this->assertSame(OrganicDiscoveryReadinessService::STATE_BLOCKED, $row['state']);
        $this->assertContains('QUALITY_INCOMPLETE', $row['findings']);
    }

    public function test_duplicate_effective_metadata_across_two_articles_is_flagged_on_both(): void
    {
        $first = $this->readyArticle('organic-duplicate-a', ['canonical_url' => 'https://kairus.test/duplicato']);
        $second = $this->readyArticle('organic-duplicate-b', ['canonical_url' => 'https://kairus.test/duplicato']);
        $this->withCompleteSearchProfile($first);
        $this->withCompleteSearchProfile($second);

        $rows = $this->service()->auditAll()->keyBy('article_id');

        $this->assertContains('DUPLICATE_METADATA', $rows[$first->id]['findings']);
        $this->assertContains('DUPLICATE_METADATA', $rows[$second->id]['findings']);
    }

    public function test_a_weak_discovery_article_is_needs_work_not_blocked(): void
    {
        // Categoria esistente in DB ma resa bozza (stesso schema di
        // ArticleDiscoveryAuditServiceTest::test_a_config_legacy_category_hidden_in_db_is_not_counted_as_a_category_path):
        // categoryCheck (EditorialQualityChecker) accetta qualunque riga
        // Category esistente indipendentemente dallo stato — mai un FAIL
        // essenziale — mentre ArticleDiscoveryAuditService la esclude dai
        // percorsi pubblici navigabili (isPubliclyVisible() false). Nessun
        // Percorso attivo, nessun link interno in entrata: WEAK_DISCOVERY
        // (un solo percorso residuo, l'archivio), mai ZERO_DISCOVERY_PATHS
        // (l'archivio Notizie è sempre un percorso reale) — quindi mai
        // bloccante da solo.
        $draftCategory = Category::create(['name' => 'Organic Weak Discovery Category', 'slug' => 'organic-weak-discovery-category', 'status' => Category::STATUS_DRAFT]);

        $article = $this->readyArticle('organic-weak-discovery', ['category' => $draftCategory->slug]);
        $this->withCompleteSearchProfile($article);

        $row = $this->service()->forArticle($article);

        $this->assertSame(OrganicDiscoveryReadinessService::STATE_NEEDS_WORK, $row['state']);
        $this->assertContains('WEAK_DISCOVERY', $row['findings']);
    }

    public function test_draft_and_scheduled_articles_are_never_included_in_the_aggregate_audit(): void
    {
        $draft = $this->readyArticle('organic-draft', ['status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $scheduled = $this->readyArticle('organic-scheduled', ['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);
        $published = $this->readyArticle('organic-published-control');

        $ids = $this->service()->auditAll()->pluck('article_id');

        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($scheduled->id, $ids);
        $this->assertContains($published->id, $ids);
    }

    public function test_for_article_returns_null_for_a_non_public_article(): void
    {
        $draft = $this->readyArticle('organic-draft-for-article', ['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $this->assertNull($this->service()->forArticle($draft));
    }

    public function test_preview_for_article_works_on_an_unpublished_draft_without_a_corpus_scan(): void
    {
        $draft = $this->readyArticle('organic-preview-draft', ['status' => Article::STATUS_DRAFT, 'published_at' => null, 'cover_image' => null, 'cover_alt' => null]);

        $qualityReport = app(EditorialQualityChecker::class)->check($draft);
        $preview = $this->service()->previewForArticle($draft, $qualityReport);

        $this->assertContains('QUALITY_INCOMPLETE', $preview['findings']);
        $this->assertContains('SEARCH_PROFILE_MISSING', $preview['findings']);
        $this->assertContains('structured_data_presence', $preview['not_measured']);
        $this->assertContains('internal_discovery_paths', $preview['not_measured']);
    }

    public function test_preview_for_article_has_no_findings_for_a_fully_compliant_draft(): void
    {
        $draft = $this->readyArticle('organic-preview-compliant-draft', ['status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $this->withCompleteSearchProfile($draft);

        $qualityReport = app(EditorialQualityChecker::class)->check($draft);
        $preview = $this->service()->previewForArticle($draft, $qualityReport);

        $this->assertSame([], $preview['findings']);
    }

    /**
     * Rete di sicurezza contro una regressione N+1: auditAll() compone
     * più audit già a query costante (ArticleDiscoveryAuditService,
     * SeoMetadataQualityAuditService, EditorialQualityChecker con indice
     * precalcolato) e aggiunge solo query aggregate proprie (una per
     * ArticleRevisionTransparencyService::lastEditorialUpdates(), una per
     * articleIdsWithLatestSearchConsoleData()) — mai una query per
     * articolo. Stesso principio/soglia di
     * EditorialQualityAuditPerformanceTest.
     */
    public function test_the_query_count_does_not_grow_linearly_with_the_number_of_articles(): void
    {
        $this->seedArticles(20, 'a');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service()->auditAll();
        $countWith20 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->seedArticles(80, 'b');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service()->auditAll();
        $countWith100 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $countWith20,
            $countWith100,
            "Con 20 articoli: {$countWith20} query. Con 100: {$countWith100} query. Devono coincidere."
        );
    }

    private function seedArticles(int $count, string $prefix): void
    {
        for ($i = 0; $i < $count; $i++) {
            $article = $this->readyArticle('organic-perf-'.$prefix.'-'.$i, [
                'title' => 'Articolo performance ricerca organica '.$prefix.' '.$i,
            ]);
            $this->withCompleteSearchProfile($article);
        }
    }
}
