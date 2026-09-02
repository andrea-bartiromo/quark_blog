<?php

namespace App\Services\Measurement;

use App\Models\EditorialContinuityEvent;
use App\Services\Telemetry\EditorialEventContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Measurement Closeout (Missione 4) — path continuation rate con
 * denominatore CORRETTO.
 *
 *     path continuation rate = view successive alla stessa sessione sullo
 *                               stesso articolo target dopo un evento
 *                               article.transition_available
 *                             / eventi article.transition_available
 *
 * IL DENOMINATORE ESPLICITAMENTE RICHIESTO E VIETATO. La missione vieta
 * "tutte le pageview" come denominatore: un articolo che è l'ultimo di un
 * Percorso non ha un "successivo" da cliccare, un articolo senza Percorso
 * non ha "precedente"/"successivo" affatto. Contare quelle view al
 * denominatore sottostimerebbe artificialmente il tasso. Il recorder
 * (EditorialContinuityRecorder::recordTransitionAvailable) scrive un evento
 * SOLO quando il controllo era davvero renderizzato — quell'evento, non la
 * pageview, è il denominatore.
 *
 * "SEGUITA" SIGNIFICA COSA. Un evento transition_available registra
 * (sessione, articolo sorgente, articolo target, tipo). La transizione è
 * "seguita" se la STESSA sessione genera poi un article.viewed sul target —
 * entro la stessa finestra di misura, senza limite di tempo interno ad essa:
 * non esiste nel dominio una nozione di "troppo tardi per contare come la
 * stessa visita" più fine della finestra stessa, e inventarne una
 * introdurrebbe una soglia arbitraria che nessun requisito chiede.
 *
 * DUPLICATI. Un secondo transition_available per la stessa coppia
 * (sessione, target, tipo) non può esistere: il recorder deduplica per
 * (sessione, transition_type, source, target) prima ancora di questa
 * classe. Una transizione "seguita" due volte nella stessa sessione (avanti,
 * indietro, avanti) conta comunque UNA volta al numeratore: il CTR misura
 * "quante transizioni disponibili sono state prese", non "quanti click".
 */
class PathContinuationRateService
{
    /**
     * Sotto questa soglia di controlli disponibili osservati il rapporto non
     * viene pubblicato. Stessa motivazione statistica/privacy di
     * SecondReadRateService::MINIMUM_SESSIONS.
     */
    public const MINIMUM_AVAILABLE = 20;

    public const DENOMINATOR_DEFINITION = 'Eventi article.transition_available nella finestra: SOLO le pagine in cui il controllo di transizione era davvero renderizzato, mai tutte le pageview. Numeratore: fra questi, quelli seguiti da un article.viewed della stessa sessione sull\'articolo target.';

    /**
     * Tasso complessivo, o segmentato per Percorso/tipo di transizione se
     * richiesto. mobile/desktop non è incluso: nessun evento di questo
     * gruppo registra la classe di dispositivo (vedi
     * docs/MEASUREMENT_CLOSEOUT.md, "Cosa non è misurabile") — introdurla
     * richiederebbe un nuovo campo mai raccolto, quindi la missione la rende
     * esplicitamente condizionale ("solo se il dato esiste realmente") e qui
     * non esiste.
     *
     * @return array{
     *     rate: MetricResult,
     *     available: int,
     *     followed: int,
     *     window: array<string, mixed>,
     * }
     */
    public function overall(MeasurementWindow $window, int $minimumAvailable = self::MINIMUM_AVAILABLE): array
    {
        $totals = $this->totals($window);

        return [
            'rate' => MetricResult::ratio(
                $totals['followed'],
                $totals['available'],
                self::DENOMINATOR_DEFINITION,
                $minimumAvailable,
            ),
            'available' => $totals['available'],
            'followed' => $totals['followed'],
            'window' => $window->toArray(),
        ];
    }

    /**
     * Segmentazione per tipo di transizione: previous / next / continua_da_qui
     * / pillar / article_in_path — esattamente i cinque tipi allowlisted da
     * EditorialEventContract::TRANSITION_TYPES, mai un sesto inventato qui.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function byTransitionType(MeasurementWindow $window, int $minimumAvailable = self::MINIMUM_AVAILABLE): Collection
    {
        return collect(EditorialEventContract::TRANSITION_TYPES)
            ->map(function (string $type) use ($window, $minimumAvailable): array {
                $totals = $this->totals($window, transitionType: $type);

                return [
                    'transition_type' => $type,
                    'available' => $totals['available'],
                    'followed' => $totals['followed'],
                    'rate' => MetricResult::ratio(
                        $totals['followed'],
                        $totals['available'],
                        self::DENOMINATOR_DEFINITION.' Segmentato per tipo di transizione.',
                        $minimumAvailable,
                    ),
                ];
            })
            ->filter(fn (array $row) => $row['available'] > 0)
            ->values();
    }

    /**
     * Segmentazione per Percorso. Solo le righe con content_cluster_id
     * valorizzato: la transizione "continua_da_qui" può avvenire fuori da un
     * Percorso (fallback di categoria di ArticleContinuationService) — quelle
     * righe restano nel totale complessivo ma non compaiono qui, dove
     * "Percorso" è per definizione la chiave di raggruppamento.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function byPath(MeasurementWindow $window, int $minimumAvailable = self::MINIMUM_AVAILABLE, int $limit = 50): Collection
    {
        $limit = max(1, min($limit, 200));

        $available = DB::table('editorial_continuity_events')
            ->select('content_cluster_id')
            ->selectRaw('COUNT(*) as available_count')
            ->where('event_name', EditorialEventContract::TRANSITION_AVAILABLE)
            ->whereNotNull('content_cluster_id')
            ->where('occurred_at', '>=', $window->startInclusive)
            ->where('occurred_at', '<', $window->endExclusive)
            ->groupBy('content_cluster_id');

        $rows = DB::query()
            ->fromSub($available, 'avail')
            ->join('content_clusters', 'content_clusters.id', '=', 'avail.content_cluster_id')
            ->select(['avail.content_cluster_id', 'content_clusters.name', 'content_clusters.slug', 'avail.available_count'])
            ->orderByDesc('avail.available_count')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) use ($window, $minimumAvailable): array {
            $followed = $this->followedCount($window, contentClusterId: (int) $row->content_cluster_id);

            return [
                'content_cluster_id' => (int) $row->content_cluster_id,
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
                'available' => (int) $row->available_count,
                'followed' => $followed,
                'rate' => MetricResult::ratio(
                    $followed,
                    (int) $row->available_count,
                    self::DENOMINATOR_DEFINITION.' Segmentato per Percorso.',
                    $minimumAvailable,
                ),
            ];
        });
    }

    /**
     * @return array{available:int, followed:int}
     */
    private function totals(MeasurementWindow $window, ?string $transitionType = null): array
    {
        $available = DB::table('editorial_continuity_events')
            ->where('event_name', EditorialEventContract::TRANSITION_AVAILABLE)
            ->where('occurred_at', '>=', $window->startInclusive)
            ->where('occurred_at', '<', $window->endExclusive)
            ->when($transitionType, fn ($query) => $query->where('transition_type', $transitionType))
            ->count();

        $followed = $this->followedCount($window, transitionType: $transitionType);

        return ['available' => $available, 'followed' => $followed];
    }

    /**
     * Conta gli eventi transition_available seguiti da un article.viewed
     * della STESSA sessione sull'articolo target, DOPO l'evento di
     * disponibilità (occurred_at maggiore o uguale, id maggiore a parità di
     * secondo — stessa cautela sulla granularità del timestamp già motivata
     * in SecondReadRateService).
     *
     * whereExists invece di un JOIN: un target può essere raggiunto da più
     * article.viewed nella stessa sessione (reload dopo il primo arrivo, che
     * il recorder di article.viewed NON deduplica per target ma per singolo
     * articolo — un secondo arrivo sullo stesso target nella stessa sessione
     * scriverebbe comunque un solo article.viewed grazie alla sua stessa
     * deduplicazione). EXISTS restituisce quindi "seguita: sì/no" per riga,
     * mai un conteggio gonfiato da eventuali righe multiple sul lato destro.
     */
    private function followedCount(
        MeasurementWindow $window,
        ?string $transitionType = null,
        ?int $contentClusterId = null,
    ): int {
        return DB::table('editorial_continuity_events as avail')
            ->where('avail.event_name', EditorialEventContract::TRANSITION_AVAILABLE)
            ->where('avail.occurred_at', '>=', $window->startInclusive)
            ->where('avail.occurred_at', '<', $window->endExclusive)
            ->when($transitionType, fn ($query) => $query->where('avail.transition_type', $transitionType))
            ->when($contentClusterId, fn ($query) => $query->where('avail.content_cluster_id', $contentClusterId))
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('editorial_continuity_events as viewed')
                    ->where('viewed.event_name', EditorialEventContract::ARTICLE_VIEWED)
                    ->whereColumn('viewed.session_key', 'avail.session_key')
                    ->whereColumn('viewed.article_id', 'avail.target_article_id')
                    ->where(function ($column) {
                        $column->whereColumn('viewed.occurred_at', '>', 'avail.occurred_at')
                            ->orWhere(function ($equalSecond) {
                                $equalSecond->whereColumn('viewed.occurred_at', '=', 'avail.occurred_at')
                                    ->whereColumn('viewed.id', '>', 'avail.id');
                            });
                    });
            })
            ->count();
    }

    public function lastEventAt(): ?string
    {
        $value = EditorialContinuityEvent::query()->max('occurred_at');

        return $value === null ? null : CarbonImmutable::parse($value)->utc()->toIso8601String();
    }
}
