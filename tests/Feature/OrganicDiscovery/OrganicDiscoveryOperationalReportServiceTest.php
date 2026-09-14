<?php

namespace Tests\Feature\OrganicDiscovery;

use App\Models\Article;
use App\Models\SearchConsoleImportCoverage;
use App\Models\SearchConsoleQuery;
use App\Models\SearchOpportunityDecision;
use App\Models\SearchZeroResultQuery;
use App\Models\User;
use App\Services\OrganicDiscovery\OrganicDiscoveryOperationalReportService;
use App\Services\SearchConsole\SearchOpportunityDecisionService;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 7 (programma "Kairus Organic Discovery"): il report operativo
 * compone solo servizi già testati altrove (OrganicDiscoveryReadinessService,
 * SearchOpportunityScoringService, SearchOpportunityDecisionService,
 * SearchConsoleFreshnessService/ImportCoverageService) — questi test
 * coprono solo LA SUA LOGICA DI AGGREGAZIONE, mai la correttezza degli
 * stati/soglie già coperta dai test dedicati di quei servizi.
 */
class OrganicDiscoveryOperationalReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_database_produces_a_report_with_no_errors_and_zero_counts(): void
    {
        $snapshot = app(OrganicDiscoveryOperationalReportService::class)->snapshot();

        $this->assertFalse($snapshot['search_console']['freshness']['available']);
        $this->assertSame(0, $snapshot['readiness']['total']);
        $this->assertSame(0, $snapshot['opportunities']['current_total']);
        $this->assertSame(0, $snapshot['decisions']['total']);
        $this->assertSame(0, $snapshot['outcomes']['28d']['measured']);
        $this->assertSame(0, $snapshot['cannibalization']['current_period_findings']);
    }

    public function test_coverage_rows_are_summed_not_listed_row_by_row(): void
    {
        SearchConsoleImportCoverage::create([
            'property' => 'https://kairus.it', 'period_start' => '2026-08-01', 'period_end' => '2026-08-07',
            'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE, 'row_count' => 100,
            'matched_count' => 80, 'unmatched_count' => 20, 'pages_observed_count' => 10,
            'origin' => SearchConsoleImportCoverage::ORIGIN_MANUAL_CSV, 'import_batch' => 'b1', 'imported_at' => now(),
        ]);
        SearchConsoleImportCoverage::create([
            'property' => 'https://kairus.it', 'period_start' => '2026-08-08', 'period_end' => '2026-08-14',
            'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE, 'row_count' => 50,
            'matched_count' => 45, 'unmatched_count' => 5, 'pages_observed_count' => 8,
            'origin' => SearchConsoleImportCoverage::ORIGIN_MANUAL_CSV, 'import_batch' => 'b2', 'imported_at' => now(),
        ]);

        $snapshot = app(OrganicDiscoveryOperationalReportService::class)->snapshot();

        $this->assertSame(2, $snapshot['search_console']['coverage']['periods_covered']);
        $this->assertSame(150, $snapshot['search_console']['coverage']['total_rows_imported']);
        $this->assertSame(25, $snapshot['search_console']['coverage']['total_unmatched_queries']);
    }

    public function test_decisions_are_counted_by_type_and_due_unmeasured_decisions_are_flagged(): void
    {
        $user = User::factory()->create(['role' => 'editor']);
        $article = $this->article();

        // Decisione con baseline abbastanza vecchia da essere dovuta a
        // 28gg ma non ancora misurata.
        $this->decision($user, $article, [
            'decision_type' => SearchOpportunityDecision::DECISION_UPDATE_ARTICLE,
            'baseline_captured_at' => now()->subDays(30),
        ]);
        // Decisione già misurata a 28gg: non deve comparire tra le dovute.
        $this->decision($user, $article, [
            'decision_type' => SearchOpportunityDecision::DECISION_IGNORE,
            'baseline_captured_at' => now()->subDays(40),
            'measured_28d_at' => now(),
            'measured_28d_clicks' => 5,
        ], suffix: '2');
        // Decisione troppo recente: non ancora dovuta.
        $this->decision($user, $article, [
            'decision_type' => SearchOpportunityDecision::DECISION_UPDATE_ARTICLE,
            'baseline_captured_at' => now()->subDays(5),
        ], suffix: '3');

        $snapshot = app(OrganicDiscoveryOperationalReportService::class)->snapshot();

        $this->assertSame(3, $snapshot['decisions']['total']);
        $this->assertSame(2, $snapshot['decisions']['by_type'][SearchOpportunityDecision::DECISION_UPDATE_ARTICLE]['count']);
        $this->assertSame(1, $snapshot['decisions']['by_type'][SearchOpportunityDecision::DECISION_IGNORE]['count']);
        $this->assertSame(1, $snapshot['decisions']['due_but_unmeasured_28d']);
    }

    public function test_outcomes_are_classified_by_comparing_measured_clicks_to_baseline(): void
    {
        $user = User::factory()->create(['role' => 'editor']);
        $article = $this->article();

        $this->decision($user, $article, [
            'baseline_clicks' => 10, 'baseline_captured_at' => now()->subDays(30),
            'measured_28d_at' => now(), 'measured_28d_clicks' => 15,
        ], suffix: 'up');
        $this->decision($user, $article, [
            'baseline_clicks' => 10, 'baseline_captured_at' => now()->subDays(30),
            'measured_28d_at' => now(), 'measured_28d_clicks' => 10,
        ], suffix: 'flat');
        $this->decision($user, $article, [
            'baseline_clicks' => 10, 'baseline_captured_at' => now()->subDays(30),
            'measured_28d_at' => now(), 'measured_28d_clicks' => 4,
        ], suffix: 'down');
        // Non ancora misurata: non deve contare in nessuna delle tre categorie.
        $this->decision($user, $article, [
            'baseline_clicks' => 10, 'baseline_captured_at' => now()->subDays(5),
        ], suffix: 'unmeasured');

        $snapshot = app(OrganicDiscoveryOperationalReportService::class)->snapshot();

        $this->assertSame(3, $snapshot['outcomes']['28d']['measured']);
        $this->assertSame(1, $snapshot['outcomes']['28d']['improved']);
        $this->assertSame(1, $snapshot['outcomes']['28d']['flat']);
        $this->assertSame(1, $snapshot['outcomes']['28d']['worse']);
        $this->assertSame(0, $snapshot['outcomes']['90d']['measured']);
    }

    public function test_current_opportunities_are_split_between_with_and_without_a_decision(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        SearchZeroResultQuery::create(['normalized_query' => 'con decisione', 'hit_count' => 5]);
        SearchZeroResultQuery::create(['normalized_query' => 'senza decisione', 'hit_count' => 5]);

        $opportunity = app(SearchOpportunityScoringService::class)
            ->currentOpportunities(null)
            ->firstWhere('query', 'con decisione');
        app(SearchOpportunityDecisionService::class)->record(
            $opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, $editor
        );

        $snapshot = app(OrganicDiscoveryOperationalReportService::class)->snapshot();

        $this->assertSame(2, $snapshot['opportunities']['current_total']);
        $this->assertSame(1, $snapshot['opportunities']['current_with_decision']);
        $this->assertSame(1, $snapshot['opportunities']['current_without_decision']);
    }

    public function test_cannibalization_finding_count_reflects_the_latest_period(): void
    {
        $first = $this->article(['slug' => 'report-cannib-primo']);
        $second = $this->article(['slug' => 'report-cannib-secondo']);

        SearchConsoleQuery::create([
            'query' => 'query report cannibalizzazione', 'page_url' => 'https://kairus.it/articolo/report-cannib-primo', 'article_id' => $first->id,
            'clicks' => 3, 'impressions' => 40, 'ctr' => 0.075, 'position' => 4.0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'import_batch' => 'fixture', 'imported_at' => now(),
        ]);
        SearchConsoleQuery::create([
            'query' => 'query report cannibalizzazione', 'page_url' => 'https://kairus.it/articolo/report-cannib-secondo', 'article_id' => $second->id,
            'clicks' => 1, 'impressions' => 15, 'ctr' => 0.066, 'position' => 8.0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'import_batch' => 'fixture', 'imported_at' => now(),
        ]);

        $snapshot = app(OrganicDiscoveryOperationalReportService::class)->snapshot();

        $this->assertSame(1, $snapshot['cannibalization']['current_period_findings']);
    }

    private function article(array $overrides = []): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo report',
            'slug' => 'articolo-report-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function decision(User $user, Article $article, array $overrides, string $suffix = ''): SearchOpportunityDecision
    {
        $key = 'update_article|query report '.$suffix.uniqid().'|';

        return SearchOpportunityDecision::create(array_merge([
            'opportunity_key' => $key,
            'opportunity_key_hash' => hash('sha256', $key),
            'opportunity_type' => 'update_article',
            'opportunity_query' => 'query report',
            'decision_type' => SearchOpportunityDecision::DECISION_UPDATE_ARTICLE,
            'article_id' => $article->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $overrides));
    }
}
