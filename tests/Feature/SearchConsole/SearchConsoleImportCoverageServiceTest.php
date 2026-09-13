<?php

namespace Tests\Feature\SearchConsole;

use App\Models\SearchConsoleImportCoverage;
use App\Services\SearchConsole\SearchConsoleImportCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SearchConsoleImportCoverageServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SearchConsoleImportCoverageService
    {
        return app(SearchConsoleImportCoverageService::class);
    }

    public function test_resolve_property_returns_the_given_value_when_present(): void
    {
        $this->assertSame('https://esempio.it', $this->service()->resolveProperty('https://esempio.it'));
    }

    public function test_resolve_property_falls_back_to_the_configured_default(): void
    {
        config(['search-console.default_property' => 'https://default-config.it']);

        $this->assertSame('https://default-config.it', $this->service()->resolveProperty(null));
    }

    public function test_resolve_property_falls_back_to_app_url_when_no_default_is_configured(): void
    {
        config(['search-console.default_property' => null, 'app.url' => 'https://app-url.it']);

        $this->assertSame('https://app-url.it', $this->service()->resolveProperty(''));
    }

    public function test_record_upserts_instead_of_duplicating_for_the_same_property_period_and_report_type(): void
    {
        $data = [
            'property' => 'https://esempio.it',
            'period_start' => Carbon::parse('2026-08-01'),
            'period_end' => Carbon::parse('2026-08-07'),
            'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE,
            'row_count' => 10,
            'matched_count' => 4,
            'unmatched_count' => 6,
            'pages_observed_count' => 3,
            'import_batch' => 'batch-a',
        ];

        $this->service()->record($data);
        $this->service()->record([...$data, 'row_count' => 20, 'import_batch' => 'batch-b']);

        $this->assertSame(1, SearchConsoleImportCoverage::query()->count());
        $this->assertSame(20, SearchConsoleImportCoverage::query()->firstOrFail()->row_count);
    }

    // Nota: property/tipo di report diversi NON coesistono per lo STESSO
    // periodo — vedi i test "superseded" sotto — perché
    // search_console_queries ha un'unica riga per periodo, sostituita
    // per intero a ogni import indipendentemente da questi due campi.
    // Periodi diversi restano invece indipendenti (già coperto da
    // test_record_does_not_touch_coverage_rows_for_a_different_period).

    // Codex (PR #586, P2, reale): search_console_queries viene sostituita
    // per l'intero periodo indipendentemente da property/tipo di report
    // (SearchConsoleCsvImporter::import() cancella per period_start/
    // period_end soltanto — vedi la sua docblock), quindi una riga di
    // copertura per una property/tipo di report ORMAI sostituita nello
    // stesso periodo va rimossa: altrimenti resterebbe visibile in admin
    // come "corrente" pur descrivendo dati che non esistono più.
    public function test_record_removes_a_superseded_coverage_row_for_the_same_period_with_a_different_property(): void
    {
        $period = ['period_start' => Carbon::parse('2026-08-01'), 'period_end' => Carbon::parse('2026-08-07')];

        $this->service()->record([
            ...$period,
            'property' => 'https://sito-vecchio.it',
            'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE,
            'row_count' => 5,
            'matched_count' => 1,
            'unmatched_count' => 4,
            'pages_observed_count' => 2,
            'import_batch' => 'batch-a',
        ]);

        $this->service()->record([
            ...$period,
            'property' => 'https://sito-nuovo.it',
            'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE,
            'row_count' => 9,
            'matched_count' => 3,
            'unmatched_count' => 6,
            'pages_observed_count' => 4,
            'import_batch' => 'batch-b',
        ]);

        $rows = SearchConsoleImportCoverage::query()->get();
        $this->assertCount(1, $rows);
        $this->assertSame('https://sito-nuovo.it', $rows->first()->property);
    }

    public function test_record_removes_a_superseded_coverage_row_for_the_same_period_with_a_different_report_type(): void
    {
        $period = ['period_start' => Carbon::parse('2026-08-01'), 'period_end' => Carbon::parse('2026-08-07')];

        $this->service()->record([
            ...$period,
            'property' => 'https://esempio.it',
            'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_ONLY,
            'row_count' => 5,
            'matched_count' => 0,
            'unmatched_count' => 5,
            'pages_observed_count' => 0,
            'import_batch' => 'batch-a',
        ]);

        $this->service()->record([
            ...$period,
            'property' => 'https://esempio.it',
            'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE,
            'row_count' => 9,
            'matched_count' => 3,
            'unmatched_count' => 6,
            'pages_observed_count' => 4,
            'import_batch' => 'batch-b',
        ]);

        $rows = SearchConsoleImportCoverage::query()->get();
        $this->assertCount(1, $rows);
        $this->assertSame(SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE, $rows->first()->report_type);
    }

    public function test_record_does_not_touch_coverage_rows_for_a_different_period(): void
    {
        $this->service()->record([
            'property' => 'https://esempio.it',
            'period_start' => Carbon::parse('2026-07-01'),
            'period_end' => Carbon::parse('2026-07-07'),
            'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE,
            'row_count' => 5,
            'matched_count' => 1,
            'unmatched_count' => 4,
            'pages_observed_count' => 2,
            'import_batch' => 'batch-a',
        ]);

        $this->service()->record([
            'property' => 'https://sito-diverso.it',
            'period_start' => Carbon::parse('2026-08-01'),
            'period_end' => Carbon::parse('2026-08-07'),
            'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE,
            'row_count' => 9,
            'matched_count' => 3,
            'unmatched_count' => 6,
            'pages_observed_count' => 4,
            'import_batch' => 'batch-b',
        ]);

        $this->assertSame(2, SearchConsoleImportCoverage::query()->count());
    }

    public function test_all_orders_by_period_start_descending(): void
    {
        $base = [
            'property' => 'https://esempio.it',
            'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE,
            'row_count' => 1,
            'matched_count' => 0,
            'unmatched_count' => 1,
            'pages_observed_count' => 0,
            'import_batch' => 'batch-a',
        ];

        $this->service()->record([...$base, 'period_start' => Carbon::parse('2026-07-01'), 'period_end' => Carbon::parse('2026-07-07')]);
        $this->service()->record([...$base, 'period_start' => Carbon::parse('2026-08-01'), 'period_end' => Carbon::parse('2026-08-07')]);

        $all = $this->service()->all();

        $this->assertSame('2026-08-01', $all->first()->period_start->toDateString());
    }
}
