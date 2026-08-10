<?php

namespace App\Services\Concerns;

use RuntimeException;

/**
 * Risolve il percorso assoluto di un file della Libreria media a partire
 * dal suo disk_name, garantendo che il risultato esista davvero, non sia
 * un collegamento simbolico e resti dentro la radice attesa (mai un
 * path traversal via disk_name manomesso). Estratta da MediaMoveService
 * (dove e' nata per lo spostamento di cartella) perche'
 * MediaWebpMigrationService (FASE 6) deve risolvere il sorgente esistente
 * con esattamente le stesse garanzie prima di convertirlo: un'unica
 * implementazione di questo controllo filesystem-safety-critical, non due
 * che potrebbero divergere.
 */
trait ResolvesMediaFileSafely
{
    private function safeExistingFilePath(string $root, string $diskName): string
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new RuntimeException('La radice media non e risolvibile.');
        }

        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $diskName);

        if (is_link($path)) {
            throw new RuntimeException('Il file sorgente e un collegamento simbolico.');
        }

        if (! is_file($path)) {
            throw new RuntimeException('Il file sorgente non esiste sul filesystem: '.$diskName);
        }

        $real = realpath($path);
        if ($real === false || ! $this->isWithin($real, $rootReal)) {
            throw new RuntimeException('Il file sorgente esce dalla radice media.');
        }

        return $real;
    }

    private function isWithin(string $path, string $root): bool
    {
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);

        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $root = strtolower($root);
        }

        return $path === $root || str_starts_with($path, $root.DIRECTORY_SEPARATOR);
    }
}
