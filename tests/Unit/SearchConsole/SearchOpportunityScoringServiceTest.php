<?php

namespace Tests\Unit\SearchConsole;

use App\Models\Article;
use App\Models\SearchConsoleQuery;
use App\Models\SearchZeroResultQuery;
use App\Models\User;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
