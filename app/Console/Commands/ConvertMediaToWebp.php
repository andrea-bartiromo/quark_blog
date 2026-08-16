<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\Concerns\DetectsAbsolutePaths;
use App\Services\MediaWebpMigrationResult;
use App\Services\MediaWebpMigrationService;
use Illuminate\Console\Command;

class ConvertMediaToWebp extends Command
{
    use DetectsAbsolutePaths;

    protected $signature = 'media:convert-webp
        {--execute : Applica davvero le conversioni (default: sola analisi/dry-run, nessuna modifica)}
        {--media-id=* : Limita l\'operazione a uno o piu ID Media specifici}
        {--limit= : Numero massimo di Media da processare in questa esecuzione}
        {--report= : Percorso del file JSON su cui scrivere il manifest completo}
        {--force : Salta la conferma interattiva prima di eseguire (non aggira mai i controlli di sicurezza)}';

    protected $description = 'Migra progressivamente i media editoriali JPG/PNG esistenti verso WebP, mantenendo sempre l\'originale (default: sola analisi)';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        FASE 6 della missione WebP (successiva a media:webp-audit e alla
        conversione automatica dei nuovi upload). Converte in WebP i file
        JPG/PNG GIA' esistenti e registrati come Media, riscrivendo i
        riferimenti strutturati conosciuti (copertina articolo, banner
        pubblicita, foto profilo, immagine categoria, contenuto pagine
        speciali). Riusa integralmente MediaWebpAuditService (stessa
        definizione di "candidato sicuro" usata da media:webp-audit) e
        ImageService::convertToWebp() (stessa scrittura atomica usata
        dalla conversione dei nuovi upload).

        Modalita dry-run (default)
        ---------------------------
        Senza --execute il comando e in sola lettura: rivaluta ogni Media
        candidato, misura realmente (in una directory temporanea usa-e-
        getta) il peso WebP risultante, e stampa/esporta un manifest
        completo. Nessuna scrittura ne su database ne su filesystem.

        Modalita --execute
        -------------------
        Rivaluta ogni Media (mai fidandosi ciecamente di un piano
        calcolato in precedenza) e applica la conversione SOLO ai Media
        risultati "planned" nella rivalutazione, uno alla volta, tramite
        MediaWebpMigrationService::apply(). Un errore su un Media viene
        registrato e non interrompe gli altri: non esiste una transazione
        unica su tutta la libreria. Una seconda esecuzione e sempre
        sicura: i Media gia convertiti hanno ormai disk_name .webp e non
        vengono piu selezionati dalla query (idempotente).

        L'ORIGINALE NON VIENE MAI ELIMINATO da questo comando. Si crea
        solo un nuovo file WebP e si aggiornano i riferimenti per puntare
        ad esso. La rimozione dei file originali e' una fase futura,
        deliberatamente distinta e mai automatica qui — vedi
        media:webp-cleanup e docs/EDITORIAL_MEDIA_WEBP.md.

        Opzioni
        -------
        --execute     Applica le conversioni (default: sola analisi)
        --media-id=   Limita a uno o piu ID Media (ripetibile)
        --limit=      Numero massimo di Media processati in questa esecuzione
        --report=     Percorso file JSON con il manifest completo
        --force       Salta la conferma interattiva (MAI i controlli di
                       sicurezza: riferimenti bloccanti, collisioni, file
                       protetti, Turing, GIF)

        Struttura del manifest JSON
        -----------------------------
        generated_at, mode (dry_run|executed), summary (conteggi per
        stato), results (uno per Media: status, media_id, original_path,
        new_path, original_bytes, webp_bytes, saving_bytes,
        saving_percent, dimensions, updated_reference_count, reason).

        Stati possibili per Media
        ---------------------------
        planned / converted -> candidato sicuro (o gia convertito con
          successo in modalita --execute).
        skipped_<motivo> -> escluso (gif, protected, turing_unmanaged,
          webp_destination_conflict, blocked_references, already_webp):
          stessa classificazione di media:webp-audit.
        missing_source -> il file non esiste piu sul filesystem (o e un
          symlink, o uscirebbe dalla radice media): richiede indagine
          manuale, mai trattato come "quindi convertibile".
        failed -> errore tecnico durante la conversione o l'applicazione
          (encoder assente, scrittura fallita, verifica post-conversione
          fallita): nessuna modifica applicata, rollback automatico.

        Procedura operativa consigliata
        ---------------------------------
        1. Backup del database.
        2. Backup di storage/app/public e public/assets/img.
        3. Esecuzione in dry-run (senza --execute), revisione del report
           (--report=...).
        4. Esecuzione in staging con --execute, verifica manuale di
           frontend e pannello Admin.
        5. Esecuzione in produzione con --execute su un lotto limitato
           (--limit=... o --media-id=...) prima dell'intera libreria.
        6. Nuovo dry-run di verifica per confermare lo stato finale.
        7. Periodo di osservazione prima di valutare la rimozione degli
           originali (vedi media:webp-cleanup, mai automatica).

        Exit code
        ---------
        0: esecuzione completata senza errori tecnici (media saltati o
           con sorgente mancante NON sono errori tecnici, sono riportati
           nel summary). Diverso da 0: almeno un errore tecnico
           (conversione o applicazione fallita).
        HELP;

    public function handle(MediaWebpMigrationService $migrationService): int
    {
        $execute = (bool) $this->option('execute');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $this->info($execute ? 'Modalita: ESEGUI conversioni' : 'Modalita: sola analisi (dry-run)');
        $this->line('(l\'originale non viene mai eliminato da questo comando)');
        $this->newLine();

        $mediaList = $this->buildQuery($limit)->get();

        if ($mediaList->isEmpty()) {
            $this->info('Nessun media JPG/PNG da valutare con i filtri indicati.');

            return self::SUCCESS;
        }

        $plans = [];
        foreach ($mediaList as $media) {
            $plans[$media->id] = $migrationService->plan($media);
        }

        $this->printSummary($plans, 'Anteprima (dry-run)');

        if (! $execute) {
            if ($reportPath = $this->option('report')) {
                $this->writeReport($reportPath, $plans, 'dry_run');
            }

            $this->newLine();
            $this->info('Dry-run completata: nessuna modifica applicata. Usa --execute per convertire i media "planned".');

            return self::SUCCESS;
        }

        $eligibleIds = array_keys(array_filter($plans, fn (MediaWebpMigrationResult $r) => $r->status === 'planned'));

        if ($eligibleIds === []) {
            $this->newLine();
            $this->info('Nessun media "planned" da convertire.');

            if ($reportPath = $this->option('report')) {
                $this->writeReport($reportPath, $plans, 'executed');
            }

            return self::SUCCESS;
        }

        if (! $this->option('force') && $this->input->isInteractive()) {
            if (! $this->confirm('Procedere con la conversione di '.count($eligibleIds).' media?', false)) {
                $this->warn('Esecuzione annullata dall\'utente. Nessuna conversione eseguita.');

                return self::SUCCESS;
            }
        }

        $results = $plans;
        $technicalErrors = 0;

        foreach ($eligibleIds as $mediaId) {
            $result = $migrationService->apply($mediaId);
            $results[$mediaId] = $result;

            if ($result->isFailed()) {
                $technicalErrors++;
            }

            if ($reportPath = $this->option('report')) {
                $this->writeReport($reportPath, $results, 'executed');
            }
        }

        $this->newLine();
        $this->printSummary($results, 'Esito');

        return $technicalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function buildQuery(?int $limit)
    {
        $query = Media::query()->where(function ($q) {
            $q->where('disk_name', 'like', '%.jpg')
                ->orWhere('disk_name', 'like', '%.jpeg')
                ->orWhere('disk_name', 'like', '%.png');
        });

        $mediaIds = array_map('intval', (array) $this->option('media-id'));
        if ($mediaIds !== []) {
            $query->whereIn('id', $mediaIds);
        }

        $query->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query;
    }

    /**
     * @param  array<int, MediaWebpMigrationResult>  $results
     */
    private function printSummary(array $results, string $title): void
    {
        $human = fn (?int $bytes) => $bytes === null ? '—' : Media::humanFileSize($bytes);

        $byStatus = [];
        foreach ($results as $result) {
            $byStatus[$result->status] ??= 0;
            $byStatus[$result->status]++;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>'.$title.'</>');
        ksort($byStatus);
        $rows = [];
        foreach ($byStatus as $status => $count) {
            $rows[] = [$status, $count];
        }
        $this->table(['Stato', 'Conteggio'], $rows);

        $convertedOrPlanned = array_filter($results, fn (MediaWebpMigrationResult $r) => $r->isConvertedOrPlanned());

        if ($convertedOrPlanned !== []) {
            $totalOriginal = array_sum(array_map(fn (MediaWebpMigrationResult $r) => $r->originalBytes ?? 0, $convertedOrPlanned));
            $measured = array_filter($convertedOrPlanned, fn (MediaWebpMigrationResult $r) => $r->webpBytes !== null);
            $totalWebp = array_sum(array_map(fn (MediaWebpMigrationResult $r) => $r->webpBytes, $measured));

            $this->newLine();
            $this->line('Peso attuale totale: '.$human($totalOriginal));

            if (count($measured) === count($convertedOrPlanned)) {
                $saving = $totalOriginal - $totalWebp;
                $percent = $totalOriginal > 0 ? round(($saving / $totalOriginal) * 100, 1) : 0.0;
                $this->line('Peso WebP: '.$human($totalWebp).' — risparmio: '.$human($saving).' ('.$percent.'%)');
            }

            $this->newLine();
            $this->line('Dettaglio:');
            foreach ($convertedOrPlanned as $result) {
                $savingLabel = $result->savingPercent !== null
                    ? sprintf(' → %s (%s%%)', $human($result->webpBytes), $result->savingPercent)
                    : ' → (non misurato)';
                $this->line('  #'.$result->mediaId.' '.$result->originalDiskName.' '.$human($result->originalBytes).$savingLabel);
            }
        }

        $failedOrMissing = array_filter($results, fn (MediaWebpMigrationResult $r) => in_array($r->status, ['failed', 'missing_source'], true));
        if ($failedOrMissing !== []) {
            $this->newLine();
            $this->line('<fg=red;options=bold>Richiedono attenzione:</>');
            foreach ($failedOrMissing as $result) {
                $this->line('  #'.$result->mediaId.' ['.$result->status.'] '.$result->originalDiskName.' — '.$result->reason);
            }
        }
    }

    /**
     * @param  array<int, MediaWebpMigrationResult>  $results
     */
    private function writeReport(string $path, array $results, string $mode): void
    {
        $fullPath = $this->isAbsolutePath($path) ? $path : base_path($path);
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $byStatus = [];
        foreach ($results as $result) {
            $byStatus[$result->status] ??= 0;
            $byStatus[$result->status]++;
        }

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'mode' => $mode,
            'summary' => $byStatus,
            'results' => array_values(array_map(fn (MediaWebpMigrationResult $r) => $r->toArray(), $results)),
        ];

        file_put_contents($fullPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
