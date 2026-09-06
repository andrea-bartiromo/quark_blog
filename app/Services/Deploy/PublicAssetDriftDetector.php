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
     * Contenuto identico su entrambe le radici ma con permessi troppo
     * restrittivi per essere serviti in modo affidabile (es. un file 600
     * copiato con `cp -a` da un backup che aveva preservato quel permesso,
     * o una directory 700 non piu' attraversabile da Apache). Il detector
     * confronta da sempre solo hash/presenza: questo status copre il gap
     * — un asset "corretto" nel contenuto ma potenzialmente illeggibile.
     */
    public const STATUS_UNSAFE_MODE = 'unsafe_mode';

    /** Permesso minimo per un file di release: rw-r--r--. */
    private const MIN_FILE_MODE = 0644;

    /** Permesso minimo per una directory di release: rwxr-xr-x. */
    private const MIN_DIR_MODE = 0755;

    /**
     * @return array{
     *     enabled: bool,
     *     entries?: list<array{path:string,status:string,app_hash:?string,served_hash:?string}>,
     *     totals?: array{scanned:int,ok:int,mismatch:int,missing_on_webroot:int,missing_on_app:int,unsafe_mode:int}
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
            self::STATUS_UNSAFE_MODE => 0,
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
                ! $this->hasSafeFileMode($appPath) || ! $this->hasSafeFileMode($servedPath) => self::STATUS_UNSAFE_MODE,
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

        foreach ($this->unsafeScannedDirectories($appRoot, $servedRoot, $scanPaths) as $unsafeDir) {
            $totals[self::STATUS_UNSAFE_MODE]++;

            $entries[] = [
                'path' => $unsafeDir,
                'status' => self::STATUS_UNSAFE_MODE,
                'app_hash' => null,
                'served_hash' => null,
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
            && $report['totals'][self::STATUS_MISSING_ON_APP] === 0
            && $report['totals'][self::STATUS_UNSAFE_MODE] === 0;
    }

    private function hasSafeFileMode(string $path): bool
    {
        if (! is_file($path)) {
            return true;
        }

        $mode = @fileperms($path);

        if ($mode === false) {
            return false;
        }

        return ($mode & self::MIN_FILE_MODE) === self::MIN_FILE_MODE;
    }

    private function hasSafeDirMode(string $path): bool
    {
        if (! is_dir($path)) {
            return true;
        }

        $mode = @fileperms($path);

        if ($mode === false) {
            return false;
        }

        return ($mode & self::MIN_DIR_MODE) === self::MIN_DIR_MODE;
    }

    /**
     * Verifica il permesso delle directory nominate direttamente in
     * asset_drift_scan_paths (non ogni sottodirectory attraversata da
     * scanDirectory() — quelle sono gia' coperte indirettamente: se non
     * sono attraversabili, scanDirectory() semplicemente non trova i file
     * al loro interno, che risultano "missing"). Restituisce solo i
     * percorsi realmente non sicuri: una directory sicura non genera
     * rumore nel report, coerentemente con come i file OK non lo fanno.
     *
     * @param  list<string>  $scanPaths
     * @return list<string>
     */
    private function unsafeScannedDirectories(string $appRoot, string $servedRoot, array $scanPaths): array
    {
        $unsafe = [];

        foreach ($scanPaths as $target) {
            $target = trim(str_replace('\\', '/', $target), '/');

            if ($target === '') {
                continue;
            }

            $appTarget = $appRoot.'/'.$target;
            $servedTarget = $servedRoot.'/'.$target;

            $appIsDir = is_dir($appTarget);
            $servedIsDir = is_dir($servedTarget);

            if (! $appIsDir && ! $servedIsDir) {
                continue;
            }

            $safe = (! $appIsDir || $this->hasSafeDirMode($appTarget))
                && (! $servedIsDir || $this->hasSafeDirMode($servedTarget));

            if (! $safe) {
                $unsafe[] = $target;
            }
        }

        return array_values(array_unique($unsafe));
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
