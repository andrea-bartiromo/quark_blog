<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Estende `/up` (route health già registrata da bootstrap/app.php,
 * `->withRouting(health: '/up', ...)`) da semplice liveness ("Laravel si è
 * avviato") a readiness reale ("l'app può davvero servire traffico").
 *
 * Meccanismo del framework (non nostro): la route `/up` dispatcha
 * Illuminate\Foundation\Events\DiagnosingHealth dentro un try/catch — se un
 * listener lancia un'eccezione, la route risponde 500 (JSON:
 * {"status":"down"}, oppure la vista health-up.blade.php stock di Laravel
 * per richieste non-JSON, che NON stampa mai il messaggio dell'eccezione
 * nel markup — verificato leggendo
 * vendor/laravel/framework/.../health-up.blade.php prima di scrivere
 * questo listener). L'eccezione viene comunque registrata via report($e),
 * quindi il dettaglio reale resta nei log applicativi, mai nella risposta
 * HTTP.
 *
 * Messaggi di eccezione qui SEMPRE generici (mai l'eccezione PDO originale,
 * che su alcuni driver include host/utente) — la diagnosi reale va cercata
 * nei log applicativi (vedi sotto), non nella risposta dell'endpoint.
 *
 * Due controlli, entrambi a overhead minimo (misurato: vedi
 * HealthCheckOverheadTest):
 *   - DB: DB::connection()->getPdo() — stabilisce/riusa la connessione,
 *     nessuna query aggiuntiva.
 *   - Storage: is_writable() sulla directory storage/app — solo uno stat
 *     di permessi, nessuna scrittura reale a ogni chiamata (trade-off
 *     deliberato: non rileva un disco pieno-ma-scrivibile per permessi,
 *     solo un problema di permessi/mount — scrittura reale scartata per
 *     non introdurre I/O extra a ogni hit di un endpoint potenzialmente
 *     chiamato spesso da un monitor esterno).
 */
class CheckApplicationHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        $this->checkDatabase();
        $this->checkStorage();
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            Log::error('Health check: database non raggiungibile.', [
                'operation' => 'health_check.database',
                'exception_class' => $e::class,
            ]);

            throw new RuntimeException('database_unreachable', previous: $e);
        }
    }

    private function checkStorage(): void
    {
        $path = storage_path('app');

        if (! is_writable($path)) {
            Log::error('Health check: storage non scrivibile.', [
                'operation' => 'health_check.storage',
            ]);

            throw new RuntimeException('storage_not_writable');
        }
    }
}
