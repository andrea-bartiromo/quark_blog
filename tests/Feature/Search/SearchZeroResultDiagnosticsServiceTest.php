<?php

namespace Tests\Feature\Search;

use App\Models\SearchZeroResultQuery;
use App\Services\Search\SearchZeroResultDiagnosticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mission 31 — Search Zero-Result Diagnostics.
 */
class SearchZeroResultDiagnosticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_query_creates_one_aggregate_row_with_hit_count_one(): void
    {
        app(SearchZeroResultDiagnosticsService::class)->record('buco nero rotante');

        $this->assertDatabaseHas('search_zero_result_queries', [
            'normalized_query' => 'buco nero rotante',
            'hit_count' => 1,
        ]);
        $this->assertSame(1, SearchZeroResultQuery::count());
    }

    public function test_the_same_query_repeated_increments_the_existing_row_instead_of_duplicating_it(): void
    {
        $service = app(SearchZeroResultDiagnosticsService::class);

        $service->record('buco nero rotante');
        $service->record('buco nero rotante');
        $service->record('buco nero rotante');

        $this->assertSame(1, SearchZeroResultQuery::count());
        $this->assertDatabaseHas('search_zero_result_queries', [
            'normalized_query' => 'buco nero rotante',
            'hit_count' => 3,
        ]);
    }

    /**
     * "Prefer normalized query/count/time aggregates" dalla formulazione
     * della missione: differenze di maiuscole/spazi/punteggiatura Unicode
     * non devono generare voci distinte per la stessa domanda reale.
     */
    public function test_case_whitespace_and_typographic_punctuation_differences_converge_on_the_same_row(): void
    {
        $service = app(SearchZeroResultDiagnosticsService::class);

        $service->record('Buco Nero');
        $service->record('buco   nero');
        $service->record('BUCO NERO');
        $service->record("buco\u{2011}nero");

        // Il trattino tipografico normalizza a "-", quindi "buco-nero" resta
        // una voce distinta da "buco nero" (spazio vs trattino sono
        // sintatticamente diversi) — solo maiuscole/spazi multipli/trattini
        // Unicode equivalenti convergono, non ogni possibile variante.
        $this->assertSame(2, SearchZeroResultQuery::count());
        $this->assertDatabaseHas('search_zero_result_queries', ['normalized_query' => 'buco nero', 'hit_count' => 3]);
        $this->assertDatabaseHas('search_zero_result_queries', ['normalized_query' => 'buco-nero', 'hit_count' => 1]);
    }

    public function test_a_blank_or_whitespace_only_query_is_never_recorded(): void
    {
        $service = app(SearchZeroResultDiagnosticsService::class);

        $service->record('');
        $service->record('   ');

        $this->assertSame(0, SearchZeroResultQuery::count());
    }

    /**
     * Nessuna colonna utente/sessione/IP/user-agent: solo il testo
     * normalizzato e un conteggio — "Do not store unnecessary personal
     * information" dalla formulazione della missione.
     */
    public function test_the_stored_row_never_carries_any_visitor_identifying_column(): void
    {
        app(SearchZeroResultDiagnosticsService::class)->record('query qualunque');

        $columns = collect(DB::getSchemaBuilder()->getColumnListing('search_zero_result_queries'));

        foreach (['user_id', 'session_id', 'ip', 'ip_address', 'user_agent'] as $forbidden) {
            $this->assertFalse($columns->contains($forbidden), "La colonna '{$forbidden}' non deve mai esistere su questa tabella.");
        }
    }

    /**
     * Fail-open: un fallimento di scrittura non deve mai propagarsi al
     * chiamante (la ricerca pubblica), stesso principio già applicato da
     * ContinuationAnalyticsService.
     */
    public function test_recording_never_throws_even_if_the_table_is_unavailable(): void
    {
        DB::statement('DROP TABLE search_zero_result_queries');

        app(SearchZeroResultDiagnosticsService::class)->record('query qualunque');

        $this->assertTrue(true);
    }

    public function test_top_unresolved_orders_by_hit_count_descending(): void
    {
        $service = app(SearchZeroResultDiagnosticsService::class);

        $service->record('raro');
        foreach (range(1, 3) as $i) {
            $service->record('frequente');
        }

        $top = $service->topUnresolved();

        $this->assertSame('frequente', $top->first()->normalized_query);
        $this->assertSame(3, $top->first()->hit_count);
    }
}
