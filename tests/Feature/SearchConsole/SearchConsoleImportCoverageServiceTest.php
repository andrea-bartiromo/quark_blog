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

    public function test_record_keeps_separate_rows_for_different_properties_or_report_types(): void
    {
        $base = [
            'period_start' => Carbon::parse('2026-08-01'),
            'period_end' => Carbon::parse('2026-08-07'),
            'row_count' => 5,
            'matched_count' => 1,
            'unmatched_count' => 4,
            'pages_observed_count' => 1,
            'import_batch' => 'batch-a',
        ];

        $this->service()->record([...$base, 'property' => 'https://sito-uno.it', 'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE]);
        $this->service()->record([...$base, 'property' => 'https://sito-due.it', 'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_PAGE]);
        $this->service()->record([...$base, 'property' => 'https://sito-uno.it', 'report_type' => SearchConsoleImportCoverage::REPORT_TYPE_QUERY_ONLY]);

        $this->assertSame(3, SearchConsoleImportCoverage::query()->count());
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
