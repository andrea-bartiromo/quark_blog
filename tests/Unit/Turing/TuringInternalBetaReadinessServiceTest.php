<?php

namespace Tests\Unit\Turing;

use App\Models\TuringChapterSource;
use App\Services\Turing\TuringInternalBetaReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 69 (programma "100 cantieri Kairus", dipende dai Cantieri 62,
 * 67, 68).
 *
 * Il servizio è puramente di sola lettura: nessun test qui deve mai
 * dimostrare una scrittura riuscita (non esiste alcun metodo di scrittura
 * da testare) — solo che la lettura riflette onestamente lo stato reale.
 */
class TuringInternalBetaReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TuringInternalBetaReadinessService
    {
        return app(TuringInternalBetaReadinessService::class);
    }

    public function test_preview_condition_is_met_because_the_preview_routes_are_registered(): void
    {
        $condition = collect($this->service()->assess())->firstWhere('key', 'anteprima_disponibile');

        $this->assertSame(TuringInternalBetaReadinessService::STATE_MET, $condition['state']);
    }

    public function test_never_empty_hub_condition_is_met_because_the_static_landing_exists(): void
    {
        $condition = collect($this->service()->assess())->firstWhere('key', 'hub_mai_vuoto');

        $this->assertSame(TuringInternalBetaReadinessService::STATE_MET, $condition['state']);
    }

    public function test_completeness_report_condition_is_met_because_the_route_is_registered(): void
    {
        $condition = collect($this->service()->assess())->firstWhere('key', 'report_completezza_disponibile');

        $this->assertSame(TuringInternalBetaReadinessService::STATE_MET, $condition['state']);
    }

    public function test_sources_condition_is_not_met_when_the_table_is_empty(): void
    {
        $this->assertSame(0, TuringChapterSource::query()->count());

        $condition = collect($this->service()->assess())->firstWhere('key', 'fonti_registrate');

        $this->assertSame(TuringInternalBetaReadinessService::STATE_NOT_MET, $condition['state']);
    }

    public function test_sources_condition_becomes_met_once_a_source_is_registered(): void
    {
        TuringChapterSource::create([
            'chapter' => 'enigma',
            'label' => 'On Computable Numbers',
            'url' => 'https://example.com/on-computable-numbers',
            'year' => 1936,
            'sort_order' => 1,
        ]);

        $condition = collect($this->service()->assess())->firstWhere('key', 'fonti_registrate');

        $this->assertSame(TuringInternalBetaReadinessService::STATE_MET, $condition['state']);
        $this->assertStringContainsString('1 fonte', $condition['detail']);
    }

    public function test_owner_condition_is_always_not_determinable(): void
    {
        $condition = collect($this->service()->assess())->firstWhere('key', 'owner_revisione_assegnato');

        $this->assertSame(TuringInternalBetaReadinessService::STATE_NOT_DETERMINABLE, $condition['state']);
    }

    public function test_all_conditions_met_is_false_without_sources_and_owner(): void
    {
        $this->assertFalse($this->service()->allConditionsMet());
    }

    public function test_the_service_never_writes_anything(): void
    {
        $this->service()->assess();
        $this->service()->allConditionsMet();

        $this->assertDatabaseCount('turing_chapter_sources', 0);
    }
}
