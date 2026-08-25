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

    /**
     * Missione 45 (Fase F — Search Intelligence): "import freshness".
     * SearchConsoleCsvImporter è già idempotente per-periodo (sostituisce
     * solo le righe dello stesso period_start/period_end), quindi periodi
     * diversi si accumulano davvero come batch distinti nel tempo —
     * importHistory() li espone, mai una nuova regola di raggruppamento.
     */
    public function test_import_history_is_empty_when_no_import_ever_happened(): void
    {
        $this->assertSame([], app(SearchConsoleFreshnessService::class)->importHistory());
    }

    public function test_import_history_lists_one_row_per_batch_most_recent_first(): void
    {
        $this->batch('batch-vecchio', '2026-07-01', '2026-07-07', now()->subDays(10), 2);
        $this->batch('batch-recente', '2026-08-01', '2026-08-07', now()->subDays(2), 3);

        $history = app(SearchConsoleFreshnessService::class)->importHistory();

        $this->assertCount(2, $history);
        $this->assertSame('batch-recente', $history[0]['import_batch']);
        $this->assertSame(3, $history[0]['row_count']);
        $this->assertSame('batch-vecchio', $history[1]['import_batch']);
        $this->assertSame(2, $history[1]['row_count']);
    }

    private function batch(string $importBatch, string $periodStart, string $periodEnd, \DateTimeInterface $importedAt, int $rows): void
    {
        for ($i = 0; $i < $rows; $i++) {
            SearchConsoleQuery::create([
                'query' => 'query freschezza test '.$i,
                'page_url' => 'https://kairus.it/notizie',
                'article_id' => null,
                'clicks' => 1,
                'impressions' => 10,
                'ctr' => 0.1,
                'position' => 5,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'import_batch' => $importBatch,
                'imported_at' => $importedAt,
            ]);
        }
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
