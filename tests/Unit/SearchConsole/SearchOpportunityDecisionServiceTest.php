<?php

namespace Tests\Unit\SearchConsole;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SearchConsoleQuery;
use App\Models\SearchOpportunityDecision;
use App\Models\SearchOpportunityDecisionHistory;
use App\Models\SearchZeroResultQuery;
use App\Models\User;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
use App\Services\SearchConsole\SearchOpportunity;
use App\Services\SearchConsole\SearchOpportunityDecisionService;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        $decision = $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Query troppo generica.', null, $editor);

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
        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Prima motivazione.', null, $editor);

        // Stessa opportunity_key, metriche "attuali" diverse (come se fosse
        // stata ricalcolata da un periodo più recente) e una decisione
        // diversa: il baseline catturato alla PRIMA decisione non deve
        // mai cambiare.
        Carbon::setTestNow('2026-02-01 10:00:00');
        $laterOpportunity = $this->opportunity(['impressions' => 9000, 'clicks' => 900]);
        $decision = $this->service()->record($laterOpportunity, SearchOpportunityDecision::DECISION_UPDATE_ARTICLE, 'Seconda motivazione.', null, $editor);

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

        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Prima motivazione.', null, $editor);
        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_UPDATE_ARTICLE, 'Seconda motivazione.', null, $editor);

        $history = SearchOpportunityDecisionHistory::query()->where('opportunity_key', $opportunity->key)->orderBy('id')->get();

        $this->assertCount(2, $history);
        $this->assertSame('decision_recorded', $history[0]->action);
        $this->assertSame('decision_updated', $history[1]->action);
        // Snapshot completo (tipo + articolo + brief + motivazione), non
        // solo il tipo di decisione (Codex, PR #590): altrimenti un
        // cambio di articolo/motivazione a parità di tipo di decisione
        // sarebbe invisibile nello storico.
        $this->assertStringContainsString('decision_type='.SearchOpportunityDecision::DECISION_IGNORE, $history[1]->old_value);
        $this->assertStringContainsString('decision_type='.SearchOpportunityDecision::DECISION_UPDATE_ARTICLE, $history[1]->new_value);
    }

    public function test_decisions_for_returns_a_bulk_keyed_map_with_a_single_query(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $opportunity = $this->opportunity();
        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, $editor);

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
        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Non prioritario.', null, $editor);

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
        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, $editor);

        // La query non genera più un'opportunità (hit_count sotto soglia):
        // fail-closed, nessuna misurazione indovinata.
        SearchZeroResultQuery::query()->where('normalized_query', 'query sparita')->update(['hit_count' => 1]);
        Carbon::setTestNow('2026-02-01 00:00:00');
        $result = $this->service()->measureDueOutcomes();

        $decision = SearchOpportunityDecision::query()->where('opportunity_key', $opportunity->key)->first();

        $this->assertSame(0, $result['measured_28d']);
        $this->assertNull($decision->measured_28d_at);
    }

    /**
     * Regressione Codex (PR #590, P1): senza un controllo esplicito che
     * il periodo più recente copra davvero l'orizzonte richiesto, il
     * comando avrebbe misurato +28 giorni con dati vecchi solo 10 giorni
     * (o addirittura con lo stesso periodo del baseline), rendendo
     * l'esito indistinguibile dal punto di partenza e bloccando per
     * sempre un nuovo tentativo quando dati realmente più recenti
     * arrivano.
     */
    public function test_measure_due_outcomes_waits_for_a_period_that_actually_covers_the_horizon(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $query = 'query orizzonte specifico';
        $pageUrl = 'https://kairus.it/articolo/orizzonte';

        Carbon::setTestNow('2026-01-01 00:00:00');
        SearchConsoleQuery::create([
            'query' => $query, 'page_url' => $pageUrl, 'article_id' => null,
            'clicks' => 1, 'impressions' => 100, 'ctr' => 0.01, 'position' => 1.0,
            'period_start' => '2026-01-01', 'period_end' => '2026-01-01',
            'import_batch' => 'batch-baseline', 'imported_at' => now(),
        ]);

        $periods = app(SearchConsoleFreshnessService::class)->availablePeriods();
        $opportunity = app(SearchOpportunityScoringService::class)
            ->currentOpportunities($periods->first(), $periods->get(1))
            ->firstWhere('query', $query);

        $this->assertNotNull($opportunity, 'precondizione di setup: nessuna opportunità generata dalla riga di test.');

        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, $editor);

        // Un periodo che finisce 10 giorni dopo il baseline: NON copre
        // ancora +28 giorni.
        SearchConsoleQuery::create([
            'query' => $query, 'page_url' => $pageUrl, 'article_id' => null,
            'clicks' => 2, 'impressions' => 150, 'ctr' => 0.013, 'position' => 1.0,
            'period_start' => '2026-01-10', 'period_end' => '2026-01-10',
            'import_batch' => 'batch-10d', 'imported_at' => now(),
        ]);

        Carbon::setTestNow('2026-02-05 00:00:00');
        $result = $this->service()->measureDueOutcomes();
        $decision = SearchOpportunityDecision::query()->where('opportunity_key_hash', hash('sha256', $opportunity->key))->first();

        $this->assertSame(0, $result['measured_28d']);
        $this->assertNull($decision->measured_28d_at);

        // Arriva un periodo che finisce 29 giorni dopo il baseline: copre
        // +28 giorni — la misurazione deve usare QUESTI dati, non quelli
        // del periodo precedente.
        SearchConsoleQuery::create([
            'query' => $query, 'page_url' => $pageUrl, 'article_id' => null,
            'clicks' => 9, 'impressions' => 400, 'ctr' => 0.0225, 'position' => 1.0,
            'period_start' => '2026-01-30', 'period_end' => '2026-01-30',
            'import_batch' => 'batch-29d', 'imported_at' => now(),
        ]);

        $result = $this->service()->measureDueOutcomes();
        $decision->refresh();

        $this->assertSame(1, $result['measured_28d']);
        $this->assertSame(9, $decision->measured_28d_clicks);
    }

    /**
     * Cantiere 7, per il report operativo (Codex, PR #593, P2): distingue
     * le decisioni dovute che measureDueOutcomes() misurerebbe DAVVERO se
     * eseguito ora ("eseguibili") da quelle che restano bloccate — nessun
     * periodo copre ancora l'orizzonte, o l'opportunità non è più tra
     * quelle attuali. Riusa esattamente le stesse condizioni di idoneità,
     * mai una seconda regola.
     */
    public function test_due_outcomes_eligibility_distinguishes_runnable_from_blocked(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        SearchZeroResultQuery::create(['normalized_query' => 'query ancora attiva', 'hit_count' => 10]);
        SearchZeroResultQuery::create(['normalized_query' => 'query sparita eligibility', 'hit_count' => 10]);

        Carbon::setTestNow('2026-01-01 00:00:00');
        $runnableOpportunity = app(SearchOpportunityScoringService::class)->currentOpportunities(null)->firstWhere('query', 'query ancora attiva');
        $blockedOpportunity = app(SearchOpportunityScoringService::class)->currentOpportunities(null)->firstWhere('query', 'query sparita eligibility');

        $this->service()->record($runnableOpportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, $editor);
        $this->service()->record($blockedOpportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, $editor);

        // La seconda query scende sotto soglia: la sua opportunità sparisce
        // da currentOpportunities(), pur restando "dovuta".
        SearchZeroResultQuery::query()->where('normalized_query', 'query sparita eligibility')->update(['hit_count' => 0]);

        Carbon::setTestNow('2026-02-01 00:00:00');
        $eligibility = $this->service()->dueOutcomesEligibility();

        $this->assertSame(1, $eligibility['runnable_28d']);
        $this->assertSame(1, $eligibility['blocked_28d']);

        // measureDueOutcomes() conferma la stessa lettura: solo la
        // decisione "eseguibile" viene davvero misurata.
        $result = $this->service()->measureDueOutcomes();
        $this->assertSame(1, $result['measured_28d']);
    }

    /**
     * Regressione Codex (PR #590, P2): senza eager-load, la vista che
     * legge ->article/->projectTask per ogni decisione genererebbe una
     * query per riga.
     */
    public function test_decisions_for_eager_loads_article_and_project_task(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $article = Article::withoutEvents(fn () => Article::create([
            'user_id' => $editor->id, 'title' => 'Articolo', 'slug' => 'articolo-eager-decisioni',
            'excerpt' => 'Excerpt', 'body' => '<p>Body</p>', 'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_PUBLISHED, 'published_at' => now()->subDay(), 'read_minutes' => 1,
        ]));
        $opportunity = $this->opportunity();
        $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_UPDATE_ARTICLE, 'Motivo.', $article->id, $editor);

        $decisions = $this->service()->decisionsFor(collect([$opportunity]));

        DB::flushQueryLog();
        DB::enableQueryLog();
        // Legge la relazione così come farebbe la vista Blade — se non
        // fosse eager-caricata da decisionsFor(), questo genererebbe una
        // query aggiuntiva per riga.
        $loadedArticle = $decisions[$opportunity->key]->article;
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($article->id, $loadedArticle->id);
        $this->assertSame(0, $queryCount, "Accedere a ->article dopo decisionsFor() ha eseguito {$queryCount} query: la relazione non era eager-caricata.");
    }

    /**
     * Regressione Codex (PR #590, P2): un'opportunity_key reale può
     * superare 600 caratteri (tipo + query fino a 255 + page_url fino a
     * 500) — deve restare registrabile senza errori di validazione o di
     * indicizzazione DB.
     */
    public function test_a_maximal_length_opportunity_key_can_be_recorded_and_looked_up(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $longQuery = str_repeat('a', 255);
        $longPageUrl = 'https://kairus.it/'.str_repeat('b', 480);
        $opportunity = $this->opportunity(['query' => $longQuery, 'pageUrl' => $longPageUrl]);

        $this->assertGreaterThan(600, mb_strlen($opportunity->key));

        $decision = $this->service()->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, $editor);

        $this->assertSame($opportunity->key, $decision->opportunity_key);

        $decisions = $this->service()->decisionsFor(collect([$opportunity]));
        $this->assertArrayHasKey($opportunity->key, $decisions);
    }
}
