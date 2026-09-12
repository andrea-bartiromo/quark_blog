<?php

namespace App\Services\PublicPages;

use App\Models\NotFoundHit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cantiere 24 (programma 100-cantieri Kairus). Registra, aggregato per
 * path, ogni 404 reale incontrato dal traffico pubblico — mai una riga
 * per hit, un solo record per path con un contatore, cosi' il registro
 * resta utile (e limitato) anche sotto scansioni automatiche o link
 * rotti visitati ripetutamente.
 *
 * Due esclusioni, entrambe gia' stabilite altrove in questa stessa
 * famiglia di funzionalita' e riusate qui per lo stesso motivo:
 * - `X-Kairus-Internal-Audit` (Cantiere 22/23): un audit come
 *   RedirectAndCanonicalIntegrityAudit visita deliberatamente vecchi
 *   slug che rispondono 404 come esito CORRETTO — se questo registro li
 *   contasse, ogni esecuzione dell'audit sporcherebbe il registro con
 *   404 che non sono mai stati visti da un visitatore reale.
 * - Traffico redazionale autenticato (User::canAccessRedazione(),
 *   stesso criterio di ArticleViewTrackingService): un redattore che
 *   prova un link mentre lavora non e' un segnale di link rotto per i
 *   lettori.
 */
class NotFoundHitTracker
{
    public function shouldRecord(Request $request): bool
    {
        if ($request->headers->has('X-Kairus-Internal-Audit')) {
            return false;
        }

        $user = auth()->user();

        return ! $user || ! $user->canAccessRedazione();
    }

    /**
     * Upsert atomico a livello di riga (stesso approccio di
     * ArticleViewTrackingService::incrementDailyBucket): un INSERT ... ON
     * CONFLICT/ON DUPLICATE KEY UPDATE, non una lettura seguita da una
     * scrittura, sicuro sotto richieste concorrenti sullo stesso path.
     */
    public function recordHit(Request $request): void
    {
        if (! $this->shouldRecord($request)) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');
        $path = substr($path, 0, 255);
        $referer = $request->headers->get('referer');
        $referer = $referer !== null ? substr($referer, 0, 1000) : null;
        $now = now()->toDateTimeString();

        match (DB::connection()->getDriverName()) {
            'sqlite', 'pgsql' => DB::statement(
                'INSERT INTO not_found_hits (path, hits, last_referer, first_seen_at, last_seen_at, created_at, updated_at)
                 VALUES (?, 1, ?, ?, ?, ?, ?)
                 ON CONFLICT (path) DO UPDATE SET
                    hits = not_found_hits.hits + 1,
                    last_referer = excluded.last_referer,
                    last_seen_at = excluded.last_seen_at,
                    updated_at = excluded.updated_at',
                [$path, $referer, $now, $now, $now, $now]
            ),
            default => DB::statement(
                'INSERT INTO not_found_hits (path, hits, last_referer, first_seen_at, last_seen_at, created_at, updated_at)
                 VALUES (?, 1, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    hits = hits + 1,
                    last_referer = VALUES(last_referer),
                    last_seen_at = VALUES(last_seen_at),
                    updated_at = VALUES(updated_at)',
                [$path, $referer, $now, $now, $now, $now]
            ),
        };
    }

    /**
     * @return list<NotFoundHit>
     */
    public function topHits(int $limit = 50): array
    {
        return NotFoundHit::query()
            ->orderByDesc('hits')
            ->orderByDesc('last_seen_at')
            ->take($limit)
            ->get()
            ->all();
    }
}
