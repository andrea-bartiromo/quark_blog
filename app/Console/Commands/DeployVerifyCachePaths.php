<?php

namespace App\Console\Commands;

use App\Services\Deploy\CachedConfigPathAudit;
use Illuminate\Console\Command;

/**
 * Prompt 8 (programma 100-prompt Kairus): gate di rilascio, invocato da
 * deploy.sh subito dopo `config:cache`/`route:cache`/`view:cache` —
 * verifica, come vero sottoprocesso Artisan in questa release, che la
 * cache appena scritta risolva i percorsi calcolati da storage_path()
 * (log, sessioni, cache su file, disco locale) sotto QUESTA directory,
 * non una diversa sopravvissuta da un'altra release. Vedi
 * App\Services\Deploy\CachedConfigPathAudit per il perché.
 */
class DeployVerifyCachePaths extends Command
{
    protected $signature = 'deploy:verify-cache-paths';

    protected $description = 'Verifica che la config cache di questa release risolva i percorsi storage_path() sotto la directory corrente, non una diversa. Solo lettura, non modifica mai alcun file.';

    public function handle(CachedConfigPathAudit $audit): int
    {
        $report = $audit->report();

        if ($report['ok']) {
            $this->info("Tutti i percorsi di config basati su storage_path() puntano correttamente sotto {$report['expected_prefix']}.");

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'La config cache di questa release contiene percorsi che non puntano sotto %s — probabile bootstrap/cache/config.php sopravvissuto da una directory di release diversa. Rigenerare la cache (php artisan optimize:clear && php artisan config:cache) prima di procedere.',
            $report['expected_prefix']
        ));

        $this->table(
            ['Chiave config', 'Valore in cache'],
            array_map(fn (array $problem) => [$problem['key'], $problem['value']], $report['problems'])
        );

        return self::FAILURE;
    }
}
