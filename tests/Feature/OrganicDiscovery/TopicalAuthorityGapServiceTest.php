<?php

namespace Tests\Feature\OrganicDiscovery;

use App\Models\Article;
use App\Models\ArticleConcept;
use App\Models\ArticleSearchProfile;
use App\Models\Concept;
use App\Models\ContentCluster;
use App\Models\SearchConsoleQuery;
use App\Models\User;
use App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService;
use App\Services\OrganicDiscovery\TopicalAuthorityGapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 8 (programma "Kairus Organic Discovery"): copre solo la logica
 * di composizione di TopicalAuthorityGapService (domanda Search Console +
 * prontezza già misurata altrove) — mai la correttezza degli stati di
 * OrganicDiscoveryReadinessService, già coperta dai suoi test dedicati.
 */
class TopicalAuthorityGapServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TopicalAuthorityGapService
    {
        return app(TopicalAuthorityGapService::class);
    }

    private function article(array $overrides = []): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo autorevolezza',
            'slug' => 'articolo-autorevolezza-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function withDemand(Article $article, int $impressions = 50): void
    {
        SearchConsoleQuery::create([
            'query' => 'query domanda '.$article->id,
            'page_url' => '',
            'article_id' => $article->id,
            'clicks' => 2,
            'impressions' => $impressions,
            'ctr' => 0.04,
            'position' => 8.0,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-07',
            'import_batch' => 'fixture',
            'imported_at' => now(),
        ]);
    }

    private function cluster(array $articleIds): ContentCluster
    {
        $cluster = ContentCluster::create([
            'name' => 'Percorso autorevolezza '.uniqid(),
            'slug' => 'percorso-autorevolezza-'.uniqid(),
            'is_active' => true,
        ]);

        foreach ($articleIds as $i => $articleId) {
            $cluster->articles()->attach($articleId, ['position' => ($i + 1) * 10]);
        }

        return $cluster;
    }

    private function concept(array $articleIds): Concept
    {
        $concept = Concept::create([
            'name' => 'Concetto autorevolezza '.uniqid(),
            'slug' => 'concetto-autorevolezza-'.uniqid(),
            'status' => Concept::STATUS_ACTIVE,
        ]);

        foreach ($articleIds as $articleId) {
            ArticleConcept::create(['article_id' => $articleId, 'concept_id' => $concept->id]);
        }

        return $concept;
    }

    public function test_an_empty_database_produces_no_findings(): void
    {
        $this->assertTrue($this->service()->auditClusters()->isEmpty());
        $this->assertTrue($this->service()->auditConcepts()->isEmpty());
    }

    public function test_a_cluster_with_only_draft_or_scheduled_articles_has_no_public_content(): void
    {
        $draft = $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $scheduled = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);
        $this->cluster([$draft->id, $scheduled->id]);

        $result = $this->service()->auditClusters()->first();

        $this->assertSame(TopicalAuthorityGapService::STATE_NO_PUBLIC_CONTENT, $result['state']);
        $this->assertSame(0, $result['article_count']);
    }

    public function test_a_public_cluster_with_no_search_console_data_has_demand_not_observed(): void
    {
        $article = $this->article();
        $this->cluster([$article->id]);

        $result = $this->service()->auditClusters()->first();

        $this->assertSame(TopicalAuthorityGapService::STATE_NO_DEMAND_OBSERVED, $result['state']);
        $this->assertSame(1, $result['article_count']);
        $this->assertSame(0, $result['impressions']);
    }

    public function test_demand_below_the_minimum_impressions_threshold_stays_not_observed(): void
    {
        $article = $this->article();
        $this->withDemand($article, impressions: 5);
        $this->cluster([$article->id]);

        $result = $this->service()->auditClusters()->first();

        $this->assertSame(TopicalAuthorityGapService::STATE_NO_DEMAND_OBSERVED, $result['state']);
    }

    public function test_demand_with_an_unready_article_is_a_weak_readiness_gap(): void
    {
        $article = $this->article();
        $this->withDemand($article, impressions: 50);
        $this->cluster([$article->id]);

        $result = $this->service()->auditClusters()->first();

        $this->assertSame(TopicalAuthorityGapService::STATE_GAP_WEAK_READINESS, $result['state']);
        $this->assertSame(50, $result['impressions']);
        $this->assertSame(2, $result['clicks']);
    }

    public function test_demand_with_all_ready_articles_is_covered(): void
    {
        // readyArticle() attacca già l'articolo al proprio Percorso attivo
        // (necessario per NO_ACTIVE_PATH nel corpus di prontezza): nessun
        // secondo Percorso qui, altrimenti auditClusters() ne conterrebbe due.
        $article = $this->readyArticle();
        $this->withDemand($article, impressions: 50);

        $result = $this->service()->auditClusters()->first();

        $this->assertSame(TopicalAuthorityGapService::STATE_COVERED, $result['state']);
        // Search Console demand è già presente (withDemand()), quindi
        // OrganicDiscoveryReadinessService classifica l'articolo "measured"
        // (non "ready", riservato a un articolo altrimenti identico senza
        // dati Search Console) — vedi il suo stesso test dedicato.
        $this->assertArrayHasKey(OrganicDiscoveryReadinessService::STATE_MEASURED, $result['readiness_counts']);
    }

    public function test_secondary_queries_are_aggregated_and_deduplicated_across_articles(): void
    {
        $first = $this->article();
        $second = $this->article();
        ArticleSearchProfile::create([
            'article_id' => $first->id,
            'primary_query' => 'query principale uno',
            'secondary_queries' => ['query condivisa', 'query solo primo'],
        ]);
        ArticleSearchProfile::create([
            'article_id' => $second->id,
            'primary_query' => 'query principale due',
            'secondary_queries' => ['query condivisa', 'query solo secondo'],
        ]);
        $this->cluster([$first->id, $second->id]);

        $result = $this->service()->auditClusters()->first();

        $this->assertCount(3, $result['secondary_queries']);
        $this->assertContains('query condivisa', $result['secondary_queries']);
        $this->assertContains('query solo primo', $result['secondary_queries']);
        $this->assertContains('query solo secondo', $result['secondary_queries']);
    }

    public function test_an_inactive_cluster_is_never_audited(): void
    {
        $article = $this->article();
        $cluster = $this->cluster([$article->id]);
        $cluster->update(['is_active' => false]);

        $this->assertTrue($this->service()->auditClusters()->isEmpty());
    }

    public function test_concepts_are_audited_with_the_same_states_and_exclude_non_public_articles(): void
    {
        $published = $this->article();
        $draft = $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $this->withDemand($published, impressions: 50);
        $this->concept([$published->id, $draft->id]);

        $result = $this->service()->auditConcepts()->first();

        $this->assertSame(1, $result['article_count']);
        $this->assertSame(TopicalAuthorityGapService::STATE_GAP_WEAK_READINESS, $result['state']);
    }

    public function test_an_inactive_concept_is_never_audited(): void
    {
        $article = $this->article();
        $concept = $this->concept([$article->id]);
        $concept->update(['status' => Concept::STATUS_INACTIVE]);

        $this->assertTrue($this->service()->auditConcepts()->isEmpty());
    }

    /**
     * Stesso corpus minimo di
     * OrganicDiscoveryReadinessServiceTest::readyArticle()/withCompleteSearchProfile()/
     * wireFullInternalDiscovery(), indispensabile per ottenere STATE_READY
     * invece di STATE_BLOCKED/STATE_NEEDS_WORK.
     */
    private function readyArticle(): Article
    {
        $article = $this->article([
            'title' => 'La scoperta di un nuovo esopianeta abitabile',
            'excerpt' => 'Un team internazionale ha individuato un pianeta nella zona abitabile di una stella vicina.',
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso sulla scoperta. ', 15).'</p><a href="/articolo/altro">Approfondisci</a>',
            'cover_image' => 'copertina.webp',
            'cover_alt' => 'Rappresentazione artistica dell\'esopianeta',
            'primary_sources' => 'https://www.nasa.gov/press-release/esopianeta',
            'read_minutes' => 3,
        ]);

        ArticleSearchProfile::create([
            'article_id' => $article->id,
            'primary_query' => 'esopianeta abitabile',
            'last_editorial_review_at' => now()->subDay(),
        ]);

        $linkingSource = $this->article([
            'title' => 'Un approfondimento correlato distinto',
            'excerpt' => 'Un contenuto editoriale distinto che collega ad approfondimenti correlati.',
            'body' => '<p>'.str_repeat('Testo di collegamento editoriale reale. ', 15).'</p><a href="/articolo/'.$article->slug.'">Approfondisci</a>',
        ]);
        // L'articolo linking-source ha bisogno del proprio corpus minimo
        // per non introdurre rumore, ma non entra in nessun Percorso qui.
        unset($linkingSource);

        $cluster = ContentCluster::create([
            'name' => 'Percorso interno di prova '.$article->id,
            'slug' => 'percorso-interno-prova-'.$article->id,
            'is_active' => true,
        ]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        return $article;
    }
}
