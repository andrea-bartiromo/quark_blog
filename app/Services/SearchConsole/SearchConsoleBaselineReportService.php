<?php

namespace App\Services\SearchConsole;

use App\Models\SearchConsoleQuery;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Cantiere 1 (programma "Kairus Organic Discovery"). Report read-only sui
 * dati Search Console di un periodo già importato: totali clic/impression/
 * CTR/posizione, top landing page organiche, query non-brand — riusa
 * SearchOpportunityScoringService per le opportunità già scorate (CTR
 * basso, posizione 11-20, nessuna landing page forte) invece di
 * ricalcolarle qui.
 *
 * Nessun dato per dispositivo/Paese: gli export CSV supportati da
 * SearchConsoleCsvImporter (Query, Query+Pagina) non includono queste
 * dimensioni — il report lo dichiara esplicitamente invece di fingere un
 * dato che non esiste (stesso principio di "not_measured" già usato da
 * ArticleDiscoveryAuditService).
 */
class SearchConsoleBaselineReportService
{
    public function __construct(private readonly SearchOpportunityScoringService $scoring) {}

    /** @return array<string, mixed> */
    public function report(CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        $rows = SearchConsoleQuery::query()
            ->with('article')
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->get();

        $opportunities = $this->scoring->forPeriod($periodStart, $periodEnd);

        return [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'has_data' => $rows->isNotEmpty(),
            'totals' => $this->totals($rows),
            'top_landing_pages' => $this->topLandingPages($rows),
            'non_brand_queries' => $this->nonBrandQueries($rows),
            'high_impression_low_ctr' => $opportunities
                ->where('type', SearchOpportunityScoringService::TYPE_HIGH_IMPRESSION_LOW_CTR)->values()->all(),
            'near_page_one' => $opportunities
                ->where('type', SearchOpportunityScoringService::TYPE_NEAR_PAGE_ONE)->values()->all(),
            'no_strong_landing_page' => $opportunities
                ->where('type', SearchOpportunityScoringService::TYPE_NO_STRONG_LANDING_PAGE)->values()->all(),
            // Cantiere 1: nessun export CSV supportato porta dispositivo o
            // Paese — dichiarato, mai inventato.
            'device_breakdown' => null,
            'country_breakdown' => null,
        ];
    }

    /**
     * @param  Collection<int, SearchConsoleQuery>  $rows
     * @return array{clicks:int, impressions:int, ctr:?float, position:?float}
     */
    private function totals(Collection $rows): array
    {
        $clicks = (int) $rows->sum('clicks');
        $impressions = (int) $rows->sum('impressions');

        return [
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions > 0 ? $clicks / $impressions : null,
            'position' => $this->weightedAveragePosition($rows),
        ];
    }

    /**
     * @param  Collection<int, SearchConsoleQuery>  $rows
     * @return list<array{page_url:string, article_id:?int, clicks:int, impressions:int, ctr:?float, position:?float}>
     */
    private function topLandingPages(Collection $rows, int $limit = 20): array
    {
        return $rows
            ->filter(fn (SearchConsoleQuery $row) => trim((string) $row->page_url) !== '')
            ->groupBy('page_url')
            ->map(function (Collection $group, string $pageUrl) {
                $clicks = (int) $group->sum('clicks');
                $impressions = (int) $group->sum('impressions');

                return [
                    'page_url' => $pageUrl,
                    'article_id' => $group->first()->article_id,
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $impressions > 0 ? $clicks / $impressions : null,
                    'position' => $this->weightedAveragePosition($group),
                ];
            })
            ->sortByDesc('clicks')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Query "non-brand": non contengono nessuno dei termini configurati in
     * search-console.brand_terms (default: il nome del sito). Regola
     * deterministica e testabile, mai un'euristica linguistica.
     *
     * @param  Collection<int, SearchConsoleQuery>  $rows
     * @return list<array{query:string, clicks:int, impressions:int, ctr:?float, position:?float}>
     */
    private function nonBrandQueries(Collection $rows, int $limit = 50): array
    {
        $brandTerms = array_map(
            fn ($term) => mb_strtolower((string) $term),
            config('search-console.brand_terms', [])
        );

        return $rows
            ->filter(fn (SearchConsoleQuery $row) => ! $this->isBrandQuery($row->query, $brandTerms))
            ->groupBy(fn (SearchConsoleQuery $row) => mb_strtolower(trim($row->query)))
            ->map(function (Collection $group, string $query) {
                $clicks = (int) $group->sum('clicks');
                $impressions = (int) $group->sum('impressions');

                return [
                    'query' => $group->first()->query,
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $impressions > 0 ? $clicks / $impressions : null,
                    'position' => $this->weightedAveragePosition($group),
                ];
            })
            ->sortByDesc('impressions')
            ->take($limit)
            ->values()
            ->all();
    }

    /** @param  list<string>  $brandTerms */
    private function isBrandQuery(string $query, array $brandTerms): bool
    {
        if ($brandTerms === []) {
            return false;
        }

        $normalized = mb_strtolower($query);

        foreach ($brandTerms as $term) {
            if ($term !== '' && str_contains($normalized, $term)) {
                return true;
            }
        }

        return false;
    }

    /** @param  Collection<int, SearchConsoleQuery>  $rows */
    private function weightedAveragePosition(Collection $rows): ?float
    {
        $impressions = $rows->sum('impressions');

        if ($impressions <= 0) {
            return null;
        }

        $weighted = $rows->sum(fn (SearchConsoleQuery $row) => $row->position * $row->impressions);

        return $weighted / $impressions;
    }
}
