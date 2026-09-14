<?php

namespace Tests\Feature\Admin;

use App\Models\TrustKnowledgeStatement;
use App\Models\TrustKnowledgeStatementPreviewView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cantiere 43 (programma "100 cantieri Kairus", dipende dal Cantiere 40).
 *
 * Verifica end-to-end che aprire la preview registri un evento
 * privacy-first e che l'indice mostri il conteggio aggregato — mai un
 * dato per-visitatore.
 */
class TrustPilotPreviewMetricsControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Stessa data di TrustPilotPreviewMetricsService::TRACKING_STARTED_AT
     * (privata, quindi duplicata qui deliberatamente): i test che
     * dipendono dal tempo devono ancorarsi a questa data via
     * Carbon::setTestNow(), mai al "now" reale di esecuzione.
     */
    private const TRACKING_STARTED_AT = '2026-09-14 00:00:00';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function statement(array $overrides = []): TrustKnowledgeStatement
    {
        return TrustKnowledgeStatement::create(array_merge([
            'domanda' => 'Una domanda di prova?',
            'consenso' => 'Consenso di prova.',
            'incertezza' => 'Incertezza di prova.',
        ], $overrides));
    }

    private function statementPublishedAt(Carbon $publishedAt, array $overrides = []): TrustKnowledgeStatement
    {
        $statement = $this->statement($overrides);
        $statement->forceFill(['created_at' => $publishedAt])->save();

        return $statement->fresh();
    }

    public function test_opening_the_preview_records_exactly_one_view_event(): void
    {
        $statement = $this->statement();
        $editor = $this->editor();

        $this->actingAs($editor)->get(route('admin.trust-knowledge.preview', $statement))->assertOk();

        $this->assertDatabaseCount('trust_knowledge_preview_views', 1);
        $this->assertDatabaseHas('trust_knowledge_preview_views', [
            'trust_knowledge_statement_id' => $statement->id,
        ]);
    }

    public function test_opening_the_preview_multiple_times_accumulates_events(): void
    {
        $statement = $this->statement();
        $editor = $this->editor();

        $this->actingAs($editor)->get(route('admin.trust-knowledge.preview', $statement));
        $this->actingAs($editor)->get(route('admin.trust-knowledge.preview', $statement));
        $this->actingAs($editor)->get(route('admin.trust-knowledge.preview', $statement));

        $this->assertSame(3, TrustKnowledgeStatementPreviewView::where('trust_knowledge_statement_id', $statement->id)->count());
    }

    public function test_the_index_shows_insufficient_data_for_a_freshly_created_statement(): void
    {
        Carbon::setTestNow(self::TRACKING_STARTED_AT);
        $statement = $this->statement();
        $this->actingAs($this->editor())->get(route('admin.trust-knowledge.preview', $statement));

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(3));
        $response = $this->actingAs($this->editor())->get(route('admin.trust-knowledge.index'));

        $response->assertOk();
        $response->assertSee('Dati insufficienti (meno di 7gg)');
    }

    public function test_the_index_shows_the_aggregate_count_once_seven_days_have_passed(): void
    {
        $statement = $this->statementPublishedAt(Carbon::parse(self::TRACKING_STARTED_AT));
        $editor = $this->editor();

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(2));
        $this->actingAs($editor)->get(route('admin.trust-knowledge.preview', $statement));
        $this->actingAs($editor)->get(route('admin.trust-knowledge.preview', $statement));

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(10));
        $response = $this->actingAs($editor)->get(route('admin.trust-knowledge.index'));

        $response->assertOk();
        $response->assertSee('2');
        $response->assertDontSee('Dati insufficienti (meno di 7gg)');
    }

    /**
     * Codex P2 (PR #602): uno statement pubblicato prima che questa
     * strumentazione esistesse non deve risultare "available" solo per
     * la sua anzianità — deve valere la stessa regola dei 7 giorni di
     * raccolta reale, ancorata al rollout della metrica.
     */
    public function test_the_index_shows_insufficient_data_for_a_statement_published_before_instrumentation_existed(): void
    {
        $statement = $this->statementPublishedAt(Carbon::parse(self::TRACKING_STARTED_AT)->subDays(20));

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(3));
        $response = $this->actingAs($this->editor())->get(route('admin.trust-knowledge.index'));

        $response->assertOk();
        $response->assertSee('Dati insufficienti (meno di 7gg)');
    }

    /**
     * Garanzia strutturale privacy-first: nessuna colonna diversa da
     * id/trust_knowledge_statement_id/created_at deve esistere sulla
     * tabella — nessun modo, nemmeno accidentale, di aggiungere un
     * identificativo di visitatore in futuro senza che questo test se ne
     * accorga.
     */
    public function test_the_preview_views_table_has_no_visitor_identifying_column(): void
    {
        $columns = Schema::getColumnListing('trust_knowledge_preview_views');

        $this->assertSame(['id', 'trust_knowledge_statement_id', 'created_at'], $columns);
    }

    public function test_the_index_never_grows_its_query_count_with_the_number_of_statements(): void
    {
        $editor = $this->editor();
        $publishedAt = Carbon::parse(self::TRACKING_STARTED_AT);

        foreach (range(1, 2) as $i) {
            $this->statementPublishedAt($publishedAt, ['domanda' => "Domanda piccola $i?"]);
        }

        Carbon::setTestNow($publishedAt->copy()->addDays(10));
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($editor)->get(route('admin.trust-knowledge.index'))->assertOk();
        $smallCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        foreach (range(3, 12) as $i) {
            $this->statementPublishedAt($publishedAt, ['domanda' => "Domanda grande $i?"]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($editor)->get(route('admin.trust-knowledge.index'))->assertOk();
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual($smallCount + 2, $largeCount, 'Il calcolo delle metriche aggregate sull\'indice non deve crescere linearmente con il numero di righe.');
    }
}
