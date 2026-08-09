<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaWebpAuditService;
use Illuminate\Console\Command;

/**
 * Fotografa cosa succederebbe se convertissimo le immagini editoriali di
 * Kairus in WebP — senza convertire nulla davvero.
 *
 * Esclusivamente in lettura: la conversione "reale" usata per stimare il
 * peso WebP di ogni candidato scrive solo in una directory temporanea
 * usa-e-getta (mai in public/assets/img), rimossa subito dopo la misura.
 * Nessun file di produzione viene creato, spostato o eliminato.
 */
class MediaWebpAudit extends Command
{
    protected $signature = 'media:webp-audit
        {--json : Restituisce il risultato in formato JSON invece del report testuale}
        {--path= : Limita la scansione a una sottodirectory di public/assets/img (es. categories)}
        {--only= : Limita ai soli formati indicati, separati da virgola (es. jpg,png)}
        {--min-size=0 : Ignora i file sotto questa soglia in byte}
        {--article= : Limita alla sola copertina dell\'articolo indicato (id o slug)}
        {--no-measure : Salta la conversione di misura reale (più veloce, nessuna stima del peso WebP)}
        {--verbose-files : Nel report testuale, elenca ogni singolo file escluso, non solo i conteggi}';

    protected $description = 'Analizza le immagini editoriali e mostra cosa succederebbe convertendole in WebP, senza convertire nulla (sola lettura)';

    public function handle(MediaWebpAuditService $service): int
    {
        $only = $this->option('only')
            ? array_map(fn (string $e) => strtolower(trim($e)) === 'jpeg' ? 'jpg' : strtolower(trim($e)), explode(',', $this->option('only')))
            : null;

        $report = $service->audit([
            'path' => $this->option('path'),
            'only' => $only,
            'minSize' => (int) $this->option('min-size'),
            'measureActual' => ! $this->option('no-measure'),
            'article' => $this->option('article'),
        ]);

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
        $this->line('<fg=cyan;options=bold>AUDIT WEBP — MEDIA EDITORIALI KAIRUS</>');
        $this->line('(sola lettura — nessun file di produzione viene modificato)');
        $this->newLine();

        $this->line('Immagini analizzate: '.$report['scanned_count'].' ('.$human($report['total_size_bytes']).')');

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Formati sorgente</>');
        foreach ($report['format_breakdown'] as $format => $stats) {
            $this->line(sprintf('  %-6s %5d file — %s', strtoupper($format), $stats['count'], $human($stats['size_bytes'])));
        }

        $this->newLine();
        $this->line('Già in WebP: '.$report['already_webp']['count'].' file ('.$human($report['already_webp']['size_bytes']).')');

        $candidates = $report['candidates'];
        $this->newLine();
        $this->line('<fg=green;options=bold>Candidati alla conversione: '.$candidates['count'].'</> ('.$human($candidates['current_size_bytes']).')');

        if ($candidates['estimated_webp_size_bytes'] !== null) {
            $this->line('  Peso WebP stimato (misurato realmente, non approssimato): '.$human($candidates['estimated_webp_size_bytes']));
            $this->line('  Risparmio potenziale: '.$human($candidates['saving_bytes']).' ('.$candidates['saving_percent'].'%)');
        } elseif ($candidates['count'] === 0) {
            $this->line('  (nessun candidato: nulla da misurare)');
        } else {
            $this->line('  (--no-measure: nessuna stima del peso WebP calcolata)');
        }

        if ($candidates['files'] !== []) {
            $this->newLine();
            $this->line('  Top 10 per dimensione attuale:');
            foreach (array_slice($candidates['files'], 0, 10) as $file) {
                $savingLabel = $file['saving_percent'] !== null
                    ? sprintf(' → %s (%s%%)', $human($file['estimated_webp_size_bytes']), $file['saving_percent'])
                    : '';
                $this->line('    '.$human($file['current_size_bytes']).$savingLabel.' — '.$file['relative_path']);
            }
        }

        $this->newLine();
        $this->line('<fg=yellow;options=bold>Escluse dalla conversione automatica</>');
        $labels = [
            'gif' => 'GIF (animazione)',
            'turing_unmanaged' => 'Turing (nessun record Media, non ancora dimostrato sicuro)',
            'protected' => 'Riferimenti statici protetti',
            'no_media_record' => 'Nessun record Media (richiede revisione manuale)',
            'blocked_references' => 'Riferimenti non aggiornabili in sicurezza',
        ];
        foreach ($report['excluded'] as $bucket => $data) {
            $this->line(sprintf('  %-45s %5d file — %s', $labels[$bucket] ?? $bucket, $data['count'], $human($data['size_bytes'])));

            if ($this->option('verbose-files')) {
                foreach ($data['files'] as $f) {
                    $this->line('      - '.$f['relative_path'].' — '.$f['reason']);
                }
            }
        }

        if ($report['missing_media_files'] !== []) {
            $this->newLine();
            $this->line('<fg=red;options=bold>Record Media con file mancante: '.count($report['missing_media_files']).'</> (non convertibili, file assente su disco)');
        }

        if ($report['safe_duplicates'] !== []) {
            $this->newLine();
            $this->line('<fg=yellow;options=bold>Duplicati sicuri rilevati (stesso contenuto, SHA-256): '.count($report['safe_duplicates']).' gruppo/i</>');
            foreach ($report['safe_duplicates'] as $group) {
                $this->line('    '.Media::humanFileSize($group['size_bytes']).' × '.count($group['paths']).': '.implode(', ', $group['paths']));
            }
        }

        $this->newLine();
    }
}
