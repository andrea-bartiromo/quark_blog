<?php

namespace App\Console\Commands;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/**
 * Incidente reale (rilascio controllato di main@0907b4e, dopo PR #540):
 * `newsletter:reconfirmation-cleanup` era schedulato in
 * routes/console.php e risultava correttamente registrato secondo ogni
 * verifica statica (bootstrap/app.php) e in-process (un test PHPUnit
 * nello stesso processo già bootstrappato dal framework di test), eppure
 * un vero sottoprocesso `php artisan newsletter:reconfirmation-cleanup
 * --dry-run` nella release effettiva falliva con "Command is not
 * defined". Nessuno dei due tipi di verifica precedenti poteva rilevarlo:
 * entrambi guardano il codice sorgente o un processo PHP già avviato da
 * PHPUnit, mai il runtime Artisan realmente eseguito in quella release.
 *
 * Questo comando non presume di conoscere la causa ambientale esatta.
 * Verifica il SINTOMO, nel modo in cui si è manifestato: invocato da
 * deploy.sh come `php artisan deploy:verify-scheduled-commands`, gira
 * già come un vero sottoprocesso Artisan nella release in corso di
 * verifica, dopo lo stesso ciclo di cache che precede un rilascio reale.
 *
 * Revisione Codex su PR #541: la prima versione di questo comando
 * leggeva routes/console.php come testo e cercava `Schedule::command(`
 * via regex — una riga commentata avrebbe comunque richiesto il
 * comando (falso positivo), mentre una chiamata non letterale
 * (`Schedule::command($variabile)` o via classe) sarebbe sfuggita
 * silenziosamente al controllo (falso negativo, il peggiore dei due:
 * un gate di rilascio che tace proprio quando dovrebbe bloccare).
 * Corretto interrogando lo SCHEDULER GIÀ COSTRUITO da questo stesso
 * processo — routes/console.php è comunque già stato eseguito da
 * withRouting(commands: ...) prima che questo comando giri, quindi
 * Schedule::events() riflette esattamente cosa è stato davvero
 * registrato, non cosa appare nel sorgente: una riga commentata non
 * produce mai un evento; una chiamata per classe o per variabile
 * produce lo stesso identico Event di una chiamata letterale, perché
 * Schedule::command() la risolve PRIMA di costruire l'evento (vedi
 * Schedule::command() in vendor/laravel/framework).
 */
class DeployVerifyScheduledCommands extends Command
{
    protected $signature = 'deploy:verify-scheduled-commands';

    protected $description = 'Verifica che ogni comando schedulato via Schedule::command(...) sia realmente registrato da Artisan in questo processo. Solo lettura, non modifica mai alcun file.';

    public function handle(Schedule $schedule): int
    {
        $prefix = ConsoleApplication::formatCommandString('');

        $scheduled = collect($schedule->events())
            ->reject(fn ($event) => $event instanceof CallbackEvent)
            ->map(function ($event) use ($prefix) {
                $command = (string) $event->command;

                $withoutPrefix = str_starts_with($command, $prefix)
                    ? substr($command, strlen($prefix))
                    : $command;

                return strtok(trim($withoutPrefix), ' ');
            })
            ->filter()
            ->unique()
            ->values();

        if ($scheduled->isEmpty()) {
            $this->error('The scheduler has no command-based events registered (Schedule::command(...)). Refusing to verify an empty scheduler contract.');

            return self::FAILURE;
        }

        $registered = array_keys(Artisan::all());

        $missing = $scheduled->reject(fn (string $name) => in_array($name, $registered, true))->values();

        if ($missing->isNotEmpty()) {
            $this->error(sprintf(
                'Scheduled command(s) not registered by Artisan in this release: %s. This is the exact newsletter:reconfirmation-cleanup incident class.',
                $missing->implode(', ')
            ));

            return self::FAILURE;
        }

        $this->info(sprintf('All %d scheduled commands are registered by Artisan in this release.', $scheduled->count()));

        return self::SUCCESS;
    }
}
