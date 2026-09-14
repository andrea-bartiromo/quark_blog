<?php

namespace Tests\Unit\Trust;

use App\Models\TrustKnowledgeStatement;
use App\Services\Trust\TrustPilotGateReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 42 (programma "100 cantieri Kairus", dipende dai Cantieri 40-41).
 *
 * Il servizio è puramente di sola lettura: nessun test qui deve mai
 * dimostrare una scrittura riuscita (non esiste alcun metodo di scrittura
 * da testare) — solo che la lettura riflette onestamente lo stato reale.
 */
class TrustPilotGateReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TrustPilotGateReadinessService
    {
        return app(TrustPilotGateReadinessService::class);
    }

    public function test_owner_condition_is_always_not_determinable(): void
    {
        $condition = collect($this->service()->assess())->firstWhere('key', 'owner_assegnato');

        $this->assertSame(TrustPilotGateReadinessService::STATE_NOT_DETERMINABLE, $condition['state']);
    }

    public function test_content_condition_is_not_met_when_no_statement_exists(): void
    {
        $this->assertSame(0, TrustKnowledgeStatement::query()->count());

        $condition = collect($this->service()->assess())->firstWhere('key', 'contenuto_approvato');

        $this->assertSame(TrustPilotGateReadinessService::STATE_NOT_MET, $condition['state']);
    }

    public function test_content_condition_becomes_not_determinable_but_never_met_once_a_statement_exists(): void
    {
        TrustKnowledgeStatement::create([
            'domanda' => 'Una domanda di prova?',
            'consenso' => 'Consenso di prova.',
            'incertezza' => 'Incertezza di prova.',
        ]);

        $condition = collect($this->service()->assess())->firstWhere('key', 'contenuto_approvato');

        // Mai "met": il modello non ha un campo "approvato", quindi la
        // presenza di una riga può solo smentire "zero contenuto", mai
        // dichiarare la condizione soddisfatta.
        $this->assertSame(TrustPilotGateReadinessService::STATE_NOT_DETERMINABLE, $condition['state']);
        $this->assertStringContainsString('1 voce', $condition['detail']);
    }

    public function test_sources_component_condition_is_met_when_the_component_file_exists(): void
    {
        $condition = collect($this->service()->assess())->firstWhere('key', 'componente_fonti_mergiato');

        $this->assertSame(TrustPilotGateReadinessService::STATE_MET, $condition['state']);
    }

    public function test_all_conditions_met_is_false_without_owner_and_content(): void
    {
        $this->assertFalse($this->service()->allConditionsMet());
    }

    public function test_the_service_never_writes_anything(): void
    {
        $this->service()->assess();
        $this->service()->allConditionsMet();

        $this->assertDatabaseCount('trust_knowledge_statements', 0);
    }
}
