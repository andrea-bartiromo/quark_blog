<?php

namespace Tests\Unit\SearchConsole;

use App\Models\Article;
use App\Models\SearchConsoleQuery;
use App\Models\SearchZeroResultQuery;
use App\Models\User;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SearchOpportunityScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $periodStart;

    private Carbon $periodEnd;

    private int $matchedArticleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->periodStart = Carbon::parse('2026-08-01');
        $this->periodEnd = Carbon::parse('2026-08-07');

        $author = User::factory()->create(['role' => 'author']);
        $this->matchedArticleId = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo corrispondente',
            'slug' => 'articolo-corrispondente',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ])->id;
    }

    /**
     * Di default collegata a un articolo reale, cosi' i test sui segnali
     * riga-per-riga (CTR/posizione) non attivano incidentalmente anche
     * no_strong_landing_page — segnale indipendente e ortogonale, testato
     * a parte. I test dedicati a no_strong_landing_page sovrascrivono
     * esplicitamente article_id a null.
     */
    private function row(array $overrides = []): SearchConsoleQuery
    {
        return SearchConsoleQuery::create(array_merge([
            'query' => 'query di test',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => $this->matchedArticleId,
            'clicks' => 1,
            'impressions' => 50,
            'ctr' => 0.02,
            'position' => 10,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'import_batch' => 'test-batch',
            'imported_at' => now(),
        ], $overrides));
    }

    public function test_below_minimum_evidence_threshold_generates_no_opportunity_regardless_of_metrics(): void
    {
        $this->row([
            'impressions' => SearchOpportunityScoringService::MIN_IMPRESSIONS - 1,
            'ctr' => 0.0001,
            'position' => 25,
        ]);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);

        $this->assertTrue($opportunities->isEmpty());
    }

    public function test_high_impression_low_ctr_beyond_page_two(): void
    {
        $this->row(['impressions' => 200, 'ctr' => 0.001, 'position' => 25]);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);

        $this->assertCount(1, $opportunities);
        $this->assertSame(SearchOpportunityScoringService::TYPE_HIGH_IMPRESSION_LOW_CTR, $opportunities->first()->type);
        $this->assertGreaterThan(0, $opportunities->first()->score);
    }

    public function test_good_position_low_ctr_on_page_one(): void
    {
        $this->row(['impressions' => 100, 'ctr' => 0.03, 'position' => 3]);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);

        $this->assertCount(1, $opportunities);
        $this->assertSame(SearchOpportunityScoringService::TYPE_GOOD_POSITION_LOW_CTR, $opportunities->first()->type);
    }

    public function test_near_page_one_when_ctr_is_in_line_with_expectations(): void
    {
        $this->row(['impressions' => 120, 'ctr' => 0.01, 'position' => 12]);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);

        $this->assertCount(1, $opportunities);
        $this->assertSame(SearchOpportunityScoringService::TYPE_NEAR_PAGE_ONE, $opportunities->first()->type);
        $this->assertEqualsWithDelta(120 / 12, $opportunities->first()->score, 0.01);
    }

    public function test_a_strong_page_one_result_generates_no_opportunity(): void
    {
        // Posizione 1, CTR alto: nessuna opportunità, e' gia' un risultato forte.
        $this->row(['impressions' => 500, 'ctr' => 0.30, 'position' => 1]);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);

        $this->assertTrue($opportunities->isEmpty());
    }

    public function test_no_strong_landing_page_when_no_row_for_the_query_matches_an_article(): void
    {
        $this->row(['query' => 'argomento senza articolo', 'impressions' => 40, 'article_id' => null]);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);

        $noLandingPage = $opportunities->firstWhere('type', SearchOpportunityScoringService::TYPE_NO_STRONG_LANDING_PAGE);
        $this->assertNotNull($noLandingPage);
        $this->assertSame(40, $noLandingPage->impressions);
    }

    public function test_no_strong_landing_page_is_not_flagged_when_the_query_has_a_matched_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo reale',
            'slug' => 'articolo-reale',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->row([
            'query' => 'argomento con articolo',
            'impressions' => 40,
            'ctr' => 0.05,
            'position' => 5,
            'article_id' => $article->id,
        ]);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);

        $this->assertNull($opportunities->firstWhere('type', SearchOpportunityScoringService::TYPE_NO_STRONG_LANDING_PAGE));
    }

    public function test_rising_query_requires_a_previous_period_and_meaningful_growth(): void
    {
        $previousStart = Carbon::parse('2026-07-01');
        $previousEnd = Carbon::parse('2026-07-07');

        SearchConsoleQuery::create([
            'query' => 'trend crescente',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => null,
            'clicks' => 1,
            'impressions' => 20,
            'ctr' => 0.05,
            'position' => 5,
            'period_start' => $previousStart,
            'period_end' => $previousEnd,
            'import_batch' => 'prev-batch',
            'imported_at' => now(),
        ]);

        $this->row(['query' => 'trend crescente', 'impressions' => 60, 'ctr' => 0.20, 'position' => 3]);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod(
            $this->periodStart,
            $this->periodEnd,
            $previousStart,
            $previousEnd,
        );

        $rising = $opportunities->firstWhere('type', SearchOpportunityScoringService::TYPE_RISING_QUERY);
        $this->assertNotNull($rising);
        $this->assertEqualsWithDelta(2.0, $rising->score, 0.01); // (60-20)/20 = 2.0 = +200%
    }

    public function test_rising_query_is_not_flagged_without_a_previous_period(): void
    {
        $this->row(['query' => 'senza confronto', 'impressions' => 60]);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);

        $this->assertNull($opportunities->firstWhere('type', SearchOpportunityScoringService::TYPE_RISING_QUERY));
    }

    public function test_rising_query_ignores_growth_from_a_negligible_previous_base(): void
    {
        $previousStart = Carbon::parse('2026-07-01');
        $previousEnd = Carbon::parse('2026-07-07');

        SearchConsoleQuery::create([
            'query' => 'falso allarme',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => null,
            'clicks' => 0,
            'impressions' => 1,
            'ctr' => 0,
            'position' => 30,
            'period_start' => $previousStart,
            'period_end' => $previousEnd,
            'import_batch' => 'prev-batch',
            'imported_at' => now(),
        ]);

        $this->row(['query' => 'falso allarme', 'impressions' => 25]);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod(
            $this->periodStart,
            $this->periodEnd,
            $previousStart,
            $previousEnd,
        );

        $this->assertNull($opportunities->firstWhere('type', SearchOpportunityScoringService::TYPE_RISING_QUERY));
    }

    public function test_opportunities_are_sorted_by_score_descending(): void
    {
        $this->row(['query' => 'basso punteggio', 'impressions' => 21, 'ctr' => 0.001, 'position' => 25]);
        $this->row(['query' => 'alto punteggio', 'impressions' => 1000, 'ctr' => 0.0001, 'position' => 25]);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);

        $this->assertSame('alto punteggio', $opportunities->first()->query);
    }

    // ── Mission 32 — Search Opportunity Pipeline ────────────────────────

    public function test_a_zero_result_query_below_the_minimum_hits_threshold_is_not_flagged(): void
    {
        SearchZeroResultQuery::create([
            'normalized_query' => 'sotto soglia',
            'hit_count' => SearchOpportunityScoringService::MIN_INTERNAL_ZERO_RESULT_HITS - 1,
        ]);

        $opportunities = app(SearchOpportunityScoringService::class)->internalZeroResultOpportunities(collect());

        $this->assertTrue($opportunities->isEmpty());
    }

    public function test_a_zero_result_query_at_or_above_the_threshold_becomes_an_opportunity(): void
    {
        SearchZeroResultQuery::create([
            'normalized_query' => 'buco nero rotante',
            'hit_count' => SearchOpportunityScoringService::MIN_INTERNAL_ZERO_RESULT_HITS,
        ]);

        $opportunities = app(SearchOpportunityScoringService::class)->internalZeroResultOpportunities(collect());

        $this->assertCount(1, $opportunities);
        $opportunity = $opportunities->first();
        $this->assertSame(SearchOpportunityScoringService::TYPE_INTERNAL_ZERO_RESULT_SEARCH, $opportunity->type);
        $this->assertSame('buco nero rotante', $opportunity->query);
        $this->assertSame(SearchOpportunityScoringService::MIN_INTERNAL_ZERO_RESULT_HITS, $opportunity->impressions);
        $this->assertSame((float) SearchOpportunityScoringService::MIN_INTERNAL_ZERO_RESULT_HITS, $opportunity->score);
        $this->assertNull($opportunity->article);
    }

    /**
     * "Avoid duplicate opportunity creation": la stessa query, già
     * segnalata come TYPE_NO_STRONG_LANDING_PAGE dal lato Search Console,
     * non deve generare una seconda opportunità qui — stesso concetto
     * editoriale, due fonti diverse, un solo item da gestire.
     */
    public function test_a_query_already_flagged_as_no_strong_landing_page_is_not_duplicated(): void
    {
        SearchZeroResultQuery::create([
            'normalized_query' => 'Argomento Senza Articolo',
            'hit_count' => 10,
        ]);

        $this->row(['query' => 'argomento senza articolo', 'impressions' => 40, 'article_id' => null]);
        $existing = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);

        $internal = app(SearchOpportunityScoringService::class)->internalZeroResultOpportunities($existing);

        $this->assertTrue($internal->isEmpty());
    }

    public function test_a_distinct_query_not_flagged_elsewhere_still_becomes_an_internal_opportunity(): void
    {
        SearchZeroResultQuery::create(['normalized_query' => 'query distinta', 'hit_count' => 5]);

        $this->row(['query' => 'query non correlata', 'impressions' => 40, 'article_id' => null]);
        $existing = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);

        $internal = app(SearchOpportunityScoringService::class)->internalZeroResultOpportunities($existing);

        $this->assertCount(1, $internal);
        $this->assertSame('query distinta', $internal->first()->query);
    }

    public function test_query_only_export_does_not_claim_that_a_landing_page_is_missing(): void
    {
        SearchConsoleQuery::query()->create([
            'query' => 'kairus',
            'page_url' => '',
            'article_id' => null,
            'clicks' => 1,
            'impressions' => 43,
            'ctr' => 0.0233,
            'position' => 2.44,
            'period_start' => '2026-05-25',
            'period_end' => '2026-08-24',
            'import_batch' => (string) Str::uuid(),
            'imported_at' => now(),
        ]);

        $opportunities = app(
            SearchOpportunityScoringService::class
        )->forPeriod(
            Carbon::parse('2026-05-25'),
            Carbon::parse('2026-08-24')
        );

        $this->assertFalse(
            $opportunities->contains(
                fn ($opportunity) => $opportunity->type
                    === SearchOpportunityScoringService::TYPE_NO_STRONG_LANDING_PAGE
                    && $opportunity->query === 'kairus'
            )
        );
    }

    // ── Cantiere 5 ("Kairus Organic Discovery") — cannibalizzazione ────

    private function publicArticle(string $slug): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo '.$slug,
            'slug' => $slug,
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_two_public_articles_receiving_impressions_for_the_same_query_are_flagged_as_cannibalization(): void
    {
        $second = $this->publicArticle('secondo-articolo');

        $this->row([
            'query' => 'query condivisa',
            'page_url' => 'https://kairus.it/articolo/primo',
            'article_id' => $this->matchedArticleId,
            'impressions' => 30,
            'clicks' => 2,
        ]);
        $this->row([
            'query' => 'query condivisa',
            'page_url' => 'https://kairus.it/articolo/secondo-articolo',
            'article_id' => $second->id,
            'impressions' => 10,
            'clicks' => 1,
        ]);

        $findings = app(SearchOpportunityScoringService::class)->cannibalizationFindingsForPeriod($this->periodStart, $this->periodEnd);

        $this->assertCount(1, $findings);
        $finding = $findings->first();
        $this->assertSame('query condivisa', $finding->query);
        $this->assertCount(2, $finding->competitors);
        $this->assertSame($this->matchedArticleId, $finding->primaryArticle->id);
        $this->assertSame(30, $finding->competitors->first()['impressions']);
        $this->assertSame($second->id, $finding->competitors->last()['article']->id);

        $opportunities = app(SearchOpportunityScoringService::class)->forPeriod($this->periodStart, $this->periodEnd);
        $cannibalization = $opportunities->firstWhere('type', SearchOpportunityScoringService::TYPE_SEARCH_CANNIBALIZATION);
        $this->assertNotNull($cannibalization);
        $this->assertSame(40, $cannibalization->impressions);
        $this->assertSame(10.0, $cannibalization->score); // impression degli articoli non primari
        $this->assertNull($cannibalization->pageUrl);
    }

    /**
     * Codex, PR #592 (P1): l'identità dell'opportunità (type|query|pageUrl)
     * deve restare stabile anche quando la classifica dei concorrenti
     * cambia da un periodo all'altro — altrimenti una decisione già
     * registrata (e la sua misurazione a 28/90gg) sparirebbe non appena un
     * articolo diverso diventasse "primario".
     */
    public function test_the_cannibalization_opportunity_key_stays_stable_when_the_primary_article_changes(): void
    {
        $second = $this->publicArticle('chiave-stabile-secondo');

        // Periodo 1: il primo articolo (matchedArticleId) e' il primario.
        $this->row([
            'query' => 'query classifica variabile',
            'page_url' => 'https://kairus.it/articolo/primo',
            'article_id' => $this->matchedArticleId,
            'impressions' => 30,
        ]);
        $this->row([
            'query' => 'query classifica variabile',
            'page_url' => 'https://kairus.it/articolo/chiave-stabile-secondo',
            'article_id' => $second->id,
            'impressions' => 10,
        ]);

        $keyPeriod1 = app(SearchOpportunityScoringService::class)
            ->cannibalizationFindingsForPeriod($this->periodStart, $this->periodEnd)
            ->first()->opportunity->key;

        // Periodo 2: la classifica si inverte, il secondo articolo diventa primario.
        $periodStart2 = Carbon::parse('2026-08-08');
        $periodEnd2 = Carbon::parse('2026-08-14');
        SearchConsoleQuery::create([
            'query' => 'query classifica variabile', 'page_url' => 'https://kairus.it/articolo/primo', 'article_id' => $this->matchedArticleId,
            'clicks' => 0, 'impressions' => 10, 'ctr' => 0, 'position' => 10,
            'period_start' => $periodStart2, 'period_end' => $periodEnd2, 'import_batch' => 'p2', 'imported_at' => now(),
        ]);
        SearchConsoleQuery::create([
            'query' => 'query classifica variabile', 'page_url' => 'https://kairus.it/articolo/chiave-stabile-secondo', 'article_id' => $second->id,
            'clicks' => 0, 'impressions' => 30, 'ctr' => 0, 'position' => 5,
            'period_start' => $periodStart2, 'period_end' => $periodEnd2, 'import_batch' => 'p2', 'imported_at' => now(),
        ]);

        $findingPeriod2 = app(SearchOpportunityScoringService::class)
            ->cannibalizationFindingsForPeriod($periodStart2, $periodEnd2)
            ->first();

        $this->assertSame($second->id, $findingPeriod2->primaryArticle->id); // la classifica si e' davvero invertita
        $this->assertSame($keyPeriod1, $findingPeriod2->opportunity->key);
    }

    public function test_cannibalization_position_is_weighted_by_impressions_not_a_plain_average(): void
    {
        $second = $this->publicArticle('posizione-pesata');

        // Due righe per lo STESSO articolo nello stesso gruppo query: una
        // media aritmetica darebbe (1+10)/2=5.5, quella pesata sulle
        // impression da' un risultato molto piu' vicino a 10.
        $this->row([
            'query' => 'query posizione pesata',
            'page_url' => 'https://kairus.it/articolo/primo',
            'article_id' => $this->matchedArticleId,
            'impressions' => 1,
            'position' => 1,
        ]);
        $this->row([
            'query' => 'query posizione pesata',
            'page_url' => 'https://kairus.it/articolo/primo?utm_source=test',
            'article_id' => $this->matchedArticleId,
            'impressions' => 99,
            'position' => 10,
        ]);
        $this->row([
            'query' => 'query posizione pesata',
            'page_url' => 'https://kairus.it/articolo/posizione-pesata',
            'article_id' => $second->id,
            'impressions' => 20,
            'position' => 6,
        ]);

        $finding = app(SearchOpportunityScoringService::class)
            ->cannibalizationFindingsForPeriod($this->periodStart, $this->periodEnd)
            ->first();

        $primary = $finding->competitors->firstWhere('article.id', $this->matchedArticleId);
        $this->assertEqualsWithDelta(9.9, $primary['position'], 0.05);
    }

    public function test_a_single_article_matched_to_a_query_is_not_flagged_as_cannibalization(): void
    {
        $this->row(['query' => 'query singola', 'impressions' => 50]);

        $findings = app(SearchOpportunityScoringService::class)->cannibalizationFindingsForPeriod($this->periodStart, $this->periodEnd);

        $this->assertTrue($findings->isEmpty());
    }

    public function test_cannibalization_requires_minimum_combined_evidence(): void
    {
        $second = $this->publicArticle('sotto-soglia');

        $this->row(['query' => 'poca evidenza', 'article_id' => $this->matchedArticleId, 'impressions' => 5]);
        $this->row(['query' => 'poca evidenza', 'article_id' => $second->id, 'impressions' => 5]);

        $findings = app(SearchOpportunityScoringService::class)->cannibalizationFindingsForPeriod($this->periodStart, $this->periodEnd);

        $this->assertTrue($findings->isEmpty());
    }

    public function test_a_non_public_competing_article_is_never_counted(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $draft = Article::create([
            'user_id' => $author->id,
            'title' => 'Bozza',
            'slug' => 'bozza-competitor',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_DRAFT,
            'published_at' => null,
        ]);

        $this->row(['query' => 'query con bozza', 'article_id' => $this->matchedArticleId, 'impressions' => 30]);
        $this->row(['query' => 'query con bozza', 'article_id' => $draft->id, 'impressions' => 30]);

        $findings = app(SearchOpportunityScoringService::class)->cannibalizationFindingsForPeriod($this->periodStart, $this->periodEnd);

        $this->assertTrue($findings->isEmpty());
    }

    public function test_a_brand_query_is_never_flagged_as_cannibalization(): void
    {
        $second = $this->publicArticle('brand-competitor');
        $brandQuery = mb_strtolower((string) config('app.name')).' guida';

        $this->row(['query' => $brandQuery, 'article_id' => $this->matchedArticleId, 'impressions' => 50]);
        $this->row(['query' => $brandQuery, 'article_id' => $second->id, 'impressions' => 50]);

        $findings = app(SearchOpportunityScoringService::class)->cannibalizationFindingsForPeriod($this->periodStart, $this->periodEnd);

        $this->assertTrue($findings->isEmpty());
    }
}
