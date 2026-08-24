<?php

namespace App\Services\Deploy;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Diagnostica read-only: confronta i file statici gestiti dalla release
 * (config('deploy.asset_drift_scan_paths')) tra l'albero applicativo
 * (public_path(), da cui App\Support\VersionedAsset e tutta l'app leggono)
 * e la radice effettivamente servita da Apache
 * (config('deploy.served_public_root')). Non scrive né sposta mai un file:
 * un mismatch va risolto dall'operatore o dal processo di deploy esterno,
 * mai da questo servizio.
 *
 * Disattivato di default (report() ritorna ['enabled' => false]) quando
 * served_public_root non è configurato — stesso contratto già in uso da
 * PublicMediaSyncService::isEnabled() per MEDIA_PUBLIC_ROOT — e si
 * disattiva anche da solo se le due radici risolvono, tramite realpath(),
 * alla stessa directory fisica.
 */
class PublicAssetDriftDetector
{
    public const STATUS_OK = 'ok';

    public const STATUS_MISMATCH = 'mismatch';

    public const STATUS_MISSING_ON_WEBROOT = 'missing_on_webroot';

    public const STATUS_MISSING_ON_APP = 'missing_on_app';

    /**
     * @return array{
     *     enabled: bool,
     *     entries?: list<array{path:string,status:string,app_hash:?string,served_hash:?string}>,
     *     totals?: array{scanned:int,ok:int,mismatch:int,missing_on_webroot:int,missing_on_app:int}
     * }
     */
    public function report(): array
    {
        $servedRoot = $this->servedRoot();

        if ($servedRoot === null) {
            return ['enabled' => false];
        }

        $appRoot = rtrim(str_replace('\\', '/', public_path()), '/');
        $scanPaths = (array) config('deploy.asset_drift_scan_paths', []);

        $relativePaths = $this->collectRelativePaths($appRoot, $servedRoot, $scanPaths);

        $totals = [
            self::STATUS_OK => 0,
            self::STATUS_MISMATCH => 0,
            self::STATUS_MISSING_ON_WEBROOT => 0,
            self::STATUS_MISSING_ON_APP => 0,
        ];
        $entries = [];

        foreach ($relativePaths as $relative) {
            $appPath = $appRoot.'/'.$relative;
            $servedPath = $servedRoot.'/'.$relative;

            $appHash = is_file($appPath) ? hash_file('sha256', $appPath) : null;
            $servedHash = is_file($servedPath) ? hash_file('sha256', $servedPath) : null;

            $status = match (true) {
                $appHash === null => self::STATUS_MISSING_ON_APP,
                $servedHash === null => self::STATUS_MISSING_ON_WEBROOT,
                $appHash !== $servedHash => self::STATUS_MISMATCH,
                default => self::STATUS_OK,
            };

            $totals[$status]++;

            $entries[] = [
                'path' => $relative,
                'status' => $status,
                'app_hash' => $appHash,
                'served_hash' => $servedHash,
            ];
        }

        usort($entries, fn (array $a, array $b) => $a['path'] <=> $b['path']);

        return [
            'enabled' => true,
            'entries' => $entries,
            'totals' => [
                'scanned' => count($entries),
                ...$totals,
            ],
        ];
    }

    /**
     * true quando il detector è disattivato (nulla da verificare — non è
     * un fallimento) oppure quando è attivo e non ha trovato alcuna
     * anomalia.
     */
    public function isClean(): bool
    {
        $report = $this->report();

        if (! $report['enabled']) {
            return true;
        }

        return $report['totals'][self::STATUS_MISMATCH] === 0
            && $report['totals'][self::STATUS_MISSING_ON_WEBROOT] === 0
            && $report['totals'][self::STATUS_MISSING_ON_APP] === 0;
    }

    private function servedRoot(): ?string
    {
        $root = config('deploy.served_public_root');

        if (! filled($root)) {
            return null;
        }

        $appRootReal = realpath(public_path());
        $servedRootReal = realpath($root);

        if ($appRootReal !== false && $servedRootReal !== false && $appRootReal === $servedRootReal) {
            return null;
        }

        return rtrim(str_replace('\\', '/', $root), '/');
    }

    /**
     * @param  list<string>  $scanPaths
     * @return list<string>
     */
    private function collectRelativePaths(string $appRoot, string $servedRoot, array $scanPaths): array
    {
        $paths = [];

        foreach ($scanPaths as $target) {
            $target = trim(str_replace('\\', '/', $target), '/');

            if ($target === '') {
                continue;
            }

            $appTarget = $appRoot.'/'.$target;
            $servedTarget = $servedRoot.'/'.$target;

            if (is_dir($appTarget) || is_dir($servedTarget)) {
                $paths = [
                    ...$paths,
                    ...$this->scanDirectory($appRoot, $appTarget),
                    ...$this->scanDirectory($servedRoot, $servedTarget),
                ];
            } else {
                // Target puntuale (file singolo): confrontato anche se non
                // esiste ancora in nessuna delle due radici, cosi' un file
                // atteso ma mancante ovunque risulta comunque nel report
                // come missing su entrambi i lati, non silenziosamente
                // ignorato.
                $paths[] = $target;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private function scanDirectory(string $root, string $absoluteDir): array
    {
        if (! is_dir($absoluteDir)) {
            return [];
        }

        $found = [];
        $rootLength = strlen($root) + 1;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absoluteDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $found[] = str_replace('\\', '/', substr($file->getPathname(), $rootLength));
        }

        return $found;
    }
}
