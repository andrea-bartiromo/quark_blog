<?php

namespace App\Console\Commands;

use App\Services\Deploy\PersistentStoragePreflight;
use Illuminate\Console\Command;

/**
 * Cantiere 18 (programma 100-cantieri Kairus). Preflight di sola lettura:
 * segnala i percorsi pensati per sopravvivere tra un rilascio e l'altro
 * (backup MariaDB, registro rilasci) che risolvono invece dentro QUESTA
 * directory di release — con lo schema a directory separate + switch di
 * symlink già in uso in produzione, verrebbero perduti al deploy
 * successivo. Solo informativo: la decisione se trattarlo come un vero
 * gate (fail-closed) resta di chi lo invoca — vedi deploy.sh, che lo
 * esegue senza bloccare il rilascio.
 */
class DeployVerifyPersistentStorage extends Command
{
    protected $signature = 'deploy:verify-persistent-storage';

    protected $description = 'Segnala i percorsi che devono sopravvivere tra un rilascio e l\'altro (backup, registro rilasci) ma risolvono dentro questa directory di release. Solo lettura, non modifica mai alcun file.';

    public function handle(PersistentStoragePreflight $preflight): int
    {
        $report = $preflight->report();

        if ($report['ok']) {
            $this->info('Nessun percorso a rischio: ogni percorso controllato che deve sopravvivere tra un rilascio e l\'altro è configurato fuori da questa directory di release.');

            return self::SUCCESS;
        }

        $this->warn("Uno o più percorsi che devono sopravvivere tra un rilascio e l'altro puntano dentro QUESTA directory di release ({$report['release_root']}) — con lo schema a directory separate + switch di symlink, verranno perduti al deploy successivo:");

        foreach ($report['at_risk'] as $item) {
            $this->line("  - {$item['label']}: {$item['path']}");
            $this->line("    Imposta {$item['env_var']} nel .env di produzione su un percorso fuori da questa directory di release.");
        }

        return self::FAILURE;
    }
}
