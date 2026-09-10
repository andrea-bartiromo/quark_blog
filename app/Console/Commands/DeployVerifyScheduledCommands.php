<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
 * Da lì confronta ogni nome comando schedulato in routes/console.php con
 * Artisan::all() — l'elenco realmente registrato in QUESTO processo, non
 * un elenco letto da un altro processo già avviato in precedenza.
 */
class DeployVerifyScheduledCommands extends Command
{
    protected $signature = 'deploy:verify-scheduled-commands';

    protected $description = 'Verifica che ogni comando schedulato in routes/console.php sia realmente registrato da Artisan in questo processo. Solo lettura, non modifica mai alcun file.';

    public function handle(): int
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        preg_match_all("/Schedule::command\(\s*['\"]([^'\"]+)['\"]/", $schedule, $matches);

        if (empty($matches[1])) {
            $this->error('routes/console.php has no Schedule::command(...) lines. Refusing to verify an empty scheduler contract.');

            return self::FAILURE;
        }

        $scheduled = collect($matches[1])
            ->map(fn (string $raw) => strtok($raw, ' '))
            ->unique()
            ->values();

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
