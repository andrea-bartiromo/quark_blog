<?php

namespace App\Services\PublicPages;

use App\Models\NotFoundHit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
 *
 * Limite noto e accettato di quest'ultima esclusione (Codex, PR #572,
 * verificato empiricamente): funziona solo quando il 404 proviene da
 * una rotta effettivamente risolta (es. ArticleController::show() con
 * uno slug inesistente), MAI per un path che non corrisponde a NESSUNA
 * rotta — il routing lancia il 404 prima che il gruppo 'web' (e quindi
 * StartSession) sia mai eseguito per quella richiesta, quindi
 * auth()->user() risulta sempre un guest anche per un redattore con
 * un cookie di sessione valido. Un fix generale (Route::fallback() nel
 * gruppo 'web') e' stato tentato e SCARTATO dopo aver riprodotto
 * concretamente una regressione peggiore: un fallback GET intercetta
 * l'individuazione dei verbi alternativi di Laravel
 * (Illuminate\Routing\AbstractRouteCollection::matchAgainstRoutes()),
 * trasformando ogni 405 dell'app in un 404. Impatto accettato: un
 * link mal digitato da un redattore autenticato verso un path del
 * tutto inesistente puo' comparire in questo registro — rumore
 * minimo e autoreferenziale, mai una corruzione di analytics o
 * contenuti reali. Vedi
 * tests/Feature/NotFoundHitsRegistrationTest.php per la verifica e la
 * spiegazione completa (incluso perche' actingAs() o un login reale
 * nello stesso metodo di test non possono riprodurre questo scenario).
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
     *
     * La chiave di conflitto e' `path_hash` (sha256 del path COMPLETO,
     * mai troncato) e non il path stesso: un unique su una stringa
     * troncata a 255 byte farebbe collidere path distinti piu' lunghi
     * che condividono lo stesso prefisso, e su MariaDB (collation
     * case-insensitive di produzione) farebbe collidere anche path che
     * differiscono solo per maiuscole/minuscole (Codex, PR #572).
     *
     * Un fallimento di questa scrittura (connessione assente, tabella
     * non ancora migrata, lock) non deve MAI trasformare il 404
     * originale in un 500: questo registro e' puramente osservativo, la
     * sua disponibilita' non puo' condizionare la risposta mostrata al
     * visitatore (Codex, PR #572) — l'eccezione viene quindi loggata,
     * mai rilanciata.
     */
    public function recordHit(Request $request): void
    {
        if (! $this->shouldRecord($request)) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');
        $pathHash = hash('sha256', $path);
        $referer = $request->headers->get('referer');
        $referer = $referer !== null ? substr($referer, 0, 1000) : null;
        $now = now()->toDateTimeString();

        try {
            match (DB::connection()->getDriverName()) {
                'sqlite', 'pgsql' => DB::statement(
                    'INSERT INTO not_found_hits (path_hash, path, hits, last_referer, first_seen_at, last_seen_at, created_at, updated_at)
                     VALUES (?, ?, 1, ?, ?, ?, ?, ?)
                     ON CONFLICT (path_hash) DO UPDATE SET
                        hits = not_found_hits.hits + 1,
                        last_referer = excluded.last_referer,
                        last_seen_at = excluded.last_seen_at,
                        updated_at = excluded.updated_at',
                    [$pathHash, $path, $referer, $now, $now, $now, $now]
                ),
                default => DB::statement(
                    'INSERT INTO not_found_hits (path_hash, path, hits, last_referer, first_seen_at, last_seen_at, created_at, updated_at)
                     VALUES (?, ?, 1, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        hits = hits + 1,
                        last_referer = VALUES(last_referer),
                        last_seen_at = VALUES(last_seen_at),
                        updated_at = VALUES(updated_at)',
                    [$pathHash, $path, $referer, $now, $now, $now, $now]
                ),
            };
        } catch (Throwable $e) {
            Log::warning('NotFoundHitTracker: impossibile registrare il 404, la risposta originale non è comunque compromessa.', [
                'path' => $path,
                'exception' => $e->getMessage(),
            ]);
        }
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
