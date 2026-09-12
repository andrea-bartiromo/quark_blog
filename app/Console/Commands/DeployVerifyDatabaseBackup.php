<?php

namespace App\Console\Commands;

use App\Services\Deploy\MariaDbBackupHealthAudit;
use Illuminate\Console\Command;

/**
 * Cantiere 19 (programma 100-cantieri Kairus). Verifica di sola lettura:
 * segnala se esiste almeno un backup MariaDB (Backup V2) valido e, quando
 * `DB_BACKUP_MAX_AGE_HOURS` è configurato, se il più recente è troppo
 * vecchio. Non crea, pianifica né elimina mai alcun backup — `backup:
 * database-v2` resta manuale/opt-in (vedi docs/DEPLOYMENT.md). Solo
 * informativo: deploy.sh lo esegue senza bloccare il rilascio.
 */
class DeployVerifyDatabaseBackup extends Command
{
    protected $signature = 'deploy:verify-database-backup';

    protected $description = 'Segnala se esiste un backup MariaDB (Backup V2) valido e non troppo vecchio. Solo lettura, non crea né elimina mai alcun backup.';

    public function handle(MariaDbBackupHealthAudit $audit): int
    {
        $report = $audit->report();

        if (! $report['applicable']) {
            $this->info('Verifica non applicabile: la connessione database corrente non è MariaDB/MySQL.');

            return self::SUCCESS;
        }

        if ($report['latest'] === null) {
            $this->warn("Nessun backup MariaDB valido trovato in {$report['directory']}.");
            $this->line("Eseguire 'php artisan backup:database-v2' manualmente prima di procedere (vedi docs/DEPLOYMENT.md).");

            return self::FAILURE;
        }

        $ageHours = round($report['latest']['age_hours'], 1);

        if ($report['stale']) {
            $this->warn("Il backup MariaDB più recente ({$report['latest']['path']}) ha {$ageHours} ore, oltre la soglia configurata di {$report['max_age_hours']} ore (DB_BACKUP_MAX_AGE_HOURS).");
            $this->line("Eseguire 'php artisan backup:database-v2' manualmente per aggiornarlo.");

            return self::FAILURE;
        }

        $this->info("Backup MariaDB valido trovato: {$report['latest']['path']} (età: {$ageHours} ore).");

        return self::SUCCESS;
    }
}
