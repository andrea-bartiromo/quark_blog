<?php

namespace App\Services\SearchConsole;

use App\Models\SearchConsoleQuery;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Motore di scoring per le "opportunità di ricerca" — v1 esplicitamente
 * basata su formule leggibili a mano, non su un modello statistico o
 * machine learning: ogni punteggio deve poter essere spiegato in una frase
 * a un redattore senza background tecnico. Vedi
 * docs/SEARCH_OPPORTUNITIES.md per le formule complete e le assunzioni.
 *
 * La curva CTR-attesa-per-posizione è una stima approssimativa comunemente
 * citata nel settore, non un dato misurato su Kairus (che non esiste
 * ancora): serve solo a distinguere "ben sotto quanto ci si aspetterebbe"
 * da rumore statistico, non come verità assoluta. Andrebbe sostituita con
 * medie osservate reali di Kairus non appena ce ne sono a sufficienza.
 */
class SearchOpportunityScoringService
{
    public const TYPE_HIGH_IMPRESSION_LOW_CTR = 'high_impression_low_ctr';

    public const TYPE_GOOD_POSITION_LOW_CTR = 'good_position_low_ctr';

    public const TYPE_NEAR_PAGE_ONE = 'near_page_one';

    public const TYPE_NO_STRONG_LANDING_PAGE = 'no_strong_landing_page';

    public const TYPE_RISING_QUERY = 'rising_query';

    /**
     * Soglia minima di evidenza: sotto questo numero di impression una
     * query è statisticamente troppo rumorosa per generare
     * un'"opportunità" affidabile (es. 3 impression con 0 click su
     * posizione 15 non dice nulla). Punto di partenza dichiaratamente
     * arbitrario, regolabile.
     */
    public const MIN_IMPRESSIONS = 20;

    /**
     * Soglia minima di impression nel periodo precedente perché una
     * variazione percentuale sia significativa (evita che 1→5 impression
     * risulti in un fuorviante "+400%").
     */
    private const MIN_PREVIOUS_IMPRESSIONS_FOR_TREND = 10;

    private const EXPECTED_CTR_BY_POSITION = [
        1 => 0.28, 2 => 0.15, 3 => 0.10, 4 => 0.07, 5 => 0.06,
        6 => 0.05, 7 => 0.04, 8 => 0.03, 9 => 0.03, 10 => 0.025,
    ];

    private const EXPECTED_CTR_11_20 = 0.01;

    private const EXPECTED_CTR_BEYOND_20 = 0.005;

    /**
     * @return Collection<int, SearchOpportunity>
     */
    public function forPeriod(CarbonInterface $periodStart, CarbonInterface $periodEnd, ?CarbonInterface $previousPeriodStart = null, ?CarbonInterface $previousPeriodEnd = null): Collection
    {
        $rows = SearchConsoleQuery::query()
            ->with('article')
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->get();

        $opportunities = collect();

        foreach ($rows as $row) {
            if ($row->impressions < self::MIN_IMPRESSIONS) {
                continue;
            }

            $expectedCtr = $this->expectedCtrForPosition($row->position);

            if ($row->position <= 10.0 && $row->ctr < $expectedCtr * 0.6) {
                $opportunities->push($this->goodPositionLowCtr($row, $expectedCtr));

                continue;
            }

            if ($row->ctr < $expectedCtr * 0.5) {
                $opportunities->push($this->highImpressionLowCtr($row, $expectedCtr));

                continue;
            }

            if ($row->position > 10.0 && $row->position <= 20.0) {
                $opportunities->push($this->nearPageOne($row));
            }
        }

        $opportunities = $opportunities->merge($this->noStrongLandingPage($rows));

        if ($previousPeriodStart && $previousPeriodEnd) {
            $opportunities = $opportunities->merge(
                $this->risingQueries($periodStart, $periodEnd, $previousPeriodStart, $previousPeriodEnd)
            );
        }

        return $opportunities->sortByDesc(fn (SearchOpportunity $o) => $o->score)->values();
    }

    private function expectedCtrForPosition(float $position): float
    {
        $rounded = (int) round($position);

        if ($rounded <= 10) {
            return self::EXPECTED_CTR_BY_POSITION[max(1, $rounded)];
        }

        return $rounded <= 20 ? self::EXPECTED_CTR_11_20 : self::EXPECTED_CTR_BEYOND_20;
    }

    private function goodPositionLowCtr(SearchConsoleQuery $row, float $expectedCtr): SearchOpportunity
    {
        $missedClicks = (int) round($row->impressions * ($expectedCtr - $row->ctr));

        return new SearchOpportunity(
            type: self::TYPE_GOOD_POSITION_LOW_CTR,
            query: $row->query,
            article: $row->article,
            impressions: $row->impressions,
            clicks: $row->clicks,
            ctr: $row->ctr,
            position: $row->position,
            score: max(0, $missedClicks),
            explanation: sprintf(
                'In posizione %.1f (pagina 1) ma CTR %.1f%% contro un atteso ~%.1f%%: titolo o meta description probabilmente poco invitanti. Stima ~%d click persi nel periodo.',
                $row->position,
                $row->ctr * 100,
                $expectedCtr * 100,
                max(0, $missedClicks)
            ),
        );
    }

    private function highImpressionLowCtr(SearchConsoleQuery $row, float $expectedCtr): SearchOpportunity
    {
        $missedClicks = (int) round($row->impressions * ($expectedCtr - $row->ctr));

        return new SearchOpportunity(
            type: self::TYPE_HIGH_IMPRESSION_LOW_CTR,
            query: $row->query,
            article: $row->article,
            impressions: $row->impressions,
            clicks: $row->clicks,
            ctr: $row->ctr,
            position: $row->position,
            score: max(0, $missedClicks),
            explanation: sprintf(
                '%d impression nel periodo ma CTR solo %.1f%% (atteso ~%.1f%% per la posizione %.1f). Stima ~%d click persi.',
                $row->impressions,
                $row->ctr * 100,
                $expectedCtr * 100,
                $row->position,
                max(0, $missedClicks)
            ),
        );
    }

    private function nearPageOne(SearchConsoleQuery $row): SearchOpportunity
    {
        // Piu' vicino alla posizione 10 e piu' impression = punteggio piu'
        // alto: entrambi i fattori rendono plausibile e valere la pena una
        // piccola spinta editoriale (aggiornamento, link interni) per
        // raggiungere la pagina 1.
        $score = $row->impressions / max(1.0, $row->position);

        return new SearchOpportunity(
            type: self::TYPE_NEAR_PAGE_ONE,
            query: $row->query,
            article: $row->article,
            impressions: $row->impressions,
            clicks: $row->clicks,
            ctr: $row->ctr,
            position: $row->position,
            score: $score,
            explanation: sprintf(
                'Posizione %.1f (appena fuori dalla pagina 1) con %d impression: una piccola spinta editoriale potrebbe portarla in pagina 1.',
                $row->position,
                $row->impressions
            ),
        );
    }

    /**
     * @param  Collection<int, SearchConsoleQuery>  $rows
     * @return Collection<int, SearchOpportunity>
     */
    private function noStrongLandingPage(Collection $rows): Collection
    {
        return $rows->groupBy('query')
            ->map(function (Collection $queryRows, string $query) {
                $totalImpressions = $queryRows->sum('impressions');
                $hasArticle = $queryRows->contains(fn (SearchConsoleQuery $r) => $r->article_id !== null);

                if ($hasArticle || $totalImpressions < self::MIN_IMPRESSIONS) {
                    return null;
                }

                return new SearchOpportunity(
                    type: self::TYPE_NO_STRONG_LANDING_PAGE,
                    query: $query,
                    article: null,
                    impressions: (int) $totalImpressions,
                    clicks: (int) $queryRows->sum('clicks'),
                    ctr: null,
                    position: null,
                    score: $totalImpressions,
                    explanation: sprintf(
                        '%d impression per questa query ma nessuna pagina risultante corrisponde a un articolo Kairus: possibile lacuna di contenuto.',
                        $totalImpressions
                    ),
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, SearchOpportunity>
     */
    private function risingQueries(
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        CarbonInterface $previousPeriodStart,
        CarbonInterface $previousPeriodEnd
    ): Collection {
        $current = SearchConsoleQuery::query()
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->get()
            ->groupBy('query')
            ->map(fn (Collection $rows) => $rows->sum('impressions'));

        $previous = SearchConsoleQuery::query()
            ->whereDate('period_start', $previousPeriodStart->toDateString())
            ->whereDate('period_end', $previousPeriodEnd->toDateString())
            ->get()
            ->groupBy('query')
            ->map(fn (Collection $rows) => $rows->sum('impressions'));

        $opportunities = collect();

        foreach ($current as $query => $impressions) {
            $previousImpressions = $previous->get($query, 0);

            if ($impressions < self::MIN_IMPRESSIONS || $previousImpressions < self::MIN_PREVIOUS_IMPRESSIONS_FOR_TREND) {
                continue;
            }

            $growth = ($impressions - $previousImpressions) / $previousImpressions;

            if ($growth <= 0.5) {
                continue;
            }

            $opportunities->push(new SearchOpportunity(
                type: self::TYPE_RISING_QUERY,
                query: $query,
                article: null,
                impressions: (int) $impressions,
                clicks: 0,
                ctr: null,
                position: null,
                score: $growth,
                explanation: sprintf(
                    'Impression salite da %d a %d (+%.0f%%) rispetto al periodo precedente: interesse crescente, momento utile per approfondire il tema.',
                    $previousImpressions,
                    $impressions,
                    $growth * 100
                ),
            ));
        }

        return $opportunities;
    }
}
