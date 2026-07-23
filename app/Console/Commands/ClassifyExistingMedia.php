<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaClassificationPlan;
use App\Services\MediaClassificationResult;
use App\Services\MediaClassificationService;
use App\Services\MediaMoveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassifyExistingMedia extends Command
{
    protected $signature = 'media:classify-existing
        {--apply : Applica gli spostamenti pianificati (default: sola analisi, nessuna modifica)}
        {--media-id=* : Limita l\'analisi a uno o piu ID Media specifici}
        {--limit= : Numero massimo di Media da analizzare}
        {--chunk=200 : Dimensione dei chunk usati per leggere i Media dal database}
        {--report= : Percorso del file JSON su cui scrivere il piano/esito (es. storage/app/reports/media-classification.json)}
        {--resume= : Percorso di un report JSON precedente: i Media gia spostati con successo vengono saltati}
        {--force : Salta la conferma interattiva prima di applicare gli spostamenti (non aggira mai i controlli di sicurezza)}';

    protected $description = 'Analizza i Media esistenti e classifica/sposta automaticamente solo quelli riconducibili in modo inequivocabile a un dominio di utilizzo reale';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        Riorganizza progressivamente i file della Libreria media gia esistenti
        nelle cartelle corrette (articoli, pubblicita, categorie, autori,
        pagine speciali), basandosi esclusivamente su riferimenti strutturati
        REALI presenti nel database (mai sul solo nome del file), riusando
        integralmente MediaReferenceService (preflight) e MediaMoveService
        (spostamento transazionale con compensazione) gia introdotti dalla
        Libreria media. Nessuna nuova logica di spostamento o di
        aggiornamento riferimenti viene introdotta qui.

        Modalita dry-run (default)
        ---------------------------
        Senza --apply il comando e in sola lettura: analizza i Media, produce
        un piano completo e lo stampa/esporta, senza scrivere nulla ne su
        database ne su filesystem ne su directory. Nessun timestamp o
        riferimento viene toccato.

        Modalita --apply
        -----------------
        Rigenera il piano (mai fidandosi ciecamente di un piano salvato in
        precedenza) e applica lo spostamento SOLO ai Media con stato
        "movable", uno alla volta, tramite MediaMoveService. Ogni Media e
        un'unita indipendente: un errore su un elemento viene registrato e
        non interrompe gli altri. Non viene mai usata una transazione
        globale su tutti i Media. La cartella di destinazione viene creata
        (se assente) solo per un Media davvero da spostare, tramite
        MediaFolderService::upsertDefinition() (idempotente). Una seconda
        esecuzione e sempre sicura: i Media gia spostati risultano "noop".

        Opzioni
        -------
        --apply             Applica gli spostamenti (default: analisi sola lettura)
        --media-id=         Limita l'analisi a uno o piu ID (ripetibile)
        --limit=            Numero massimo di Media analizzati in questa esecuzione
        --chunk=            Dimensione dei chunk di lettura dal DB (default 200)
        --report=           Percorso file JSON con il piano/esito completo
        --resume=           Report JSON precedente: salta i Media gia "moved"
        --force             Salta la conferma interattiva (MAI i controlli di sicurezza:
                            riferimenti bloccanti, collisioni, traversal, symlink)

        Struttura del report JSON
        --------------------------
        generated_at, plan_hash, summary (conteggi per stato), results
        (uno per Media: media_id, current_disk_name, current_folder,
        proposed_folder, proposed_disk_name, domain, status, reason,
        references_found, updatable_count, blocking_count, blocking_details,
        confidence, outcome, error_message), unregistered_files (file su
        disco senza record Media corrispondente, solo a scopo informativo).

        Categorie di destinazione
        --------------------------
        article -> articles/covers, ad -> ads, category -> categories,
        user -> authors, special_page -> slug della pagina speciale se
        esiste gia come cartella (es. turing), altrimenti special-pages.
        Definite in config/media.php (classification_folders).

        Regole di classificazione (deterministiche, conservative)
        -----------------------------------------------------------
        - Un solo dominio di riferimento -> movable (o noop se gia a posto).
        - Nessun riferimento -> unclassified, MAI spostato automaticamente.
        - Piu domini incompatibili -> ambiguous, nessuno spostamento.
        - Qualunque riferimento bloccante (testo libero, riferimento statico,
          User.photo in formato ambiguo, chiave JSON non censita, contratto
          Category.image non rispettabile) -> blocked, ha sempre priorita.
        - Sorgente assente sul filesystem -> missing_source.
        - Collisione DB o filesystem sulla destinazione -> collision.
        In caso di dubbio la scelta e sempre "nessuno spostamento".

        Limiti
        ------
        Nessuna classificazione basata sul contenuto visivo o su IA. Nessuna
        rinomina automatica (il basename e sempre preservato). Nessuna
        duplicazione o eliminazione automatica di media. I Media
        "unclassified" e i file non registrati vengono solo riportati, mai
        spostati o eliminati.

        Exit code
        ---------
        0: analisi/applicazione completata senza errori tecnici (media
           bloccati/ambigui/non classificati NON sono errori tecnici, sono
           riportati nel summary). Diverso da 0: almeno un errore tecnico
           (eccezione durante il preflight o lo spostamento).

        Procedura operativa consigliata
        ---------------------------------
        1. Backup del database.
        2. Backup di storage/app/public e public/assets/img.
        3. Esecuzione in dry-run (senza --apply).
        4. Revisione umana del report (--report=...).
        5. Esecuzione in staging con --apply.
        6. Verifica manuale di frontend e pannello Admin.
        7. Esecuzione in produzione con --apply.
        8. Nuovo dry-run di verifica per confermare lo stato finale.
        HELP;

    public function handle(MediaClassificationService $classificationService, MediaMoveService $moveService): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $chunkSize = max(1, (int) $this->option('chunk'));
        $skipIds = $this->resumeSkipIds();

        $query = $this->buildQuery($skipIds);

        $this->info($apply ? 'Modalita: APPLICA spostamenti' : 'Modalita: sola analisi (dry-run)');
        $this->newLine();

        $results = [];
        $processed = 0;
        $technicalErrors = 0;

        $query->orderBy('id')->chunkById($chunkSize, function ($mediaChunk) use (
            &$results, &$processed, &$technicalErrors, $classificationService, $limit
        ) {
            foreach ($mediaChunk as $media) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $result = $classificationService->planFor($media);
                $results[] = $result;
                $processed++;

                if ($result->status === 'error') {
                    $technicalErrors++;
                }

                Log::info('media:classify-existing: media pianificato', [
                    'media_id' => $result->mediaId,
                    'status' => $result->status,
                    'domain' => $result->domain,
                    'proposed_disk_name' => $result->proposedDiskName,
                ]);

                if ($limit !== null && $processed >= $limit) {
                    return false;
                }
            }

            return true;
        }, 'id');

        $unregistered = $classificationService->findUnregisteredFiles();
        $plan = new MediaClassificationPlan(
            results: $results,
            generatedAt: now()->toIso8601String(),
            planHash: MediaClassificationPlan::hashFor($results),
            unregisteredFiles: $unregistered,
        );

        $this->printSummary($plan);

        if ($reportPath = $this->option('report')) {
            $this->writeReport($reportPath, $plan);
        }

        if (! $apply) {
            $this->newLine();
            $this->info('Dry-run completata: nessuna modifica applicata. Usa --apply per spostare i media classificati come "movable".');

            return $technicalErrors > 0 ? self::FAILURE : self::SUCCESS;
        }

        $movable = $plan->movable();

        if ($movable === []) {
            $this->newLine();
            $this->info('Nessun media "movable" da spostare.');

            return $technicalErrors > 0 ? self::FAILURE : self::SUCCESS;
        }

        if (! $this->option('force') && $this->input->isInteractive()) {
            if (! $this->confirm('Procedere con lo spostamento di '.count($movable).' media?', false)) {
                $this->warn('Applicazione annullata dall\'utente. Nessuno spostamento eseguito.');

                return self::SUCCESS;
            }
        }

        $outcomes = $this->applyMovable($movable, $classificationService, $moveService, $technicalErrors, $reportPath, $results, $unregistered);

        $this->newLine();
        $this->info('Spostati con successo: '.$outcomes['moved']);
        $this->info('Gia a posto (noop durante l\'applicazione): '.$outcomes['noop']);
        $this->info('Non applicati (bloccati o falliti durante l\'applicazione): '.$outcomes['failed']);

        return $technicalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function resumeSkipIds(): array
    {
        $resumePath = $this->option('resume');
        if (! $resumePath) {
            return [];
        }

        $fullPath = $this->resolveReportPath($resumePath);
        if (! is_file($fullPath)) {
            $this->warn('File di ripresa non trovato, ignorato: '.$resumePath);

            return [];
        }

        $data = json_decode((string) file_get_contents($fullPath), true);
        if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results'])) {
            $this->warn('File di ripresa non valido, ignorato: '.$resumePath);

            return [];
        }

        return collect($data['results'])
            ->where('outcome', 'moved')
            ->pluck('media_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function buildQuery(array $skipIds)
    {
        $query = Media::query();

        $mediaIds = array_map('intval', (array) $this->option('media-id'));
        if ($mediaIds !== []) {
            $query->whereIn('id', $mediaIds);
        }

        if ($skipIds !== []) {
            $query->whereNotIn('id', $skipIds);
        }

        return $query;
    }

    private function printSummary(MediaClassificationPlan $plan): void
    {
        $summary = $plan->summary();

        $this->table(['Stato', 'Conteggio'], [
            ['Media analizzati', $summary['analyzed']],
            ['Gia corretti (noop)', $summary['noop']],
            ['Spostabili (movable)', $summary['movable']],
            ['Bloccati', $summary['blocked']],
            ['Ambigui', $summary['ambiguous']],
            ['Non classificati', $summary['unclassified']],
            ['Sorgenti mancanti', $summary['missing_source']],
            ['Collisioni', $summary['collision']],
            ['Errori tecnici', $summary['error']],
        ]);

        $this->line('File non registrati trovati su disco: '.count($plan->unregisteredFiles));
    }

    /**
     * @param  list<MediaClassificationResult>  $movable
     * @param  list<MediaClassificationResult>  $allResults
     * @param  array<int, array<string, mixed>>  $unregistered
     * @return array{moved: int, noop: int, failed: int}
     */
    private function applyMovable(
        array $movable,
        MediaClassificationService $classificationService,
        MediaMoveService $moveService,
        int &$technicalErrors,
        ?string $reportPath,
        array &$allResults,
        array $unregistered,
    ): array {
        $moved = 0;
        $noop = 0;
        $failed = 0;

        $outcomesById = [];

        foreach ($movable as $result) {
            try {
                $media = Media::find($result->mediaId);

                if (! $media) {
                    $outcomesById[$result->mediaId] = ['outcome' => 'error', 'error' => 'Media non piu presente nel database.'];
                    $failed++;
                    $technicalErrors++;

                    continue;
                }

                $folder = $classificationService->ensureTargetFolder($result);
                $moveResult = $moveService->move($media->id, $folder?->id);

                if ($moveResult->isMoved()) {
                    $outcomesById[$result->mediaId] = ['outcome' => 'moved'];
                    $moved++;
                    Log::info('media:classify-existing: spostamento applicato', [
                        'media_id' => $result->mediaId,
                        'old_disk_name' => $moveResult->oldDiskName,
                        'new_disk_name' => $moveResult->newDiskName,
                    ]);
                } elseif ($moveResult->isNoop()) {
                    $outcomesById[$result->mediaId] = ['outcome' => 'noop'];
                    $noop++;
                } else {
                    $outcomesById[$result->mediaId] = ['outcome' => 'blocked', 'error' => $moveResult->message];
                    $failed++;
                    Log::warning('media:classify-existing: spostamento bloccato in fase di applicazione', [
                        'media_id' => $result->mediaId,
                    ]);
                }
            } catch (Throwable $exception) {
                $outcomesById[$result->mediaId] = ['outcome' => 'error', 'error' => $exception->getMessage()];
                $failed++;
                $technicalErrors++;
                Log::error('media:classify-existing: errore durante lo spostamento', [
                    'media_id' => $result->mediaId,
                    'error' => $exception->getMessage(),
                ]);
            }

            if ($reportPath) {
                $allResults = array_map(
                    fn (MediaClassificationResult $r) => isset($outcomesById[$r->mediaId])
                        ? $r->withOutcome($outcomesById[$r->mediaId]['outcome'], $outcomesById[$r->mediaId]['error'] ?? null)
                        : $r,
                    $allResults
                );

                $this->writeReport($reportPath, new MediaClassificationPlan(
                    results: $allResults,
                    generatedAt: now()->toIso8601String(),
                    planHash: MediaClassificationPlan::hashFor($allResults),
                    unregisteredFiles: $unregistered,
                ));
            }
        }

        return ['moved' => $moved, 'noop' => $noop, 'failed' => $failed];
    }

    private function writeReport(string $path, MediaClassificationPlan $plan): void
    {
        $fullPath = $this->resolveReportPath($path);
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($fullPath, json_encode($plan->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function resolveReportPath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
