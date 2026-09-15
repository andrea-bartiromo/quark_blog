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
 *
 * Cantiere 72 (programma "100 cantieri Kairus"): segnala anche, quando
 * `DB_BACKUP_RETENTION` è configurato, se il numero di backup validi su
 * disco (per mode) supera quel limite — un fallimento di pulizia della
 * retention è "mai bloccante" per il backup stesso, ma senza questo
 * segnale resterebbe invisibile a un operatore.
 *
 * Cantiere 74 (programma "100 cantieri Kairus"): segnala anche, quando
 * `DB_BACKUP_OFFHOST_DISK` è configurato (Cantiere 71), se la copia
 * off-host del backup più recente risulta assente o irraggiungibile —
 * stesso principio "mai bloccante per il backup locale, ma visibile
 * qui" già applicato a retention e staleness.
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
            $this->warn("Nessun backup MariaDB valido trovato in {$report['directory']} per l'identità database corrente.");
            $this->line("Eseguire 'php artisan backup:database-v2' manualmente prima di procedere (vedi docs/DEPLOYMENT.md).");

            return self::FAILURE;
        }

        if ($report['max_age_invalid']) {
            $this->warn('DB_BACKUP_MAX_AGE_HOURS è configurato ma non è un intero positivo valido: il controllo di staleness non può essere applicato.');
            $this->line('Correggere DB_BACKUP_MAX_AGE_HOURS nel .env di produzione (vedi .env.production.example).');

            return self::FAILURE;
        }

        $ageHours = round($report['latest']['age_hours'], 1);

        if ($report['stale']) {
            $this->warn("Il backup MariaDB più recente ({$report['latest']['path']}) ha {$ageHours} ore, oltre la soglia configurata di {$report['max_age_hours']} ore (DB_BACKUP_MAX_AGE_HOURS).");
            $this->line("Eseguire 'php artisan backup:database-v2' manualmente per aggiornarlo.");

            return self::FAILURE;
        }

        // Cantiere 72 (programma "100 cantieri Kairus"): stesso principio
        // "solo informativo" del controllo di staleness sopra — un
        // fallimento di retention non blocca mai il deploy, ma renderlo
        // visibile qui è l'unico modo per accorgersene senza leggere i
        // log di ogni singola esecuzione di 'backup:database-v2'.
        if ($report['retention_invalid']) {
            $this->warn('DB_BACKUP_RETENTION è configurato ma non è un intero positivo valido: il controllo di retention non può essere applicato.');
            $this->line('Correggere DB_BACKUP_RETENTION nel .env di produzione (vedi .env.production.example).');

            return self::FAILURE;
        }

        if ($report['retention_exceeded_modes'] !== []) {
            foreach ($report['retention_exceeded_modes'] as $mode) {
                $count = $report['pair_counts_by_mode'][$mode];
                $this->warn("Ci sono {$count} backup MariaDB validi in modalità '{$mode}', oltre il limite di retention configurato ({$report['retention_configured']}, DB_BACKUP_RETENTION).");
            }
            $this->line('La pulizia automatica della retention potrebbe fallire ripetutamente (permessi, spazio disco): vedi docs/BACKUP_V2_OPERATIONS.md, "Failure semantics".');

            return self::FAILURE;
        }

        // Cantiere 74 (programma "100 cantieri Kairus"): stesso principio
        // "solo informativo" sopra — un fallimento di caricamento off-host
        // non invalida mai il backup locale, ma senza questo segnale
        // resterebbe invisibile fino al momento in cui servirebbe di più.
        if ($report['offhost_checked'] && ($report['offhost_missing'] || $report['offhost_error'])) {
            $reason = $report['offhost_error']
                ? "il disco '{$report['offhost_disk']}' non è raggiungibile o non è configurato correttamente"
                : "la copia off-host di {$report['latest']['path']} risulta assente sul disco '{$report['offhost_disk']}'";
            $this->warn("Verifica off-host fallita: {$reason}.");
            $this->line('Il backup locale resta valido; vedi docs/BACKUP_V2_OPERATIONS.md, "Optional off-host copy" per la configurazione attesa.');

            return self::FAILURE;
        }

        $this->info("Backup MariaDB valido trovato: {$report['latest']['path']} (età: {$ageHours} ore).");

        return self::SUCCESS;
    }
}
