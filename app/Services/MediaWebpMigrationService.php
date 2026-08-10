<?php

namespace App\Services;

use App\Models\Media;
use App\Services\Concerns\AppliesMediaReferenceUpdates;
use App\Services\Concerns\ResolvesMediaFileSafely;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * FASE 6 della missione WebP: converte in WebP un singolo file editoriale
 * gia' esistente e registrato come Media. Il comando media:convert-webp
 * decide quali Media passare, uno alla volta — un errore su un file non
 * blocca gli altri, non esiste una transazione unica su tutta la libreria.
 *
 * Riusa esplicitamente, mai reinventa:
 * - MediaWebpAuditService::evaluateCandidate() per la definizione di
 *   "candidato sicuro" — la stessa usata dalla scansione bulk di audit(),
 *   cosi' che audit e migrazione siano sempre d'accordo su cosa e' safe.
 * - MediaWebpAuditService::measureActualWebpSize() per la stima dry-run.
 * - ImageService::convertToWebp() per la scrittura atomica del WebP (temp
 *   file nella stessa directory + validazione + rename, non tocca mai il
 *   sorgente).
 * - AppliesMediaReferenceUpdates / ResolvesMediaFileSafely, le stesse
 *   concern gia' estratte da MediaMoveService.
 * - PublicMediaSyncService::create()/delete()/cleanupAfterFailedCreate()
 *   per la radice pubblica secondaria — nessuna logica di sincronizzazione
 *   duplicata qui.
 *
 * Differenza deliberata rispetto a MediaMoveService::move(): il file
 * originale non viene MAI rinominato ne' eliminato da questo servizio. Si
 * crea solo un nuovo file WebP accanto all'originale e si riscrivono i
 * riferimenti per puntare al nuovo file. La rimozione dell'originale e'
 * una fase futura, deliberatamente distinta e mai automatica qui (vedi
 * docs/EDITORIAL_MEDIA_WEBP.md — FASE 12 della missione).
 */
class MediaWebpMigrationService
{
    use AppliesMediaReferenceUpdates;
    use ResolvesMediaFileSafely;

    public function __construct(
        private readonly MediaWebpAuditService $auditService,
        private readonly ImageService $imageService,
        private readonly PublicMediaSyncService $publicMediaSync,
    ) {}

    /**
     * Sola lettura: rivaluta il Media con le stesse regole della scansione
     * bulk di audit(), e se eligible misura il peso WebP REALE (conversione
     * usa-e-getta in una directory temporanea, mai in public/assets/img,
     * eliminata subito dopo la misura). Nessuna scrittura, mai.
     */
    public function plan(Media $media): MediaWebpMigrationResult
    {
        $evaluation = $this->evaluate($media);

        if ($evaluation['outcome'] !== 'eligible') {
            return $this->resultForNonEligible($media, $evaluation);
        }

        $webpBytes = $this->auditService->measureActualWebpSize($evaluation['absolute_path']);

        return MediaWebpMigrationResult::planned(
            $media,
            $evaluation['webp_disk_name'],
            $evaluation['size_bytes'],
            $webpBytes,
            $this->safeDimensions($evaluation['absolute_path']),
            count($evaluation['preflight']['updatable_references']),
        );
    }

    /**
     * Converte davvero UN Media. Rivaluta l'eleggibilita' da zero dentro la
     * transazione (mai fidandosi di un piano calcolato in precedenza: un
     * altro processo potrebbe aver gia' convertito lo stesso file, o un
     * nuovo riferimento bloccante potrebbe essere comparso nel frattempo),
     * converte in un file WebP NUOVO (l'originale non viene mai toccato),
     * sincronizza la radice pubblica secondaria, aggiorna Media e tutti i
     * riferimenti strutturati aggiornabili.
     *
     * Ordine deliberato delle operazioni (DB e filesystem non condividono
     * una vera transazione, vedi docs/EDITORIAL_MEDIA_WEBP.md): il WebP
     * viene scritto e verificato PRIMA di qualunque scrittura sul database,
     * cosi' un fallimento di conversione non lascia mai uno stato DB
     * incoerente da compensare. Se invece un passo fallisce DOPO che il
     * WebP esiste gia' (sync pubblico, update Media, aggiornamento
     * riferimenti, verifica finale), l'intera transazione DB va in
     * rollback e il file WebP generato (con l'eventuale copia nella radice
     * pubblica secondaria) viene rimosso con best-effort prima di
     * restituire un risultato "failed": l'originale resta sempre l'unico
     * stato coerente sul filesystem, mai un WebP orfano che il DB non
     * referenzia.
     */
    public function apply(int $mediaId): MediaWebpMigrationResult
    {
        $webpAbsoluteToCleanup = null;
        $webpDiskNameToCleanup = null;
        $secondaryCopyCreatedByThisRun = false;

        try {
            return DB::transaction(function () use ($mediaId, &$webpAbsoluteToCleanup, &$webpDiskNameToCleanup, &$secondaryCopyCreatedByThisRun) {
                $media = Media::whereKey($mediaId)->lockForUpdate()->firstOrFail();
                $evaluation = $this->evaluate($media);

                if ($evaluation['outcome'] !== 'eligible') {
                    return $this->resultForNonEligible($media, $evaluation);
                }

                $originalDiskName = $media->disk_name;
                $originalAbsolute = $evaluation['absolute_path'];
                $originalBytes = $evaluation['size_bytes'];
                $webpDiskName = $evaluation['webp_disk_name'];
                $webpAbsolute = public_path('assets/img/'.$webpDiskName);
                $preflight = $evaluation['preflight'];

                $webpDirectory = dirname($webpAbsolute);
                if (! is_dir($webpDirectory) && ! @mkdir($webpDirectory, 0775, true) && ! is_dir($webpDirectory)) {
                    throw new RuntimeException('Impossibile creare la directory di destinazione.');
                }

                // Controllato PRIMA di create(): se la radice pubblica
                // secondaria ha gia' un file a questo disk_name, non e'
                // stato creato da questa esecuzione (evaluate() non
                // controlla quella radice, solo quella primaria e il DB —
                // vedi PublicMediaSyncService::targetIsFile()) e in caso di
                // rollback non deve mai essere rimosso: potrebbe essere un
                // file legittimo non correlato a questa conversione.
                $secondaryTargetPreexisted = $this->publicMediaSync->targetIsFile($webpDiskName);

                // Scrittura atomica (temp file + validazione + rename): non
                // tocca mai $originalAbsolute. Se fallisce, non e' stato
                // creato nulla da ripulire.
                $this->imageService->convertToWebp(
                    $originalAbsolute,
                    $webpAbsolute,
                    (int) config('media.webp_quality', 82),
                    (int) config('media.webp_max_width', 1600)
                );

                // Da qui in poi il file WebP esiste: qualunque fallimento
                // successivo deve ripulirlo (vedi catch esterno).
                $webpAbsoluteToCleanup = $webpAbsolute;
                $webpDiskNameToCleanup = $webpDiskName;
                $secondaryCopyCreatedByThisRun = ! $secondaryTargetPreexisted;

                $this->publicMediaSync->create($webpAbsolute, $webpDiskName);

                $webpBytes = @filesize($webpAbsolute);
                if ($webpBytes === false) {
                    throw new RuntimeException('Impossibile leggere la dimensione del file WebP appena creato.');
                }

                $dimensions = $this->safeDimensions($webpAbsolute);

                $media->update([
                    'disk_name' => $webpDiskName,
                    'mime_type' => 'image/webp',
                    'size' => $webpBytes,
                ]);

                $this->applyReferenceUpdates($preflight['updatable_references']);

                clearstatcache(true, $webpAbsolute);
                clearstatcache(true, $originalAbsolute);
                if (! is_file($webpAbsolute)
                    || ! is_file($originalAbsolute)
                    || Media::whereKey($media->id)->value('disk_name') !== $webpDiskName
                ) {
                    throw new RuntimeException('Verifica post-conversione fallita.');
                }

                Log::info('MediaWebpMigrationService: conversione applicata.', [
                    'media_id' => $media->id,
                    'old_disk_name' => $originalDiskName,
                    'new_disk_name' => $webpDiskName,
                    'original_bytes' => $originalBytes,
                    'webp_bytes' => $webpBytes,
                    'updated_references' => count($preflight['updatable_references']),
                ]);

                return MediaWebpMigrationResult::converted(
                    $media,
                    $originalDiskName,
                    $webpDiskName,
                    $originalBytes,
                    $webpBytes,
                    $dimensions,
                    count($preflight['updatable_references']),
                );
            });
        } catch (Throwable $exception) {
            if ($webpAbsoluteToCleanup !== null && $webpDiskNameToCleanup !== null) {
                $this->cleanupOrphanedWebp($webpAbsoluteToCleanup, $webpDiskNameToCleanup, $secondaryCopyCreatedByThisRun);
            }

            // La transazione e' gia' andata in rollback: disk_name torna
            // ad essere quello originale (o resta null se il Media non
            // esiste piu').
            $originalDiskName = Media::find($mediaId)?->disk_name;

            Log::error('MediaWebpMigrationService: conversione annullata dopo un errore, rollback eseguito.', [
                'media_id' => $mediaId,
                'error' => $exception->getMessage(),
            ]);

            return MediaWebpMigrationResult::failed($mediaId, $originalDiskName, $exception->getMessage());
        }
    }

    /**
     * Rimuove il file WebP generato ma reso orfano da un fallimento
     * successivo — best-effort, non deve mai mascherare l'errore originale
     * gia' catturato dal chiamante. Riusa gli stessi primitivi gia' usati
     * dai flussi di upload per lo stesso scopo
     * (PublicMediaSyncService::delete()/cleanupAfterFailedCreate()),
     * nessuna nuova logica di rimozione.
     *
     * La copia nella radice pubblica secondaria viene rimossa SOLO se
     * $createdByThisRun e' true: se un file era gia' presente li' prima di
     * questa esecuzione (mai creato da questa conversione — vedi il
     * controllo con PublicMediaSyncService::targetIsFile() PRIMA di
     * create() in apply()), rimuoverlo cancellerebbe dati che questo
     * servizio non ha mai scritto, indipendentemente dal motivo del
     * fallimento successivo. Stesso principio di
     * PublicMediaSyncService::move()/$newTargetPreexisted.
     */
    private function cleanupOrphanedWebp(string $webpAbsolute, string $webpDiskName, bool $createdByThisRun): void
    {
        if ($createdByThisRun) {
            try {
                $this->publicMediaSync->delete($webpDiskName);
            } catch (Throwable $exception) {
                Log::critical('MediaWebpMigrationService: impossibile ripulire la copia del WebP orfano nella radice pubblica secondaria.', [
                    'webp_disk_name' => $webpDiskName,
                    'error' => $exception->getMessage(),
                ]);
            }
        } else {
            Log::info('MediaWebpMigrationService: copia preesistente nella radice pubblica secondaria lasciata intatta durante il rollback (non creata da questa esecuzione).', [
                'webp_disk_name' => $webpDiskName,
            ]);
        }

        $this->publicMediaSync->cleanupAfterFailedCreate($webpAbsolute);
    }

    /**
     * @return array{outcome: 'eligible', absolute_path: string, size_bytes: int, webp_disk_name: string, preflight: array<string, mixed>}
     *                                                                                                                                     | array{outcome: 'missing_source', reason: string}
     *                                                                                                                                     | array{outcome: 'excluded', bucket: string, reason: string}
     */
    private function evaluate(Media $media): array
    {
        $relativePath = $media->disk_name;
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $root = public_path('assets/img');

        if ($extension === 'webp') {
            return ['outcome' => 'excluded', 'bucket' => 'already_webp', 'reason' => 'Il file e\' gia\' in formato WebP.'];
        }

        try {
            $absolutePath = $this->safeExistingFilePath($root, $relativePath);
        } catch (RuntimeException $exception) {
            return ['outcome' => 'missing_source', 'reason' => $exception->getMessage()];
        }

        $sizeBytes = @filesize($absolutePath);
        if ($sizeBytes === false) {
            return ['outcome' => 'missing_source', 'reason' => 'Impossibile leggere la dimensione del file sorgente.'];
        }

        $candidate = $this->auditService->evaluateCandidate(
            $media,
            $relativePath,
            $extension,
            fn (string $webpDiskName) => file_exists($root.'/'.$webpDiskName)
                || Media::where('disk_name', $webpDiskName)->where('id', '!=', $media->id)->exists()
        );

        if ($candidate['status'] === 'excluded') {
            return ['outcome' => 'excluded', 'bucket' => $candidate['bucket'], 'reason' => $candidate['reason']];
        }

        return [
            'outcome' => 'eligible',
            'absolute_path' => $absolutePath,
            'size_bytes' => $sizeBytes,
            'webp_disk_name' => $candidate['webp_disk_name'],
            'preflight' => $candidate['preflight'],
        ];
    }

    /**
     * @param  array{outcome: 'missing_source', reason: string}|array{outcome: 'excluded', bucket: string, reason: string}  $evaluation
     */
    private function resultForNonEligible(Media $media, array $evaluation): MediaWebpMigrationResult
    {
        return match ($evaluation['outcome']) {
            'missing_source' => MediaWebpMigrationResult::missingSource($media->id, $media->disk_name, $evaluation['reason']),
            'excluded' => MediaWebpMigrationResult::skipped($media->id, $media->disk_name, $evaluation['bucket'], $evaluation['reason']),
        };
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private function safeDimensions(string $absolutePath): ?array
    {
        $info = @getimagesize($absolutePath);

        if ($info === false) {
            return null;
        }

        return ['width' => $info[0], 'height' => $info[1]];
    }
}
