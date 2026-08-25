<?php

namespace App\Services\EditorialRadar\Providers;

use App\Models\Article;
use App\Models\SearchConsoleQuery;
use App\Models\SearchOpportunityStatus;
use App\Services\SearchConsole\SearchOpportunity;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use App\Services\SearchConsole\SearchOpportunityStatusService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Adapter between the already-merged Search Console intelligence and Radar.
 *
 * The Search Console service may use a numeric score internally to sort its
 * own list, but Radar deliberately exposes only discrete priority + the
 * provider's human-readable evidence. No opaque score crosses this boundary.
 *
 * Missione 48/49 (secondo batch autonomo KAIRUS, Fase F — Search
 * Intelligence): SearchOpportunityStatusService era stato costruito prima
 * che questo provider esistesse ("altra corsia, non ancora su main" —
 * docblock originale del servizio), quindi restava deliberatamente isolato.
 * Ora che entrambi sono su main, un'opportunità già "gestita" o "ignorata"
 * dalla redazione (via /admin/search-opportunities) non deve continuare a
 * ricomparire per sempre qui — mina il senso stesso del workflow di stato.
 * "Vista" resta invece visibile: significa solo "notata", non "chiusa".
 */
class SearchConsoleOpportunityProvider
{
    private const CLOSED_STATUSES = [
        SearchOpportunityStatus::STATUS_ACTIONED,
        SearchOpportunityStatus::STATUS_DISMISSED,
    ];

    public function __construct(
        private readonly SearchOpportunityScoringService $scoring,
        private readonly SearchOpportunityStatusService $statuses,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function opportunities(): Collection
    {
        $periods = SearchConsoleQuery::query()
            ->selectRaw('period_start, period_end')
            ->distinct()
            ->orderByDesc('period_start')
            ->limit(2)
            ->get();

        if ($periods->isEmpty()) {
            return collect();
        }

        $latest = $periods->first();
        $previous = $periods->get(1);
        $signals = $this->scoring->forPeriod(
            Carbon::parse($latest->period_start),
            Carbon::parse($latest->period_end),
            $previous ? Carbon::parse($previous->period_start) : null,
            $previous ? Carbon::parse($previous->period_end) : null,
        );

        // A Search Console row can retain an article_id after editorial state
        // changes. Radar must never present a draft/review/scheduled Article as
        // a public SEO opportunity, so validate all linked IDs in one bounded
        // published() query rather than one exists() query per signal.
        $linkedIds = $signals
            ->map(fn (SearchOpportunity $signal) => $signal->article?->id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $publicIds = $linkedIds->isEmpty()
            ? collect()
            : Article::query()
                ->published()
                ->whereIn('id', $linkedIds)
                ->pluck('id')
                ->mapWithKeys(fn ($id) => [(int) $id => true]);

        $statusByKey = $this->statuses->statusesFor($signals);

        return $signals
            ->reject(fn (SearchOpportunity $signal) => $signal->article !== null && ! $publicIds->has((int) $signal->article->id)
            )
            ->reject(fn (SearchOpportunity $signal) => in_array($statusByKey[$signal->key] ?? SearchOpportunityStatus::STATUS_NEW, self::CLOSED_STATUSES, true))
            ->map(fn (SearchOpportunity $signal) => $this->normalize($signal))
            ->sortBy(fn (array $row) => [
                match ($row['priority']) {
                    'HIGH' => 1, 'MEDIUM' => 2, default => 3
                },
                $row['type'],
                $row['key'],
            ])
            ->values();
    }

    /** @return array<string, mixed> */
    private function normalize(SearchOpportunity $signal): array
    {
        [$type, $priority, $action] = match ($signal->type) {
            SearchOpportunityScoringService::TYPE_HIGH_IMPRESSION_LOW_CTR,
            SearchOpportunityScoringService::TYPE_GOOD_POSITION_LOW_CTR => [
                'CTR_IMPROVEMENT',
                'HIGH',
                'Rivedi manualmente titolo e meta description senza cambiare copy automaticamente.',
            ],
            SearchOpportunityScoringService::TYPE_NEAR_PAGE_ONE => [
                'SEO_OPPORTUNITY',
                'MEDIUM',
                'Valuta aggiornamento editoriale e link interni pertinenti prima di intervenire.',
            ],
            SearchOpportunityScoringService::TYPE_NO_STRONG_LANDING_PAGE => [
                'NEW_ARTICLE',
                'HIGH',
                'Verifica la copertura editoriale esistente; crea nuovo contenuto solo dopo revisione umana.',
            ],
            SearchOpportunityScoringService::TYPE_RISING_QUERY => [
                'SEO_OPPORTUNITY',
                'MEDIUM',
                'Verifica se contenuti esistenti coprono bene l’interesse crescente prima di decidere update o nuovo articolo.',
            ],
            default => [
                'SEO_OPPORTUNITY',
                'MEDIUM',
                'Rivedi manualmente il segnale Search Console.',
            ],
        };

        $articleId = $signal->article?->id;
        $articleSlug = $signal->article?->slug;
        $identity = $articleId !== null ? 'article:'.$articleId : 'query:'.sha1($signal->query);

        return [
            'key' => 'search-console:'.$signal->type.':'.$identity,
            'type' => $type,
            'provider' => 'search_console',
            'priority' => $priority,
            'article_id' => $articleId,
            'article_slug' => $articleSlug,
            'title' => $signal->article?->title ?? 'Query: '.$signal->query,
            'detected' => $this->label($signal->type),
            'why' => $signal->explanation,
            'suggested_action' => $action,
            'search_query' => $signal->query,
        ];
    }

    private function label(string $type): string
    {
        return match ($type) {
            SearchOpportunityScoringService::TYPE_HIGH_IMPRESSION_LOW_CTR => 'Molte impression con CTR sotto attese',
            SearchOpportunityScoringService::TYPE_GOOD_POSITION_LOW_CTR => 'Buona posizione con CTR sotto attese',
            SearchOpportunityScoringService::TYPE_NEAR_PAGE_ONE => 'Query vicina alla prima pagina',
            SearchOpportunityScoringService::TYPE_NO_STRONG_LANDING_PAGE => 'Query senza articolo Kairus dedicato',
            SearchOpportunityScoringService::TYPE_RISING_QUERY => 'Query in crescita',
            default => 'Segnale Search Console',
        };
    }
}
