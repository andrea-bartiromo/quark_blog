<?php

/**
 * Kairus — Backup automatico database
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=7 : Numero di backup da conservare}';

    protected $description = 'Crea un backup del database SQLite e rimuove i backup più vecchi';

    public function handle(): int
    {
        $source = database_path('database.sqlite');
        $backDir = storage_path('backups');

        if (! is_file($source) || filesize($source) === 0) {
            $this->error('Database non trovato o vuoto: '.$source);

            return Command::FAILURE;
        }

        if (! is_dir($backDir) && ! mkdir($backDir, 0755, true) && ! is_dir($backDir)) {
            $this->error('Impossibile creare la directory di backup: '.$backDir);

            return Command::FAILURE;
        }

        $suffix = Str::lower(Str::random(8));
        $filename = 'database-'.now()->format('Y-m-d-His').'-'.$suffix.'.sqlite';
        $dest = $backDir.'/'.$filename;
        $temp = $backDir.'/.'.$filename.'.tmp';

        try {
            if (! copy($source, $temp) || ! is_file($temp) || filesize($temp) === 0) {
                $this->error('Errore durante il backup o backup vuoto.');

                return Command::FAILURE;
            }

            if (file_exists($dest) || ! rename($temp, $dest)) {
                $this->error('Impossibile finalizzare il backup.');

                return Command::FAILURE;
            }
        } finally {
            if (file_exists($temp)) {
                @unlink($temp);
            }
        }

        $size = number_format(filesize($dest) / 1024, 1);
        $this->info("✅ Backup creato: {$filename} ({$size} KB)");

        // Rimozione backup vecchi solo dopo che il nuovo backup è stato validato
        // e promosso sul filename finale.
        $keep = max(1, (int) $this->option('keep'));
        $files = glob($backDir.'/database-*.sqlite');

        if ($files && count($files) > $keep) {
            usort($files, fn ($a, $b) => filemtime($a) <=> filemtime($b));
            $toDelete = array_slice($files, 0, count($files) - $keep);
            foreach ($toDelete as $old) {
                if (@unlink($old)) {
                    $this->line('🗑  Rimosso vecchio backup: '.basename($old));
                }
            }
        }

        $remaining = count(glob($backDir.'/database-*.sqlite') ?: []);
        $this->info("📦 Backup conservati: {$remaining}/{$keep}");

        return Command::SUCCESS;
    }
}
