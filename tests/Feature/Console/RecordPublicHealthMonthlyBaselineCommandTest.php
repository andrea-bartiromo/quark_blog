<?php

namespace Tests\Feature\Console;

use App\Models\PublicHealthBaseline;
use App\Services\PublicPages\PublicHealthDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cantiere 35 (programma 100-cantieri Kairus), dipende dal Cantiere 30.
 * Usa il servizio reale di PublicHealthDashboardService (nessun fake,
 * stesso approccio di PublicHealthDashboardControllerTest): su un
 * database di test vuoto ogni dominio restituisce conteggi a zero, qui
 * si prova solo che il comando registri una riga per dominio per il
 * mese corrente.
 */
class RecordPublicHealthMonthlyBaselineCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_a_baseline_row_for_each_real_domain(): void
    {
        $this->artisan('public-health:record-monthly-baseline')->assertExitCode(0);

        $period = Carbon::now()->format('Y-m');
        $this->assertSame(
            count(PublicHealthDashboardService::REAL_DOMAIN_KEYS),
            PublicHealthBaseline::query()->where('period', $period)->count(),
        );
    }

    public function test_running_it_twice_in_the_same_month_does_not_duplicate_rows(): void
    {
        $this->artisan('public-health:record-monthly-baseline')->assertExitCode(0);
        $this->artisan('public-health:record-monthly-baseline')->assertExitCode(0);

        $period = Carbon::now()->format('Y-m');
        $this->assertSame(
            count(PublicHealthDashboardService::REAL_DOMAIN_KEYS),
            PublicHealthBaseline::query()->where('period', $period)->count(),
        );
    }
}
