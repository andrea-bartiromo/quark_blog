<?php

namespace App\Services\Measurement;

use App\Services\Telemetry\EditorialEventContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Measurement Closeout (Missione 3) — second-read rate con denominatore
 * ESPLICITO.
 *
 *     second-read rate = sessioni con almeno DUE articoli distinti
 *                      / sessioni con almeno UN articolo
 *
 * DUE ARTICOLI DISTINTI, non due eventi: un lettore che ricarica lo stesso
 * articolo non ha iniziato una seconda lettura. Il recorder già deduplica per
 * (sessione, articolo), quindi il COUNT(DISTINCT article_id) qui è una
 * seconda garanzia, non l'unica — e la ridondanza è voluta: se un giorno la
 * deduplicazione di sessione venisse allentata, questa metrica non
 * cambierebbe significato di nascosto.
 *
 * PERCHÉ NON RIUSA ContinuationAnalyticsService. Quel servizio (Growth S2)
 * misura una cosa diversa e continua a essere corretto per ciò che misura:
 * second_read_start / impression del solo modulo "Continua da qui", cioè un
 * tasso di clic su UNA CTA. La Missione 3 chiede un tasso di COMPORTAMENTO
 * DI SESSIONE, che include chi arriva al secondo articolo da precedente/
 * successivo, dall'indice di un Percorso, dai correlati o dalla home. I due
 * numeri non sono confrontabili e non devono essere fusi: la dashboard li
 * mostra affiancati ed etichettati, mai sommati.
 *
 * COSTO. Query di aggregazione servite dagli indici della tabella
 * (ece_session_occurred_idx, ece_name_occurred_idx), limitate alla finestra
 * (max MeasurementWindow::MAX_DAYS giorni): nessuna query per sessione.
 *
 * PRIVACY. session_key non compare MAI nel risultato: viene usato solo dentro
 * il GROUP BY e scartato. Nessun metodo di questa classe restituisce un
 * identificatore di sessione, nemmeno pseudonimo.
 */
class SecondReadRateService
{
    /**
     * Sotto questa soglia di sessioni osservate il rapporto non viene
     * pubblicato. Serve a due scopi che puntano nella stessa direzione:
     * evitare percentuali prive di senso statistico (1 sessione su 1 =
     * "100%") ed evitare che un valore calcolato su pochissime sessioni
     * diventi un dettaglio potenzialmente re-identificante.
     */
    public const MINIMUM_SESSIONS = 20;

    public const DENOMINATOR_DEFINITION = 'Sessioni pseudonime con almeno un evento article.viewed nella finestra. Numeratore: le sessioni fra queste con almeno due article.viewed su articoli DISTINTI.';

    /**
     * Tetto difensivo sulla sola segmentazione per sorgente. I totali
     * complessivi (overall()) non sono soggetti a questo limite: sono due
     * COUNT senza materializzazione di righe per sessione.
     */
    public const MAX_SESSION_ROWS = 200000;

    /**
     * @return array{
     *     rate: MetricResult,
     *     sessions_with_one_article: int,
     *     sessions_with_two_articles: int,
     *     window: array<string, mixed>,
     * }
     */
    public function overall(MeasurementWindow $window, int $minimumSessions = self::MINIMUM_SESSIONS): array
    {
        $totals = $this->sessionTotals($window);

        return [
            'rate' => MetricResult::ratio(
                $totals['sessions_with_two_articles'],
                $totals['sessions_with_one_article'],
                self::DENOMINATOR_DEFINITION,
                $minimumSessions,
            ),
            'sessions_with_one_article' => $totals['sessions_with_one_article'],
            'sessions_with_two_articles' => $totals['sessions_with_two_articles'],
            'window' => $window->toArray(),
        ];
    }

    /**
     * Segmentazione per sorgente di INGRESSO della sessione (Missione 5): la
     * prima source_channel non-interna/non-sconosciuta osservata nella
     * sessione, o — se la sessione non ne ha alcuna — la sua prima
     * osservazione in assoluto. Attribuire ogni singolo evento alla propria
     * sorgente farebbe apparire quasi tutto il traffico come 'internal'
     * (la seconda pageview in poi lo è quasi sempre): la sessione viene
     * quindi attribuita UNA VOLTA, all'ingresso.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function bySource(MeasurementWindow $window, int $minimumSessions = self::MINIMUM_SESSIONS): Collection
    {
        $sessions = $this->sessionRows($window);

        if ($sessions->isEmpty()) {
            return collect();
        }

        return $sessions
            ->groupBy('entry_source')
            ->map(function (Collection $rows, string $source) use ($minimumSessions): array {
                $withOne = $rows->count();
                $withTwo = $rows->where('distinct_articles', '>=', 2)->count();

                return [
                    'source_channel' => $source,
                    'sessions_with_one_article' => $withOne,
                    'sessions_with_two_articles' => $withTwo,
                    'rate' => MetricResult::ratio(
                        $withTwo,
                        $withOne,
                        self::DENOMINATOR_DEFINITION.' Segmentato per sorgente di ingresso della sessione.',
                        $minimumSessions,
                    ),
                ];
            })
            ->sortByDesc('sessions_with_one_article')
            ->values();
    }

    /**
     * @return array{sessions_with_one_article:int, sessions_with_two_articles:int}
     */
    private function sessionTotals(MeasurementWindow $window): array
    {
        // Sotto-query di aggregazione: una riga per sessione con il numero di
        // articoli distinti. Il COUNT esterno non materializza mai le righe
        // di sessione in PHP.
        $perSession = DB::table('editorial_continuity_events')
            ->select('session_key')
            ->selectRaw('COUNT(DISTINCT article_id) as distinct_articles')
            ->where('event_name', EditorialEventContract::ARTICLE_VIEWED)
            ->where('occurred_at', '>=', $window->startInclusive)
            ->where('occurred_at', '<', $window->endExclusive)
            ->groupBy('session_key');

        $row = DB::query()
            ->fromSub($perSession, 'per_session')
            ->selectRaw('COUNT(*) as sessions_with_one_article')
            ->selectRaw('SUM(CASE WHEN distinct_articles >= 2 THEN 1 ELSE 0 END) as sessions_with_two_articles')
            ->first();

        return [
            'sessions_with_one_article' => (int) ($row->sessions_with_one_article ?? 0),
            'sessions_with_two_articles' => (int) ($row->sessions_with_two_articles ?? 0),
        ];
    }

    /**
     * Una riga per sessione: articoli distinti + sorgente di ingresso.
     *
     * L'attribuzione dell'ingresso usa MIN(id), non MIN(occurred_at): due
     * eventi della stessa sessione possono condividere lo stesso secondo
     * (occurred_at ha granularità al secondo su tutti i driver supportati),
     * mentre l'id è sempre univoco e monotono per ordine di scrittura — la
     * sola chiave sicura per "il primo evento" senza ambiguità di join.
     *
     * BOUNDED a MAX_SESSION_ROWS. Il limite non è un troncamento silenzioso
     * sul totale: overall() non passa da qui e resta corretto anche oltre il
     * tetto; solo la segmentazione per sorgente lo rispetta.
     *
     * @return Collection<int, object{distinct_articles:int, entry_source:string}>
     */
    private function sessionRows(MeasurementWindow $window): Collection
    {
        $firstExternalId = DB::table('editorial_continuity_events')
            ->select('session_key')
            ->selectRaw('MIN(id) as entry_id')
            ->whereNotIn('source_channel', ['internal', EditorialEventContract::SOURCE_UNKNOWN])
            ->where('occurred_at', '>=', $window->startInclusive)
            ->where('occurred_at', '<', $window->endExclusive)
            ->groupBy('session_key');

        $firstAnyId = DB::table('editorial_continuity_events')
            ->select('session_key')
            ->selectRaw('MIN(id) as entry_id')
            ->where('occurred_at', '>=', $window->startInclusive)
            ->where('occurred_at', '<', $window->endExclusive)
            ->groupBy('session_key');

        return collect(DB::table('editorial_continuity_events as views')
            ->leftJoinSub($firstExternalId, 'external_entry', 'external_entry.session_key', '=', 'views.session_key')
            ->leftJoinSub($firstAnyId, 'any_entry', 'any_entry.session_key', '=', 'views.session_key')
            ->leftJoin('editorial_continuity_events as external_event', 'external_event.id', '=', 'external_entry.entry_id')
            ->leftJoin('editorial_continuity_events as any_event', 'any_event.id', '=', 'any_entry.entry_id')
            ->selectRaw('COUNT(DISTINCT views.article_id) as distinct_articles')
            ->selectRaw('COALESCE(MIN(external_event.source_channel), MIN(any_event.source_channel)) as entry_source')
            ->where('views.event_name', EditorialEventContract::ARTICLE_VIEWED)
            ->where('views.occurred_at', '>=', $window->startInclusive)
            ->where('views.occurred_at', '<', $window->endExclusive)
            ->groupBy('views.session_key')
            ->limit(self::MAX_SESSION_ROWS)
            ->get());
    }

    /**
     * Istante dell'ultimo evento di continuità osservato, in UTC ISO-8601, o
     * null se la tabella è vuota. La dashboard lo usa per dire "dati
     * aggiornati al ...", l'unica difesa contro il leggere una dashboard
     * ferma da giorni come se fosse aggiornata.
     */
    public function lastEventAt(): ?string
    {
        $value = DB::table('editorial_continuity_events')->max('occurred_at');

        return $value === null ? null : CarbonImmutable::parse($value)->utc()->toIso8601String();
    }
}
