<?php

namespace App\Services\Search;

use App\Models\SearchZeroResultQuery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mission 31 — Search Zero-Result Diagnostics.
 *
 * Registra, in forma aggregata, quali query digitate in /ricerca non
 * restituiscono alcun risultato utile — segnale editoriale di contenuto
 * mancante, non un log per-visita. Una riga per query normalizzata
 * (colonna `hit_count` incrementata), mai un evento per ricerca: "Prefer
 * normalized query/count/time aggregates" dalla formulazione della
 * missione. Nessun identificativo di sessione/utente/IP/user-agent viene
 * mai persistito — "Do not store unnecessary personal information".
 *
 * La normalizzazione qui è deliberatamente più leggera di
 * TrovaEntitySearchService::normalize() / SearchTokenizer::tokenize():
 * questa tabella è pensata per una lettura editoriale umana ("quali frasi
 * reali falliscono"), non per il matching — conservare accenti e ordine
 * delle parole la rende leggibile, mentre il solo trim/lowercase/
 * collasso spazi/punteggiatura Unicode basta a evitare che "Buco Nero",
 * "buco nero" e "buco  nero" (doppio spazio) si contino come tre voci
 * distinte.
 *
 * Fail-open per design, stesso principio già applicato da
 * ContinuationAnalyticsService::recordOnce(): un fallimento di scrittura
 * qui non deve mai impedire la lettura pubblica della pagina di ricerca.
 */
class SearchZeroResultDiagnosticsService
{
    public function __construct(
        private readonly SearchTokenizer $tokenizer,
    ) {}

    public function record(string $rawQuery): void
    {
        $normalized = $this->normalize($rawQuery);

        if ($normalized === '') {
            return;
        }

        try {
            $existing = SearchZeroResultQuery::query()
                ->where('normalized_query', $normalized)
                ->first();

            if ($existing) {
                $existing->increment('hit_count');

                return;
            }

            try {
                SearchZeroResultQuery::create([
                    'normalized_query' => $normalized,
                    'hit_count' => 1,
                ]);
            } catch (QueryException $raceOnUniqueConstraint) {
                // Un'altra richiesta ha inserito la stessa normalized_query
                // tra la SELECT e la INSERT qui sopra (basso traffico, corsa
                // rara ma possibile): converge sullo stesso risultato di un
                // hit_count corretto invece di far fallire la ricerca.
                SearchZeroResultQuery::query()
                    ->where('normalized_query', $normalized)
                    ->increment('hit_count');
            }
        } catch (\Throwable $exception) {
            Log::warning('SearchZeroResultDiagnosticsService: scrittura fallita, la ricerca pubblica non è stata bloccata.', [
                'normalized_query' => $normalized,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Lettura diagnostica di sola lettura: le query a zero risultati più
     * frequenti, più recenti prima a parità di conteggio.
     *
     * @return Collection<int, SearchZeroResultQuery>
     */
    public function topUnresolved(int $limit = 50): Collection
    {
        return SearchZeroResultQuery::query()
            ->orderByDesc('hit_count')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    private function normalize(string $rawQuery): string
    {
        $normalized = $this->tokenizer->normalizeUnicodePunctuation(trim($rawQuery));

        return Str::of($normalized)->lower()->squish()->value();
    }
}
