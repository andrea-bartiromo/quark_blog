<?php

namespace Tests\Unit\SearchConsole;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SearchOpportunityDecision;
use App\Models\SearchOpportunityDecisionHistory;
use App\Models\SearchZeroResultQuery;
use App\Models\User;
use App\Services\SearchConsole\SearchOpportunity;
use App\Services\SearchConsole\SearchOpportunityDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cantiere 4 (programma "Kairus Organic Discovery"). Decisione editoriale
 * tracciabile su un'opportunità di ricerca — mai un'azione automatica.
 */
class SearchOpportunityDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): SearchOpportunityDecisionService
    {
        return app(SearchOpportunityDecisionService::class);
    }

    private function opportunity(array $overrides = []): SearchOpportunity
    {
        return new SearchOpportunity(
            type: $overrides['type'] ?? 'high_impression_low_ctr',
            query: $overrides['query'] ?? 'fisica quantistica',
            article: $overrides['article'] ?? null,
            impressions: $overrides['impressions'] ?? 500,
            clicks: $overrides['clicks'] ?? 5,
            ctr: $overrides['ctr'] ?? 0.01,
            position: $overrides['position'] ?? 8.0,
            score: 42.0,
            explanation: 'Test.',
            pageUrl: $overrides['pageUrl'] ?? 'https://kairus.it/notizie',
        );
    }

    public function test_recording_a_decision_captures_a_baseline_from_the_opportunitys_current_metrics(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $opportunity = $this->opportunity();

        $decision = $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Query troppo generica.', null, null, $editor);

        $this->assertSame(500, $decision->baseline_impressions);
        $this->assertSame(5, $decision->baseline_clicks);
        $this->assertNotNull($decision->baseline_captured_at);
        $this->assertSame($editor->id, $decision->created_by);
        $this->assertSame($editor->id, $decision->updated_by);
    }

    public function test_recording_a_decision_twice_never_recaptures_the_baseline(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $opportunity = $this->opportunity();

        Carbon::setTestNow('2026-01-01 10:00:00');
        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Prima motivazione.', null, null, $editor);

        // Stessa opportunity_key, metriche "attuali" diverse (come se fosse
        // stata ricalcolata da un periodo più recente) e una decisione
        // diversa: il baseline catturato alla PRIMA decisione non deve
        // mai cambiare.
        Carbon::setTestNow('2026-02-01 10:00:00');
        $laterOpportunity = $this->opportunity(['impressions' => 9000, 'clicks' => 900]);
        $decision = $this->service()->record($laterOpportunity, SearchOpportunityDecision::DECISION_UPDATE_ARTICLE, 'Seconda motivazione.', null, null, $editor);

        $this->assertSame(1, SearchOpportunityDecision::query()->count());
        $this->assertSame(500, $decision->baseline_impressions);
        $this->assertSame(5, $decision->baseline_clicks);
        $this->assertSame('2026-01-01 10:00:00', $decision->baseline_captured_at->format('Y-m-d H:i:s'));
        $this->assertSame(SearchOpportunityDecision::DECISION_UPDATE_ARTICLE, $decision->decision_type);
    }

    public function test_every_recorded_change_appends_a_history_row_never_overwriting_the_previous_one(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $opportunity = $this->opportunity();

        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Prima motivazione.', null, null, $editor);
        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_UPDATE_ARTICLE, 'Seconda motivazione.', null, null, $editor);

        $history = SearchOpportunityDecisionHistory::query()->where('opportunity_key', $opportunity->key)->orderBy('id')->get();

        $this->assertCount(2, $history);
        $this->assertSame('decision_recorded', $history[0]->action);
        $this->assertSame('decision_updated', $history[1]->action);
        $this->assertSame(SearchOpportunityDecision::DECISION_IGNORE, $history[1]->old_value);
        $this->assertSame(SearchOpportunityDecision::DECISION_UPDATE_ARTICLE, $history[1]->new_value);
    }

    public function test_decisions_for_returns_a_bulk_keyed_map_with_a_single_query(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $opportunity = $this->opportunity();
        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, null, $editor);

        $decisions = $this->service()->decisionsFor(collect([$opportunity]));

        $this->assertArrayHasKey($opportunity->key, $decisions);
        $this->assertSame(SearchOpportunityDecision::DECISION_IGNORE, $decisions[$opportunity->key]->decision_type);
    }

    public function test_create_brief_for_opportunity_creates_a_publication_task_without_an_article(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        Project::create([
            'title' => 'Editoriale', 'slug' => 'editoriale-test', 'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'operational_status' => Project::STATUS_IN_PROGRESS, 'is_default_editorial' => true,
        ]);
        $opportunity = $this->opportunity(['query' => 'differenza tra ape e vespa']);

        $task = $this->service()->createBriefForOpportunity($opportunity, $editor);

        $this->assertSame(ProjectTask::TYPE_PUBLICATION, $task->type);
        $this->assertNull($task->article_id);
        $this->assertStringContainsString('differenza tra ape e vespa', $task->title);
    }

    public function test_create_brief_fails_closed_without_a_default_editorial_project(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->expectException(\RuntimeException::class);

        $this->service()->createBriefForOpportunity($this->opportunity(), $editor);
    }

    public function test_measure_due_outcomes_fills_28d_metrics_once_the_baseline_is_old_enough(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        // Query interna a zero risultati: unica fonte di opportunità che
        // non dipende da alcun periodo Search Console importato (Missione
        // 32) — la scelta più deterministica per questo test.
        SearchZeroResultQuery::create(['normalized_query' => 'ricerca senza risultati', 'hit_count' => 5]);
        $opportunity = new SearchOpportunity(
            type: 'internal_zero_result_search', query: 'ricerca senza risultati', article: null,
            impressions: 5, clicks: 0, ctr: null, position: null, score: 5, explanation: 'Test.',
        );

        Carbon::setTestNow('2026-01-01 00:00:00');
        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Non prioritario.', null, null, $editor);

        // Ancora troppo presto: nessuna misurazione.
        Carbon::setTestNow('2026-01-20 00:00:00');
        $result = $this->service()->measureDueOutcomes();
        $this->assertSame(0, $result['measured_28d']);

        // Il conteggio della ricerca interna sale a 8: questo è ciò che
        // deve comparire come misurazione a +28 giorni.
        SearchZeroResultQuery::query()->where('normalized_query', 'ricerca senza risultati')->update(['hit_count' => 8]);
        Carbon::setTestNow('2026-01-30 00:00:00');
        $result = $this->service()->measureDueOutcomes();

        $decision = SearchOpportunityDecision::query()->where('opportunity_key', $opportunity->key)->first();

        $this->assertSame(1, $result['measured_28d']);
        $this->assertSame(8, $decision->measured_28d_impressions);
        $this->assertNotNull($decision->measured_28d_at);
        $this->assertNull($decision->measured_90d_at);
    }

    public function test_measure_due_outcomes_leaves_a_decision_unmeasured_when_the_opportunity_is_gone(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        SearchZeroResultQuery::create(['normalized_query' => 'query sparita', 'hit_count' => 5]);
        $opportunity = new SearchOpportunity(
            type: 'internal_zero_result_search', query: 'query sparita', article: null,
            impressions: 5, clicks: 0, ctr: null, position: null, score: 5, explanation: 'Test.',
        );

        Carbon::setTestNow('2026-01-01 00:00:00');
        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, null, $editor);

        // La query non genera più un'opportunità (hit_count sotto soglia):
        // fail-closed, nessuna misurazione indovinata.
        SearchZeroResultQuery::query()->where('normalized_query', 'query sparita')->update(['hit_count' => 1]);
        Carbon::setTestNow('2026-02-01 00:00:00');
        $result = $this->service()->measureDueOutcomes();

        $decision = SearchOpportunityDecision::query()->where('opportunity_key', $opportunity->key)->first();

        $this->assertSame(0, $result['measured_28d']);
        $this->assertNull($decision->measured_28d_at);
    }
}
