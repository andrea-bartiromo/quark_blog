<?php

namespace App\Services\DataExport;

use App\Models\Newsletter;
use App\Services\ContinuationAnalyticsService;
use App\Services\EditorialOperations\EditorialOperationsDashboardService;
use Illuminate\Support\Facades\DB;

final class DashboardDataExportService
{
    public function __construct(
        private readonly EditorialOperationsDashboardService $dashboard,
        private readonly ContinuationAnalyticsService $secondRead,
        private readonly PrivacySanitizer $privacy,
    ) {}

    /** @param list<string> $sections @return array<string, mixed> */
    public function build(ExportWindow $window, array $sections): array
    {
        $snapshot = $this->dashboard->snapshot();
        $datasets = [];

        foreach ($sections as $section) {
            $datasets[$section] = match ($section) {
                'dashboard-summary' => $this->dashboardSummary($snapshot, $window),
                'content-health' => $this->contentHealth($snapshot),
                'second-read' => $this->secondRead($window),
                'newsletter-summary' => $this->newsletter($window),
            };
        }

        return $datasets;
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function dashboardSummary(array $snapshot, ExportWindow $window): array
    {
        $health = $snapshot['salute_operativa'];

        return [
            'id' => 'dashboard-summary',
            'version' => '1.0.0',
            'status' => 'available',
            'schema' => ['metric', 'value', 'denominator', 'sample', 'interval', 'status', 'unavailable_reason'],
            'rows' => collect([
                'published_articles' => $health['published_articles_total'],
                'scheduled_articles' => $snapshot['pubblicazione_readiness']['total'],
                'open_editorial_problems' => $health['open_problems_total'],
                'editorial_opportunities' => $snapshot['opportunita']['total'],
                'active_percorsi' => $health['active_percorsi_total'],
            ])->map(fn ($value, $metric) => [
                'metric' => $metric,
                'value' => $value,
                'denominator' => null,
                'sample' => $value,
                'interval' => $window->metadata(),
                'status' => 'available',
                'unavailable_reason' => null,
            ])->values()->all(),
            'limitations' => ['Snapshot operativo generato al momento dell’export; non è una serie storica.'],
        ];
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function contentHealth(array $snapshot): array
    {
        $rows = collect($snapshot['da_sistemare'])
            ->take(config('dashboard_data_export.max_rows_per_dataset'))
            ->map(fn (array $row) => [
                'content_id' => $row['article_id'],
                'title' => $this->privacy->text($row['title']),
                'public_url' => route('articolo', $row['slug']),
                'status' => 'actionable',
                'freshness' => null,
                'completeness' => null,
                'alert_type' => implode('|', collect($row['health_warnings'])->pluck('id')->all()),
                'reason' => implode('; ', array_map($this->privacy->text(...), $row['reason_summary'])),
                'last_review' => null,
                'editorial_decision' => null,
            ])->values()->all();

        return [
            'id' => 'content-health',
            'version' => '1.0.0',
            'status' => 'available',
            'schema' => ['content_id', 'title', 'public_url', 'status', 'freshness', 'completeness', 'alert_type', 'reason', 'last_review', 'editorial_decision'],
            'rows' => $rows,
            'limitations' => ['Solo allowlist di campi operativi; corpo, note private e bozze esclusi.'],
        ];
    }

    /** @return array<string, mixed> */
    private function secondRead(ExportWindow $window): array
    {
        $totals = $this->secondRead->siteWideTotals($window->from->utc(), $window->to->utc());
        $sample = $totals['impressions'];
        $available = $sample >= config('dashboard_data_export.minimum_sample_size');

        return [
            'id' => 'second-read',
            'version' => '1.0.0',
            'status' => $available ? 'available' : 'insufficient_data',
            'schema' => ['period', 'source', 'percorso', 'sessions_one_article', 'sessions_two_articles', 'second_read_rate', 'sample_size', 'status'],
            'rows' => [[
                'period' => $window->from->toDateString().' / '.$window->to->toDateString(),
                'source' => 'article_continuation_events',
                'percorso' => 'sitewide',
                'sessions_one_article' => $available ? $totals['impressions'] : null,
                'sessions_two_articles' => $available ? $totals['second_reads'] : null,
                'second_read_rate' => $available ? $totals['second_read_rate'] : null,
                'sample_size' => $sample,
                'status' => $available ? 'available' : 'insufficient_data',
            ]],
            'limitations' => ['Nessun session ID esportato; aggregato sitewide.'],
        ];
    }

    /** @return array<string, mixed> */
    private function newsletter(ExportWindow $window): array
    {
        $rows = Newsletter::query()
            ->selectRaw("COALESCE(source, 'unknown_legacy') AS source")
            ->selectRaw('COUNT(*) AS new_signups')
            ->selectRaw('SUM(CASE WHEN confirmed = 1 THEN 1 ELSE 0 END) AS confirmed')
            ->whereBetween('created_at', [$window->from->utc(), $window->to->utc()])
            ->groupBy(DB::raw("COALESCE(source, 'unknown_legacy')"))
            ->orderBy('source')
            ->limit(config('dashboard_data_export.max_rows_per_dataset'))
            ->get()
            ->map(function ($row) use ($window): array {
                $sufficient = (int) $row->new_signups >= config('dashboard_data_export.minimum_sample_size');

                return [
                    'period' => $window->from->toDateString().' / '.$window->to->toDateString(),
                    'source' => $this->privacy->text($row->source),
                    'new_signups' => $sufficient ? (int) $row->new_signups : null,
                    'confirmed' => $sufficient ? (int) $row->confirmed : null,
                    'unsubscribes' => null,
                    'recipients' => null,
                    'delivered' => null,
                    'failed' => null,
                    'clicks' => null,
                    'status' => $sufficient ? 'available' : 'insufficient_data',
                ];
            })->all();

        return [
            'id' => 'newsletter-summary',
            'version' => '1.0.0',
            'status' => 'available',
            'schema' => ['period', 'source', 'new_signups', 'confirmed', 'unsubscribes', 'recipients', 'delivered', 'failed', 'clicks', 'status'],
            'rows' => $rows,
            'limitations' => ['Email e token esclusi. Segmenti sotto soglia soppressi. Delivery/click non dichiarati senza un evento provider affidabile.'],
        ];
    }
}
