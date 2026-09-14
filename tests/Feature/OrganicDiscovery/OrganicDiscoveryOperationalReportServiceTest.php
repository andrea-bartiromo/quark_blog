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

    public function test_decisions_are_counted_by_type(): void
    {
        $user = User::factory()->create(['role' => 'editor']);
        $article = $this->article();

        $this->decision($user, $article, ['decision_type' => SearchOpportunityDecision::DECISION_UPDATE_ARTICLE]);
        $this->decision($user, $article, ['decision_type' => SearchOpportunityDecision::DECISION_IGNORE], suffix: '2');
        $this->decision($user, $article, ['decision_type' => SearchOpportunityDecision::DECISION_UPDATE_ARTICLE], suffix: '3');

        $snapshot = app(OrganicDiscoveryOperationalReportService::class)->snapshot();

        $this->assertSame(3, $snapshot['decisions']['total']);
        $this->assertSame(2, $snapshot['decisions']['by_type'][SearchOpportunityDecision::DECISION_UPDATE_ARTICLE]['count']);
        $this->assertSame(1, $snapshot['decisions']['by_type'][SearchOpportunityDecision::DECISION_IGNORE]['count']);
    }

    /**
     * Codex, PR #593 (P2): un conteggio "dovute" che non distingue le
     * decisioni davvero eseguibili ORA da quelle bloccate (l'opportunità
     * non è più tra quelle attuali) spingerebbe a rilanciare un comando
     * che per queste ultime non risolverà mai nulla.
     */
    public function test_due_measurements_are_split_between_runnable_and_blocked(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        SearchZeroResultQuery::create(['normalized_query' => 'ancora attiva', 'hit_count' => 10]);
        SearchZeroResultQuery::create(['normalized_query' => 'sparita', 'hit_count' => 10]);

        $stillCurrent = app(SearchOpportunityScoringService::class)->currentOpportunities(null)->firstWhere('query', 'ancora attiva');
        $vanishing = app(SearchOpportunityScoringService::class)->currentOpportunities(null)->firstWhere('query', 'sparita');

        app(SearchOpportunityDecisionService::class)->record($stillCurrent, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, $editor);
        app(SearchOpportunityDecisionService::class)->record($vanishing, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, $editor);

        // La seconda query non ha più abbastanza ricerche: la sua
        // opportunità sparisce da currentOpportunities(), pur restando
        // "dovuta" per la misurazione.
        SearchZeroResultQuery::where('normalized_query', 'sparita')->update(['hit_count' => 0]);

        SearchOpportunityDecision::query()->update(['baseline_captured_at' => now()->subDays(30)]);

        $snapshot = app(OrganicDiscoveryOperationalReportService::class)->snapshot();

        $this->assertSame(1, $snapshot['decisions']['runnable_28d']);
        $this->assertSame(1, $snapshot['decisions']['blocked_28d']);
    }

    public function test_outcomes_are_classified_by_comparing_measured_ctr_to_baseline(): void
    {
        $user = User::factory()->create(['role' => 'editor']);
        $article = $this->article();

        $this->decision($user, $article, [
            'baseline_ctr' => 0.05, 'baseline_captured_at' => now()->subDays(30),
            'measured_28d_at' => now(), 'measured_28d_ctr' => 0.08,
        ], suffix: 'up');
        $this->decision($user, $article, [
            'baseline_ctr' => 0.05, 'baseline_captured_at' => now()->subDays(30),
            'measured_28d_at' => now(), 'measured_28d_ctr' => 0.05,
        ], suffix: 'flat');
        $this->decision($user, $article, [
            'baseline_ctr' => 0.05, 'baseline_captured_at' => now()->subDays(30),
            'measured_28d_at' => now(), 'measured_28d_ctr' => 0.02,
        ], suffix: 'down');
        // Non ancora misurata: non deve contare in nessuna delle tre categorie.
        $this->decision($user, $article, [
            'baseline_ctr' => 0.05, 'baseline_captured_at' => now()->subDays(5),
        ], suffix: 'unmeasured');

        $snapshot = app(OrganicDiscoveryOperationalReportService::class)->snapshot();

        $this->assertSame(3, $snapshot['outcomes']['28d']['measured']);
        $this->assertSame(1, $snapshot['outcomes']['28d']['improved']);
        $this->assertSame(1, $snapshot['outcomes']['28d']['flat']);
        $this->assertSame(1, $snapshot['outcomes']['28d']['worse']);
        $this->assertSame(0, $snapshot['outcomes']['90d']['measured']);
    }

    /**
     * Codex, PR #593 (P1, due volte): le decisioni da ricerca interna a
     * zero risultati hanno clic/CTR sempre nulli o azzerati per
     * costruzione (il segnale reale è il conteggio in impressions) e, a
     * differenza delle altre opportunità, un valore più ALTO è un
     * peggioramento (più ricerche senza risultati), non un miglioramento.
     * Devono restare fuori dal confronto CTR e usare la propria metrica
     * con la direzione invertita.
     */
    public function test_internal_zero_result_search_outcomes_use_impressions_with_inverted_direction(): void
    {
        $user = User::factory()->create(['role' => 'editor']);

        $this->zeroResultDecision($user, [
            'baseline_impressions' => 10, 'baseline_captured_at' => now()->subDays(30),
            'measured_28d_at' => now(), 'measured_28d_impressions' => 3,
        ], suffix: 'meno-ricerche-fallite');
        $this->zeroResultDecision($user, [
            'baseline_impressions' => 10, 'baseline_captured_at' => now()->subDays(30),
            'measured_28d_at' => now(), 'measured_28d_impressions' => 20,
        ], suffix: 'piu-ricerche-fallite');

        $snapshot = app(OrganicDiscoveryOperationalReportService::class)->snapshot();

        // Nessuna delle due deve inquinare il confronto CTR delle
        // opportunità "normali" (entrambe hanno baseline_ctr/measured_ctr
        // nulli, mai popolati per questo tipo).
        $this->assertSame(0, $snapshot['outcomes']['28d']['measured']);

        $this->assertSame(2, $snapshot['outcomes']['internal_zero_result_search']['28d']['measured']);
        $this->assertSame(1, $snapshot['outcomes']['internal_zero_result_search']['28d']['improved']); // meno ricerche fallite = migliorata
        $this->assertSame(1, $snapshot['outcomes']['internal_zero_result_search']['28d']['worse']); // piu' ricerche fallite = peggiorata
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

    private function zeroResultDecision(User $user, array $overrides, string $suffix = ''): SearchOpportunityDecision
    {
        $key = SearchOpportunityScoringService::TYPE_INTERNAL_ZERO_RESULT_SEARCH.'|query zero risultati '.$suffix.uniqid().'|';

        return SearchOpportunityDecision::create(array_merge([
            'opportunity_key' => $key,
            'opportunity_key_hash' => hash('sha256', $key),
            'opportunity_type' => SearchOpportunityScoringService::TYPE_INTERNAL_ZERO_RESULT_SEARCH,
            'opportunity_query' => 'query zero risultati',
            'decision_type' => SearchOpportunityDecision::DECISION_IGNORE,
            'rationale' => 'Fixture.',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $overrides));
    }
}
