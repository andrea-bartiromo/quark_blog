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

namespace App\Services;

use App\Models\Media;
use App\Services\Concerns\WalksMediaDirectorySafely;

/**
 * Misura lo spazio disco occupato da Kairus (database, backup, media, log,
 * altro storage applicativo) e rileva possibili incoerenze tra il registro
 * Media e il filesystem — esclusivamente in lettura, non modifica né
 * elimina mai nulla. Pensato per essere invocato da `storage:audit`.
 *
 * La definizione di "media orfano su disco" (file presente ma senza alcun
 * record Media corrispondente) replica intenzionalmente la stessa regola
 * già implementata da MediaClassificationService::findUnregisteredFiles()
 * (confronto esatto sul disk_name contro Media::pluck('disk_name')) — non
 * una seconda definizione divergente, solo ricalcolata sulla scansione
 * unica già raccolta qui per evitare una doppia camminata del filesystem
 * su installazioni con molti file media.
 */
class StorageAuditService
{
    use WalksMediaDirectorySafely;

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function __construct(
        private readonly PublicMediaSyncService $publicMediaSync,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function audit(): array
    {
        $databaseSizeBytes = $this->databaseSizeBytes();
        $backup = $this->backupInfo();
        $logsSizeBytes = $this->directorySizeBytes(storage_path('logs'));
        $otherSizeBytes = $this->directorySizeBytes(storage_path('framework'))
            + $this->directorySizeBytes(storage_path('app'));

        $mediaFiles = $this->walkDirectory(public_path('assets/img'));
        $media = $this->summarizeMedia($mediaFiles);

        $totalMeasurableBytes = $databaseSizeBytes
            + $backup['size_bytes']
            + $media['total_size_bytes']
            + $logsSizeBytes
            + $otherSizeBytes;

        $budgetBytes = (int) config('storage_audit.budget_bytes');

        return [
            'database' => [
                'exists' => $databaseSizeBytes !== null,
                'size_bytes' => $databaseSizeBytes ?? 0,
            ],
            'backup' => $backup,
            'media' => $media,
            'logs' => [
                'size_bytes' => $logsSizeBytes,
            ],
            'other' => [
                'size_bytes' => $otherSizeBytes,
            ],
            'total_measurable_bytes' => $totalMeasurableBytes,
            'budget' => [
                'budget_bytes' => $budgetBytes,
                'used_percent' => $budgetBytes > 0
                    ? round(($totalMeasurableBytes / $budgetBytes) * 100, 2)
                    : null,
                'remaining_bytes' => $budgetBytes > 0
                    ? max(0, $budgetBytes - $totalMeasurableBytes)
                    : null,
            ],
        ];
    }

    private function databaseSizeBytes(): ?int
    {
        $path = database_path('database.sqlite');

        if (! is_file($path)) {
            return null;
        }

        $size = @filesize($path);

        return $size === false ? null : $size;
    }

    /**
     * @return array{size_bytes: int, count: int, multiplier: int}
     */
    private function backupInfo(): array
    {
        $dir = storage_path('backups');
        $files = is_dir($dir) ? (glob($dir.'/database-*.sqlite') ?: []) : [];

        $sizeBytes = 0;
        foreach ($files as $file) {
            $size = @filesize($file);
            $sizeBytes += $size === false ? 0 : $size;
        }

        return [
            'size_bytes' => $sizeBytes,
            'count' => count($files),
            // +1: il database "live" oltre alle copie di backup.
            'multiplier' => count($files) + 1,
        ];
    }

    /**
     * @param  list<array{relative_path: string, absolute_path: string, size_bytes: int, extension: string}>  $files
     * @return array<string, mixed>
     */
    private function summarizeMedia(array $files): array
    {
        $diskNames = Media::query()->pluck('disk_name')->all();
        $diskNameSet = array_fill_keys($diskNames, true);

        $fileByRelativePath = [];
        $totalSizeBytes = 0;
        $formatBreakdown = [];
        $imageFiles = [];

        foreach ($files as $file) {
            $fileByRelativePath[$file['relative_path']] = true;
            $totalSizeBytes += $file['size_bytes'];

            $bucket = $this->formatBucket($file['extension']);
            $formatBreakdown[$bucket] ??= ['count' => 0, 'size_bytes' => 0];
            $formatBreakdown[$bucket]['count']++;
            $formatBreakdown[$bucket]['size_bytes'] += $file['size_bytes'];

            if (in_array($file['extension'], self::IMAGE_EXTENSIONS, true)) {
                $imageFiles[] = $file;
            }
        }

        // Orfani: file su disco senza alcun record Media corrispondente —
        // stessa regola di MediaClassificationService::findUnregisteredFiles(),
        // vedi docblock della classe.
        $orphanFiles = array_values(array_filter(
            $files,
            fn (array $file) => ! isset($diskNameSet[$file['relative_path']])
        ));

        // Direzione opposta (non coperta da nessun servizio esistente, vedi
        // ricerca FASE 1/4): record Media il cui file non esiste su disco.
        $missingFiles = array_values(array_filter(
            $diskNames,
            fn (string $diskName) => ! isset($fileByRelativePath[$diskName])
        ));

        usort($imageFiles, fn (array $a, array $b) => $b['size_bytes'] <=> $a['size_bytes']);
        $topHeaviestImages = array_slice($imageFiles, 0, 10);

        $directorySizes = $this->directoryTotalsFromFiles($files);
        arsort($directorySizes);
        $topHeaviestDirectories = array_slice($directorySizes, 0, 10, true);

        return [
            'total_size_bytes' => $totalSizeBytes,
            'total_count' => count($files),
            'registered_in_db_count' => count($diskNames),
            'on_filesystem_count' => count($files),
            'orphan_count' => count($orphanFiles),
            'orphan_files' => array_map(fn (array $f) => $f['relative_path'], $orphanFiles),
            'missing_file_count' => count($missingFiles),
            'missing_files' => $missingFiles,
            'format_breakdown' => $formatBreakdown,
            'image_count' => count($imageFiles),
            'average_image_size_bytes' => count($imageFiles) > 0
                ? (int) round(array_sum(array_column($imageFiles, 'size_bytes')) / count($imageFiles))
                : 0,
            'top_heaviest_images' => array_map(
                fn (array $f) => ['relative_path' => $f['relative_path'], 'size_bytes' => $f['size_bytes']],
                $topHeaviestImages
            ),
            'top_heaviest_directories' => $topHeaviestDirectories,
            'public_root_sync' => $this->publicRootSyncInfo($files),
        ];
    }

    /**
     * Verifica mirata (non una seconda scansione ricorsiva completa della
     * radice pubblica secondaria, che può trovarsi fuori dal progetto ed
     * essere di dimensione/contenuto arbitrari — es. l'intera document root
     * cPanel): per ciascun file già noto da public/assets/img, controlla se
     * esiste un file identico per percorso in MEDIA_PUBLIC_ROOT. Rileva
     * "presente in entrambe" e "presente solo nella root app", non rileva
     * "presente solo nella root pubblica secondaria" (limite noto, da
     * documentare — richiederebbe una scansione completa di una directory
     * esterna al progetto).
     *
     * @param  list<array{relative_path: string, absolute_path: string, size_bytes: int, extension: string}>  $files
     * @return array<string, mixed>
     */
    private function publicRootSyncInfo(array $files): array
    {
        $configuredRoot = config('media.public_root');

        if (blank($configuredRoot)) {
            return [
                'configured' => false,
                'configured_value' => null,
                'collapses_to_app_root' => null,
                'present_in_both_count' => null,
                'present_only_in_app_root_count' => null,
            ];
        }

        // Stessa regola di uguaglianza di PublicMediaSyncService::resolveTarget():
        // se la radice pubblica secondaria risolve, via realpath(), alla stessa
        // directory fisica di public/assets/img, la sincronizzazione è
        // disattivata (scrivere sarebbe scrivere un file su se stesso).
        $appRootReal = realpath(public_path('assets/img'));
        $configuredRootReal = realpath($configuredRoot);
        $collapses = $appRootReal !== false
            && $configuredRootReal !== false
            && $appRootReal === $configuredRootReal;

        if ($collapses || ! $this->publicMediaSync->isEnabled()) {
            return [
                'configured' => true,
                'configured_value' => $configuredRoot,
                'collapses_to_app_root' => $collapses,
                'present_in_both_count' => null,
                'present_only_in_app_root_count' => null,
            ];
        }

        $presentInBoth = 0;
        $presentOnlyInAppRoot = 0;

        foreach ($files as $file) {
            $secondaryPath = rtrim(str_replace('\\', '/', $configuredRoot), '/').'/'.$file['relative_path'];

            if (@is_file($secondaryPath)) {
                $presentInBoth++;
            } else {
                $presentOnlyInAppRoot++;
            }
        }

        return [
            'configured' => true,
            'configured_value' => $configuredRoot,
            'collapses_to_app_root' => false,
            'present_in_both_count' => $presentInBoth,
            'present_only_in_app_root_count' => $presentOnlyInAppRoot,
        ];
    }

    private function formatBucket(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            'gif' => 'gif',
            default => 'other',
        };
    }

    /**
     * @param  list<array{relative_path: string, absolute_path: string, size_bytes: int, extension: string}>  $files
     * @return array<string, int> percorso directory (relativo) => byte totali
     */
    private function directoryTotalsFromFiles(array $files): array
    {
        $totals = [];

        foreach ($files as $file) {
            $directory = dirname($file['relative_path']);
            $directory = $directory === '.' ? '(radice)' : $directory;
            $totals[$directory] = ($totals[$directory] ?? 0) + $file['size_bytes'];
        }

        return $totals;
    }

    private function directorySizeBytes(string $absoluteDir): int
    {
        $total = 0;

        foreach ($this->walkDirectory($absoluteDir) as $file) {
            $total += $file['size_bytes'];
        }

        return $total;
    }
}
