<?php

namespace Tests\Unit\Trust;

use App\Models\TrustKnowledgeStatement;
use App\Models\TrustKnowledgeStatementPreviewView;
use App\Services\Trust\TrustPilotPreviewMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
     * non un campo editoriale) — per simulare uno statement esistente da
     * più di 7 giorni bisogna quindi forzarlo dopo la creazione, mai
     * passarlo a create() (verrebbe silenziosamente scartato).
     */
    private function statementCreatedDaysAgo(int $days, array $overrides = []): TrustKnowledgeStatement
    {
        $statement = $this->statement($overrides);
        $statement->forceFill(['created_at' => now()->subDays($days)])->save();

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
        $statement = $this->statement();
        $this->service()->recordView($statement);

        $metric = $this->service()->aggregateViewsFor($statement);

        $this->assertSame(TrustPilotPreviewMetricsService::STATE_INSUFFICIENT_DATA, $metric['state']);
    }

    public function test_aggregate_is_available_with_a_real_zero_count_after_seven_days(): void
    {
        $statement = $this->statementCreatedDaysAgo(10);
        // Nessuna view registrata: lo zero deve restare uno zero reale, non
        // "dati insufficienti" — stesso principio di
        // docs/DASHBOARD_DATA_EXPORT_V1.md.
        $metric = $this->service()->aggregateViewsFor($statement);

        $this->assertSame(TrustPilotPreviewMetricsService::STATE_AVAILABLE, $metric['state']);
        $this->assertSame(0, $metric['count']);
    }

    public function test_aggregate_counts_multiple_views_correctly_once_available(): void
    {
        $statement = $this->statementCreatedDaysAgo(10);

        $service = $this->service();
        $service->recordView($statement);
        $service->recordView($statement);
        $service->recordView($statement);

        $metric = $service->aggregateViewsFor($statement);

        $this->assertSame(TrustPilotPreviewMetricsService::STATE_AVAILABLE, $metric['state']);
        $this->assertSame(3, $metric['count']);
    }

    public function test_aggregate_for_many_does_not_grow_its_query_count_with_the_number_of_statements(): void
    {
        $service = $this->service();
        $statements = collect();

        foreach (range(1, 8) as $i) {
            $statement = $this->statementCreatedDaysAgo(10, ['domanda' => "Domanda $i?"]);
            $service->recordView($statement);
            $statements->push($statement);
        }

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
