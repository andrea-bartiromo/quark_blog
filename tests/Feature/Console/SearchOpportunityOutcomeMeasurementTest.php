<?php

namespace Tests\Feature\Console;

use App\Models\SearchOpportunityDecision;
use App\Models\SearchZeroResultQuery;
use App\Models\User;
use App\Services\SearchConsole\SearchOpportunity;
use App\Services\SearchConsole\SearchOpportunityDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cantiere 4 (programma "Kairus Organic Discovery"): comando artisan di
 * sola lettura per misurare l'esito a 28/90 giorni delle decisioni
 * editoriali — mai una scrittura su articoli, mai una chiamata esterna.
 */
class SearchOpportunityOutcomeMeasurementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_command_measures_decisions_due_at_28_days_and_reports_the_count(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        SearchZeroResultQuery::create(['normalized_query' => 'query comando', 'hit_count' => 5]);
        $opportunity = new SearchOpportunity(
            type: 'internal_zero_result_search', query: 'query comando', article: null,
            impressions: 5, clicks: 0, ctr: null, position: null, score: 5, explanation: 'Test.',
        );

        Carbon::setTestNow('2026-01-01 00:00:00');
        app(SearchOpportunityDecisionService::class)->record($opportunity, SearchOpportunityDecision::DECISION_IGNORE, 'Motivo.', null, null, $editor);

        Carbon::setTestNow('2026-01-30 00:00:00');
        $this->artisan('search-opportunities:measure-outcomes')
            ->expectsOutputToContain('Misurate a +28 giorni: 1')
            ->assertExitCode(0);

        $decision = SearchOpportunityDecision::query()->where('opportunity_key', $opportunity->key)->first();
        $this->assertNotNull($decision->measured_28d_at);
    }

    public function test_the_command_reports_zero_when_nothing_is_due(): void
    {
        $this->artisan('search-opportunities:measure-outcomes')
            ->expectsOutputToContain('Misurate a +28 giorni: 0')
            ->expectsOutputToContain('Misurate a +90 giorni: 0')
            ->assertExitCode(0);
    }
}
