<?php

namespace Tests\Unit\Trust;

use App\Models\TrustKnowledgeStatement;
use App\Models\TrustKnowledgeStatementPreviewView;
use App\Services\Trust\TrustPilotPreviewMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cantiere 43 (programma "100 cantieri Kairus", dipende dal Cantiere 40).
 *
 * Rehearsal privacy-first della metrica B-44 "Visualizzazioni aggregate":
 * nessun test qui deve mai dimostrare che un identificativo di
 * visitatore/sessione/utente viene salvato — solo conteggi aggregati.
 */
class TrustPilotPreviewMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Stessa data di TrustPilotPreviewMetricsService::TRACKING_STARTED_AT
     * (privata, quindi duplicata qui deliberatamente): i test che
     * dipendono dal tempo devono ancorarsi a questa data via
     * Carbon::setTestNow(), mai al "now" reale di esecuzione — altrimenti
     * diventano fragili rispetto al giorno in cui girano (la costante
     * coincide con "oggi" al momento di questo cantiere).
     */
    private const TRACKING_STARTED_AT = '2026-09-14 00:00:00';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): TrustPilotPreviewMetricsService
    {
        return app(TrustPilotPreviewMetricsService::class);
    }

    private function statement(array $overrides = []): TrustKnowledgeStatement
    {
        return TrustKnowledgeStatement::create(array_merge([
            'domanda' => 'Una domanda di prova?',
            'consenso' => 'Consenso di prova.',
            'incertezza' => 'Incertezza di prova.',
        ], $overrides));
    }

    /**
     * created_at non è fillable (giustamente: è una data tecnica di riga,
     * non un campo editoriale) — per simulare una data di pubblicazione
     * specifica bisogna quindi forzarla dopo la creazione, mai passarla a
     * create() (verrebbe silenziosamente scartata).
     */
    private function statementPublishedAt(Carbon $publishedAt, array $overrides = []): TrustKnowledgeStatement
    {
        $statement = $this->statement($overrides);
        $statement->forceFill(['created_at' => $publishedAt])->save();

        return $statement->fresh();
    }

    public function test_recording_a_view_creates_no_visitor_identifying_field(): void
    {
        $statement = $this->statement();

        $this->service()->recordView($statement);

        $event = TrustKnowledgeStatementPreviewView::first();
        $this->assertNotNull($event);
        $this->assertSame($statement->id, $event->trust_knowledge_statement_id);

        // Nessun campo diverso da id/statement_id/created_at deve esistere
        // sulla riga: la garanzia privacy-first è strutturale, non solo
        // una promessa nel docblock.
        $this->assertSame(['id', 'trust_knowledge_statement_id', 'created_at'], array_keys($event->getAttributes()));
    }

    public function test_aggregate_is_insufficient_data_before_seven_days_have_passed(): void
    {
        Carbon::setTestNow(self::TRACKING_STARTED_AT);
        $statement = $this->statement();
        $this->service()->recordView($statement);

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(3));
        $metric = $this->service()->aggregateViewsFor($statement);

        $this->assertSame(TrustPilotPreviewMetricsService::STATE_INSUFFICIENT_DATA, $metric['state']);
    }

    public function test_aggregate_is_available_with_a_real_zero_count_after_seven_days(): void
    {
        $statement = $this->statementPublishedAt(Carbon::parse(self::TRACKING_STARTED_AT));

        // Nessuna view registrata: lo zero deve restare uno zero reale, non
        // "dati insufficienti" — stesso principio di
        // docs/DASHBOARD_DATA_EXPORT_V1.md.
        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(10));
        $metric = $this->service()->aggregateViewsFor($statement);

        $this->assertSame(TrustPilotPreviewMetricsService::STATE_AVAILABLE, $metric['state']);
        $this->assertSame(0, $metric['count']);
    }

    public function test_aggregate_counts_multiple_views_correctly_once_available(): void
    {
        $statement = $this->statementPublishedAt(Carbon::parse(self::TRACKING_STARTED_AT));
        $service = $this->service();

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(2));
        $service->recordView($statement);
        $service->recordView($statement);
        $service->recordView($statement);

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(10));
        $metric = $service->aggregateViewsFor($statement);

        $this->assertSame(TrustPilotPreviewMetricsService::STATE_AVAILABLE, $metric['state']);
        $this->assertSame(3, $metric['count']);
    }

    /**
     * Codex P2 (PR #602): una statement pubblicata (Cantiere 38-39) prima
     * che questa strumentazione esistesse non deve mai risultare
     * "available" solo per la sua anzianità — la raccolta dati reale
     * inizia da TRACKING_STARTED_AT, non da statement.created_at.
     */
    public function test_a_statement_published_before_instrumentation_existed_starts_insufficient_data(): void
    {
        // Pubblicata 20 giorni prima del rollout: la finestra dei 30gg da
        // pubblicazione si chiude 10 giorni DOPO il rollout, quindi una
        // reale sovrapposizione di raccolta esiste (a differenza di uno
        // statement così vecchio che l'intera finestra è già chiusa prima
        // che la strumentazione esistesse — in quel caso non ci sono mai
        // stati giorni di raccolta possibile, quindi resta correttamente
        // "dati insufficienti" per sempre: fail-closed, mai uno zero finto).
        $statement = $this->statementPublishedAt(Carbon::parse(self::TRACKING_STARTED_AT)->subDays(20));

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(3));
        $metric = $this->service()->aggregateViewsFor($statement);

        $this->assertSame(TrustPilotPreviewMetricsService::STATE_INSUFFICIENT_DATA, $metric['state']);
    }

    public function test_a_statement_published_before_instrumentation_existed_becomes_available_after_seven_real_tracking_days(): void
    {
        $statement = $this->statementPublishedAt(Carbon::parse(self::TRACKING_STARTED_AT)->subDays(20));

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(15));
        $metric = $this->service()->aggregateViewsFor($statement);

        $this->assertSame(TrustPilotPreviewMetricsService::STATE_AVAILABLE, $metric['state']);
        $this->assertSame(0, $metric['count']);
    }

    /**
     * Uno statement la cui intera finestra di 30gg da pubblicazione è già
     * chiusa prima che questa strumentazione esistesse non ha mai avuto
     * un solo giorno di raccolta possibile — deve restare "dati
     * insufficienti" per sempre, mai un falso zero "available" (stesso
     * principio fail-closed già in uso nel resto del programma).
     */
    public function test_a_statement_whose_window_closed_before_instrumentation_existed_stays_insufficient_data_forever(): void
    {
        $statement = $this->statementPublishedAt(Carbon::parse(self::TRACKING_STARTED_AT)->subDays(100));

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(365));
        $metric = $this->service()->aggregateViewsFor($statement);

        $this->assertSame(TrustPilotPreviewMetricsService::STATE_INSUFFICIENT_DATA, $metric['state']);
    }

    /**
     * Codex P2 (PR #602): la finestra è "30 giorni da pubblicazione", non
     * un conteggio che cresce all'infinito — una view oltre il trentesimo
     * giorno non deve comparire nel conteggio.
     */
    public function test_a_view_recorded_after_the_thirty_day_window_is_excluded_from_the_count(): void
    {
        $publishedAt = Carbon::parse(self::TRACKING_STARTED_AT);
        $statement = $this->statementPublishedAt($publishedAt);
        $service = $this->service();

        Carbon::setTestNow($publishedAt->copy()->addDays(5));
        $service->recordView($statement);

        Carbon::setTestNow($publishedAt->copy()->addDays(35));
        $service->recordView($statement);

        $metric = $service->aggregateViewsFor($statement);

        $this->assertSame(1, $metric['count']);
    }

    public function test_aggregate_for_many_does_not_grow_its_query_count_with_the_number_of_statements(): void
    {
        $service = $this->service();
        $statements = collect();
        $publishedAt = Carbon::parse(self::TRACKING_STARTED_AT);

        foreach (range(1, 8) as $i) {
            $statement = $this->statementPublishedAt($publishedAt, ['domanda' => "Domanda $i?"]);
            Carbon::setTestNow($publishedAt->copy()->addDays(2));
            $service->recordView($statement);
            $statements->push($statement);
        }

        Carbon::setTestNow($publishedAt->copy()->addDays(10));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $metrics = $service->aggregateViewsForMany($statements);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(8, $metrics);
        foreach ($statements as $statement) {
            $this->assertSame(1, $metrics[$statement->id]['count']);
        }
        $this->assertLessThanOrEqual(1, $queryCount, 'aggregateViewsForMany() deve restare O(1) in query indipendentemente dal numero di statement.');
    }

    public function test_aggregate_for_many_returns_empty_array_for_an_empty_collection(): void
    {
        $this->assertSame([], $this->service()->aggregateViewsForMany(collect()));
    }
}
