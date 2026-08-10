<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Media;
use App\Services\Concerns\WalksMediaDirectorySafely;

/**
 * Audit di sola lettura: per ogni immagine editoriale sotto
 * public/assets/img, determina se convertirla in WebP sarebbe sicuro
 * oggi, e se si' quanto spazio si recupererebbe realmente — senza
 * scrivere, spostare o eliminare mai nulla in produzione.
 *
 * La sicurezza della conversione non e' una seconda definizione
 * inventata qui: ogni file registrato in Media viene valutato tramite
 * MediaReferenceService::preflight(), lo stesso servizio gia' usato da
 * media:classify-existing e dal preflight di spostamento della Libreria
 * media — "riferimento bloccante" significa esattamente la stessa cosa
 * ovunque nel progetto.
 */
class MediaWebpAuditService
{
    use WalksMediaDirectorySafely;

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function __construct(
        private readonly MediaReferenceService $referenceService,
        private readonly ImageService $imageService,
    ) {}

    /**
     * @param  array{path?: ?string, only?: ?list<string>, minSize?: int, measureActual?: bool, article?: ?string}  $options
     * @return array<string, mixed>
     */
    public function audit(array $options = []): array
    {
        $path = $options['path'] ?? null;
        $only = $options['only'] ?? null;
        $minSize = $options['minSize'] ?? 0;
        $measureActual = $options['measureActual'] ?? true;
        $articleFilter = $options['article'] ?? null;

        // Si scansiona sempre dalla radice: i percorsi relativi devono
        // restare root-relative (uguali a Media.disk_name) anche quando
        // --path restringe il report, altrimenti "categories/a.png"
        // diventerebbe "a.png" e ogni confronto con il DB fallirebbe.
        $baseDir = public_path('assets/img');
        $allFiles = $this->walkDirectory($baseDir);

        $normalizedPath = $path !== null ? trim($path, '/') : null;
        $scopedFiles = $normalizedPath === null
            ? $allFiles
            : array_values(array_filter(
                $allFiles,
                fn (array $f) => $f['relative_path'] === $normalizedPath
                    || str_starts_with($f['relative_path'], $normalizedPath.'/')
            ));

        $files = array_values(array_filter(
            $scopedFiles,
            fn (array $f) => in_array($f['extension'], self::IMAGE_EXTENSIONS, true)
                && $f['size_bytes'] >= $minSize
                && ($only === null || in_array($f['extension'], $only, true))
        ));

        $articleDiskName = null;
        if ($articleFilter !== null) {
            $articleDiskName = $this->resolveArticleCoverDiskName($articleFilter);
            $files = array_values(array_filter(
                $files,
                fn (array $f) => $f['relative_path'] === $articleDiskName
            ));
        }

        $existingRelativePaths = array_fill_keys(array_column($allFiles, 'relative_path'), true);
        $allDiskNames = Media::query()->pluck('disk_name')->all();
        $imageDiskNames = array_values(array_filter(
            $allDiskNames,
            fn (string $diskName) => in_array(strtolower(pathinfo($diskName, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true)
        ));
        $mediaByDiskName = Media::query()->get()->keyBy('disk_name');
        $scannedDiskNames = array_column($files, 'relative_path');

        $formatBreakdown = [];
        $alreadyWebp = ['count' => 0, 'size_bytes' => 0];
        $excluded = [
            'gif' => ['count' => 0, 'size_bytes' => 0, 'files' => []],
            'turing_unmanaged' => ['count' => 0, 'size_bytes' => 0, 'files' => []],
            'protected' => ['count' => 0, 'size_bytes' => 0, 'files' => []],
            'no_media_record' => ['count' => 0, 'size_bytes' => 0, 'files' => []],
            'webp_destination_conflict' => ['count' => 0, 'size_bytes' => 0, 'files' => []],
            'blocked_references' => ['count' => 0, 'size_bytes' => 0, 'files' => []],
        ];
        $candidates = [];

        foreach ($files as $file) {
            $ext = $file['extension'] === 'jpeg' ? 'jpg' : $file['extension'];
            $formatBreakdown[$ext] ??= ['count' => 0, 'size_bytes' => 0];
            $formatBreakdown[$ext]['count']++;
            $formatBreakdown[$ext]['size_bytes'] += $file['size_bytes'];

            if ($file['extension'] === 'webp') {
                $alreadyWebp['count']++;
                $alreadyWebp['size_bytes'] += $file['size_bytes'];

                continue;
            }

            $media = $mediaByDiskName->get($file['relative_path']);

            $evaluation = $this->evaluateCandidate(
                $media,
                $file['relative_path'],
                $file['extension'],
                fn (string $webpDiskName) => isset($existingRelativePaths[$webpDiskName]) || in_array($webpDiskName, $allDiskNames, true)
            );

            if ($evaluation['status'] === 'excluded') {
                $this->addExcluded($excluded, $evaluation['bucket'], $file, $evaluation['reason']);

                continue;
            }

            $preflight = $evaluation['preflight'];

            $candidate = [
                'relative_path' => $file['relative_path'],
                'current_size_bytes' => $file['size_bytes'],
                'media_id' => $media->id,
                'updatable_reference_count' => count($preflight['updatable_references']),
                'dimensions' => $this->safeDimensions($file['absolute_path']),
                'estimated_webp_size_bytes' => null,
                'saving_bytes' => null,
                'saving_percent' => null,
            ];

            if ($measureActual) {
                $measured = $this->measureActualWebpSize($file['absolute_path']);

                if ($measured !== null) {
                    $candidate['estimated_webp_size_bytes'] = $measured;
                    $candidate['saving_bytes'] = $file['size_bytes'] - $measured;
                    $candidate['saving_percent'] = $file['size_bytes'] > 0
                        ? round((($file['size_bytes'] - $measured) / $file['size_bytes']) * 100, 1)
                        : 0.0;
                }
            }

            $candidates[] = $candidate;
        }

        usort($candidates, fn (array $a, array $b) => $b['current_size_bytes'] <=> $a['current_size_bytes']);

        $missingMediaFiles = array_values(array_filter(
            $imageDiskNames,
            fn (string $diskName) => ! in_array($diskName, $scannedDiskNames, true)
                && ($path === null)
                && ($only === null)
                && $minSize === 0
        ));

        $duplicates = $this->findSafeDuplicates($files);

        // Il risparmio aggregato viene esposto solo se OGNI candidato e'
        // stato misurato realmente: sommare un sottoinsieme parziale (es.
        // una conversione fallita su un file corrotto) sotto lo stesso nome
        // di un totale darebbe un numero silenziosamente sbagliato.
        // 'measured_count' resta sempre disponibile per osservabilita'.
        $candidateTotalCurrent = array_sum(array_column($candidates, 'current_size_bytes'));
        $measuredCandidates = array_filter($candidates, fn (array $c) => $c['estimated_webp_size_bytes'] !== null);
        $allCandidatesMeasured = $candidates !== [] && count($measuredCandidates) === count($candidates);

        $candidateTotalEstimated = $allCandidatesMeasured
            ? array_sum(array_column($candidates, 'estimated_webp_size_bytes'))
            : null;
        $candidateSavingBytes = $allCandidatesMeasured ? ($candidateTotalCurrent - $candidateTotalEstimated) : null;
        $candidateSavingPercent = $allCandidatesMeasured && $candidateTotalCurrent > 0
            ? round((($candidateTotalCurrent - $candidateTotalEstimated) / $candidateTotalCurrent) * 100, 1)
            : null;

        return [
            'scanned_count' => count($files),
            'total_size_bytes' => array_sum(array_column($files, 'size_bytes')),
            'format_breakdown' => $formatBreakdown,
            'already_webp' => $alreadyWebp,
            'candidates' => [
                'count' => count($candidates),
                'measured_count' => count($measuredCandidates),
                'current_size_bytes' => $candidateTotalCurrent,
                'estimated_webp_size_bytes' => $candidateTotalEstimated,
                'saving_bytes' => $candidateSavingBytes,
                'saving_percent' => $candidateSavingPercent,
                'files' => $candidates,
            ],
            'excluded' => $excluded,
            'missing_media_files' => $missingMediaFiles,
            'safe_duplicates' => $duplicates,
        ];
    }

    /**
     * @param  array<string, array{count: int, size_bytes: int, files: list<array<string, mixed>>}>  $excluded
     * @param  array{relative_path: string, absolute_path: string, size_bytes: int, extension: string}  $file
     */
    private function addExcluded(array &$excluded, string $bucket, array $file, string $reason): void
    {
        $excluded[$bucket]['count']++;
        $excluded[$bucket]['size_bytes'] += $file['size_bytes'];
        $excluded[$bucket]['files'][] = [
            'relative_path' => $file['relative_path'],
            'size_bytes' => $file['size_bytes'],
            'reason' => $reason,
        ];
    }

    private function withExtension(string $relativePath, string $newExtension): string
    {
        return $this->imageService->changeExtension($relativePath, $newExtension);
    }

    /**
     * Unica definizione di "candidato sicuro alla conversione WebP" del
     * progetto: usata sia dalla scansione bulk di audit() sia da
     * MediaWebpMigrationService (FASE 6). Le due non devono mai poter
     * divergere silenziosamente su cosa e' safe — se cambia una regola di
     * esclusione, cambia qui una volta sola.
     *
     * Non esegue alcuna scrittura. $webpDestinationExists riceve il
     * disk_name proposto per il WebP e deve restituire true se esiste gia'
     * un file o un record Media con quel nome (il chiamante decide come
     * verificarlo in modo economico per il proprio contesto: cache
     * precaricata per la scansione bulk, controllo diretto per una singola
     * conversione).
     *
     * @return array{status: 'excluded', bucket: string, reason: string}|array{status: 'eligible', webp_disk_name: string, preflight: array<string, mixed>}
     */
    public function evaluateCandidate(
        ?Media $media,
        string $relativePath,
        string $extension,
        callable $webpDestinationExists,
    ): array {
        if ($extension === 'gif') {
            return ['status' => 'excluded', 'bucket' => 'gif', 'reason' => 'Animazione: la conversione perderebbe i frame successivi al primo.'];
        }

        if (str_starts_with($relativePath, 'turing/')) {
            return ['status' => 'excluded', 'bucket' => 'turing_unmanaged', 'reason' => 'Pagine speciali Turing: nessun record Media, riferimenti spesso hardcoded fuori dal DB (vedi config/media.php e docs/EDITORIAL_MEDIA_WEBP.md) — esclusa esplicitamente da questa missione finche\' non dimostrato sicuro.'];
        }

        if (in_array($relativePath, config('media.protected_disk_names', []), true)) {
            return ['status' => 'excluded', 'bucket' => 'protected', 'reason' => 'Riferimento statico protetto in config/media.php (hardcoded in controller/viste/seeder).'];
        }

        if ($media === null) {
            return ['status' => 'excluded', 'bucket' => 'no_media_record', 'reason' => 'Nessun record Media corrispondente: senza un riferimento strutturato da riscrivere, non e\' verificabile in sicurezza se e dove il file e\' usato. Richiede revisione manuale, non e\' un candidato automatico.'];
        }

        $webpDiskName = $this->withExtension($relativePath, 'webp');

        if ($webpDestinationExists($webpDiskName)) {
            return ['status' => 'excluded', 'bucket' => 'webp_destination_conflict', 'reason' => "Esiste gia' un file o un record Media con il nome di destinazione '{$webpDiskName}': la conversione sovrascriverebbe un asset esistente o punterebbe il riferimento a un file non correlato."];
        }

        $preflight = $this->referenceService->preflight($media, $webpDiskName);

        if ($preflight['blocking_references'] !== []) {
            $reasons = array_values(array_unique(array_filter(array_map(
                fn (array $ref) => $ref['blocking_reason'],
                $preflight['blocking_references']
            ))));

            return ['status' => 'excluded', 'bucket' => 'blocked_references', 'reason' => implode(' | ', $reasons) ?: 'Riferimento non aggiornabile in sicurezza.'];
        }

        return ['status' => 'eligible', 'webp_disk_name' => $webpDiskName, 'preflight' => $preflight];
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

    /**
     * Misura la dimensione WebP REALE convertendo il file in una directory
     * temporanea dedicata (mai in public/assets/img, mai sovrascrivendo
     * nulla), poi elimina subito il file temporaneo. Nessun dato di
     * produzione viene modificato per ottenere questa stima: e' una
     * conversione usa-e-getta, con la stessa qualita'/larghezza massima
     * che userebbe la conversione reale (config('media.webp_quality') /
     * config('media.webp_max_width')), cosi' il numero riportato e' quello
     * vero, non un'approssimazione.
     */
    public function measureActualWebpSize(string $absolutePath): ?int
    {
        $tempDir = sys_get_temp_dir().'/kairus-webp-audit-'.getmypid();

        if (! is_dir($tempDir) && ! @mkdir($tempDir, 0775, true) && ! is_dir($tempDir)) {
            return null;
        }

        $tempDestination = $tempDir.'/'.bin2hex(random_bytes(8)).'.webp';

        try {
            $this->imageService->convertToWebp(
                $absolutePath,
                $tempDestination,
                (int) config('media.webp_quality', 82),
                (int) config('media.webp_max_width', 1600)
            );

            return @filesize($tempDestination) ?: null;
        } catch (\Throwable) {
            return null;
        } finally {
            if (is_file($tempDestination)) {
                @unlink($tempDestination);
            }
        }
    }

    /**
     * Rileva duplicati "sicuri": stesso contenuto byte-per-byte (SHA-256),
     * non solo stesso nome o stessa dimensione. Il confronto per hash
     * avviene solo tra file che condividono gia' la stessa dimensione in
     * byte (raggruppamento economico), cosi' da non calcolare un hash per
     * ogni singolo file quando la libreria e' grande.
     *
     * @param  list<array{relative_path: string, absolute_path: string, size_bytes: int, extension: string}>  $files
     * @return list<array{size_bytes: int, paths: list<string>}>
     */
    private function findSafeDuplicates(array $files): array
    {
        $bySize = [];
        foreach ($files as $file) {
            $bySize[$file['size_bytes']][] = $file;
        }

        $groups = [];
        foreach ($bySize as $size => $candidates) {
            if (count($candidates) < 2) {
                continue;
            }

            $byHash = [];
            foreach ($candidates as $file) {
                $hash = @hash_file('sha256', $file['absolute_path']);

                if ($hash === false) {
                    continue;
                }

                $byHash[$hash][] = $file['relative_path'];
            }

            foreach ($byHash as $paths) {
                if (count($paths) > 1) {
                    $groups[] = ['size_bytes' => $size, 'paths' => $paths];
                }
            }
        }

        return $groups;
    }

    private function resolveArticleCoverDiskName(string $articleFilter): ?string
    {
        $article = ctype_digit($articleFilter)
            ? Article::find((int) $articleFilter)
            : Article::where('slug', $articleFilter)->first();

        return $article?->cover_image;
    }
}
