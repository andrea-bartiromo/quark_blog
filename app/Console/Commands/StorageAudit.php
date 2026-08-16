<?php

/**
 * Kairus — Rivista italiana di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 *
 * @link      https://kairus.it
 */

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\StorageAuditService;
use Illuminate\Console\Command;

/**
 * Fotografa lo spazio disco occupato da Kairus (database, backup, media,
 * log, altro storage applicativo) e segnala possibili incoerenze tra il
 * registro Media e il filesystem.
 *
 * Esclusivamente in lettura: non scrive, sposta, converte o elimina mai
 * nulla, né sul database né sul filesystem. Sicuro da eseguire in
 * qualunque momento, anche in produzione.
 */
class StorageAudit extends Command
{
    protected $signature = 'storage:audit {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Misura lo spazio disco occupato da Kairus (sola lettura, non modifica né elimina nulla)';

    public function handle(StorageAuditService $service): int
    {
        $report = $service->audit();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $this->renderTextReport($report);

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderTextReport(array $report): void
    {
        $human = fn (int $bytes) => Media::humanFileSize($bytes);

        $this->newLine();
        $this->line('<fg=cyan;options=bold>STORAGE KAIRUS</>');
        $this->newLine();

        $this->line('Database: '.($report['database']['exists']
            ? $human($report['database']['size_bytes'])
            : 'non trovato'));

        $this->line(sprintf(
            'Backup: %s, %d file (moltiplicatore attuale: x%d)',
            $human($report['backup']['size_bytes']),
            $report['backup']['count'],
            $report['backup']['multiplier']
        ));

        $media = $report['media'];
        $this->line('Media: '.$human($media['total_size_bytes']).', '.$media['total_count'].' file');
        $this->line('Media registrati nel DB: '.$media['registered_in_db_count']);
        $this->line('Media su filesystem: '.$media['on_filesystem_count']);
        $this->line('Possibili orfani (file senza record Media): '.$media['orphan_count']);
        $this->line('Possibili record con file mancante: '.$media['missing_file_count']);

        $this->line('Log: '.$human($report['logs']['size_bytes']));
        $this->line('Altro storage: '.$human($report['other']['size_bytes']));

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Totale misurabile:</> '.$human($report['total_measurable_bytes']));

        $budget = $report['budget'];
        $this->line('Budget hosting: '.$human($budget['budget_bytes']).' (config: storage_audit.budget_bytes)');

        if ($budget['used_percent'] !== null) {
            $this->line('Percentuale stimata: '.$budget['used_percent'].'%');
            $this->line('Spazio residuo stimato: '.$human($budget['remaining_bytes']));
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Formati immagine</>');
        foreach ($media['format_breakdown'] as $format => $stats) {
            $this->line(sprintf('  %-6s %5d file — %s', strtoupper($format), $stats['count'], $human($stats['size_bytes'])));
        }

        $this->line('Dimensione media immagine: '.$human($media['average_image_size_bytes']));

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Top 10 immagini più pesanti</>');
        if ($media['top_heaviest_images'] === []) {
            $this->line('  (nessuna immagine trovata)');
        }
        foreach ($media['top_heaviest_images'] as $image) {
            $this->line('  '.$human($image['size_bytes']).' — '.$image['relative_path']);
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Top 10 directory più pesanti</>');
        if ($media['top_heaviest_directories'] === []) {
            $this->line('  (nessuna directory trovata)');
        }
        foreach ($media['top_heaviest_directories'] as $directory => $sizeBytes) {
            $this->line('  '.$human($sizeBytes).' — '.$directory);
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Radice pubblica secondaria (MEDIA_PUBLIC_ROOT)</>');
        $sync = $media['public_root_sync'];
        if (! $sync['configured']) {
            $this->line('  Non configurata (MEDIA_PUBLIC_ROOT non impostato): nessuna copia secondaria attesa.');
        } elseif ($sync['collapses_to_app_root']) {
            $this->line('  Configurata su '.$sync['configured_value'].', ma coincide fisicamente con public/assets/img: sincronizzazione disattivata, nessuna copia reale.');
        } elseif ($sync['present_in_both_count'] === null) {
            $this->line('  Configurata su '.$sync['configured_value'].', ma non raggiungibile o disattivata: impossibile verificare le copie.');
        } else {
            $this->line('  Configurata su '.$sync['configured_value'].'.');
            $this->line('  Presenti in entrambe le directory: '.$sync['present_in_both_count']);
            $this->line('  Presenti solo in public/assets/img: '.$sync['present_only_in_app_root_count']);
            $this->line('  (Nota: non viene scansionata la direzione opposta — file presenti solo nella radice pubblica secondaria — per evitare una scansione ricorsiva completa di una directory esterna al progetto, potenzialmente arbitraria.)');
        }

        $this->newLine();
    }
}
