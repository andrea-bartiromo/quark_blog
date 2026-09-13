<?php

namespace Tests\Feature\SearchConsole;

use App\Models\Article;
use App\Models\SearchConsoleQuery;
use App\Models\User;
use App\Services\SearchConsole\SearchConsoleBaselineReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cantiere 1 (programma "Kairus Organic Discovery"). Il servizio riusa
 * SearchOpportunityScoringService per le opportunità già scorate — qui si
 * prova solo cio' che questo report aggiunge davvero: totali, top landing
 * page, query non-brand e il degrado esplicito per dispositivo/Paese.
 */
class SearchConsoleBaselineReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): SearchConsoleBaselineReportService
    {
        return app(SearchConsoleBaselineReportService::class);
    }

    /** @param  array<string, mixed>  $overrides */
    private function row(array $overrides = []): SearchConsoleQuery
    {
        return SearchConsoleQuery::create(array_merge([
            'query' => 'query di prova',
            'page_url' => '',
            'article_id' => null,
            'clicks' => 5,
            'impressions' => 100,
            'ctr' => 0.05,
            'position' => 8.0,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'import_batch' => 'batch-1',
            'imported_at' => now(),
        ], $overrides));
    }

    public function test_totals_are_summed_and_position_is_weighted_by_impressions(): void
    {
        $this->row(['clicks' => 10, 'impressions' => 100, 'position' => 4.0]);
        $this->row(['query' => 'seconda', 'clicks' => 5, 'impressions' => 300, 'position' => 12.0]);

        $report = $this->service()->report(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame(15, $report['totals']['clicks']);
        $this->assertSame(400, $report['totals']['impressions']);
        $this->assertEqualsWithDelta(15 / 400, $report['totals']['ctr'], 0.0001);
        // (4*100 + 12*300) / 400 = 10
        $this->assertEqualsWithDelta(10.0, $report['totals']['position'], 0.0001);
    }

    public function test_top_landing_pages_are_aggregated_by_page_and_sorted_by_clicks(): void
    {
        $this->row(['page_url' => 'https://kairus.it/articolo/uno', 'clicks' => 3, 'impressions' => 50]);
        $this->row(['query' => 'altra query stessa pagina', 'page_url' => 'https://kairus.it/articolo/uno', 'clicks' => 2, 'impressions' => 30]);
        $this->row(['query' => 'pagina due', 'page_url' => 'https://kairus.it/articolo/due', 'clicks' => 1, 'impressions' => 20]);

        $pages = $this->service()->report(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'))['top_landing_pages'];

        $this->assertCount(2, $pages);
        $this->assertSame('https://kairus.it/articolo/uno', $pages[0]['page_url']);
        $this->assertSame(5, $pages[0]['clicks']);
        $this->assertSame(80, $pages[0]['impressions']);
    }

    public function test_rows_without_a_page_url_are_excluded_from_top_landing_pages(): void
    {
        $this->row(['page_url' => '']);

        $pages = $this->service()->report(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'))['top_landing_pages'];

        $this->assertSame([], $pages);
    }

    public function test_brand_queries_are_excluded_from_non_brand_queries(): void
    {
        config(['search-console.brand_terms' => ['kairus']]);

        $this->row(['query' => 'kairus recensioni', 'impressions' => 50]);
        $this->row(['query' => 'ape insetto', 'impressions' => 40]);

        $nonBrand = $this->service()->report(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'))['non_brand_queries'];

        $this->assertCount(1, $nonBrand);
        $this->assertSame('ape insetto', $nonBrand[0]['query']);
    }

    public function test_with_no_brand_terms_configured_every_query_counts_as_non_brand(): void
    {
        config(['search-console.brand_terms' => []]);

        $this->row(['query' => 'kairus recensioni']);

        $nonBrand = $this->service()->report(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'))['non_brand_queries'];

        $this->assertCount(1, $nonBrand);
    }

    public function test_device_and_country_breakdowns_are_explicitly_not_measured(): void
    {
        $this->row();

        $report = $this->service()->report(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertNull($report['device_breakdown']);
        $this->assertNull($report['country_breakdown']);
    }

    public function test_has_data_is_false_and_totals_are_null_when_no_rows_exist_for_the_period(): void
    {
        $report = $this->service()->report(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertFalse($report['has_data']);
        $this->assertSame(0, $report['totals']['clicks']);
        $this->assertNull($report['totals']['ctr']);
        $this->assertNull($report['totals']['position']);
    }

    public function test_reuses_existing_opportunity_scoring_instead_of_recalculating(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        Article::create([
            'user_id' => $author->id,
            'title' => 'Api insetto',
            'slug' => 'api-insetto',
            'body' => 'Corpo.',
            'category' => 'natura',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        // Posizione 13 (fuori pagina 1, dentro 11-20): near_page_one.
        $this->row(['query' => 'ape insetto', 'page_url' => 'https://kairus.it/articolo/api-insetto', 'impressions' => 50, 'clicks' => 1, 'position' => 13.0]);

        $report = $this->service()->report(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertCount(1, $report['near_page_one']);
        $this->assertSame('ape insetto', $report['near_page_one'][0]->query);
    }
}
