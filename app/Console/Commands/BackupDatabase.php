<?php
/**
 * Il Laboratorio — Backup automatico database
 *
 * @author    Andrea Bartiromo <redazione@illaboratorio.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature   = 'backup:database {--keep=7 : Numero di backup da conservare}';
    protected $description = 'Crea un backup del database SQLite e rimuove i backup più vecchi';

    public function handle(): int
    {
        $source  = database_path('database.sqlite');
        $backDir = storage_path('backups');

        if (!file_exists($source)) {
            $this->error('Database non trovato: ' . $source);
            return Command::FAILURE;
        }

        if (!is_dir($backDir)) {
            mkdir($backDir, 0755, true);
        }

        // Nome file con timestamp
        $filename = 'database-' . now()->format('Y-m-d-His') . '.sqlite';
        $dest     = $backDir . '/' . $filename;

        // Copia il database
        if (!copy($source, $dest)) {
            $this->error('Errore durante il backup.');
            return Command::FAILURE;
        }

        $size = number_format(filesize($dest) / 1024, 1);
        $this->info("✅ Backup creato: {$filename} ({$size} KB)");

        // Rimozione backup vecchi — conserva gli ultimi N
        $keep  = (int) $this->option('keep');
        $files = glob($backDir . '/database-*.sqlite');

        if ($files && count($files) > $keep) {
            usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
            $toDelete = array_slice($files, 0, count($files) - $keep);
            foreach ($toDelete as $old) {
                unlink($old);
                $this->line('🗑  Rimosso vecchio backup: ' . basename($old));
            }
        }

        $remaining = count(glob($backDir . '/database-*.sqlite'));
        $this->info("📦 Backup conservati: {$remaining}/{$keep}");

        return Command::SUCCESS;
    }
}
