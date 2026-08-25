<?php

namespace Tests\Unit\SearchConsole;

use App\Models\SearchConsoleQuery;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missione 34 (secondo batch autonomo KAIRUS, Fase D — Editorial
 * Operations Command Center): "Search Opportunities operational health".
 */
class SearchConsoleFreshnessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_is_unavailable_when_no_import_ever_happened(): void
    {
        $summary = app(SearchConsoleFreshnessService::class)->summary();

        $this->assertFalse($summary['available']);
        $this->assertNull($summary['last_imported_at']);
        $this->assertNull($summary['days_since_last_import']);
    }

    public function test_summary_reflects_the_most_recent_import(): void
    {
        $this->importedAt(now()->subDays(10));
        $this->importedAt(now()->subDays(2));

        $summary = app(SearchConsoleFreshnessService::class)->summary();

        $this->assertTrue($summary['available']);
        $this->assertSame(2, $summary['days_since_last_import']);
    }

    private function importedAt(\DateTimeInterface $importedAt): SearchConsoleQuery
    {
        return SearchConsoleQuery::create([
            'query' => 'query freschezza test',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => null,
            'clicks' => 1,
            'impressions' => 10,
            'ctr' => 0.1,
            'position' => 5,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'import_batch' => 'freshness-batch-'.uniqid(),
            'imported_at' => $importedAt,
        ]);
    }
}
