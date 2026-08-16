<?php

namespace App\Services\Concerns;

/**
 * Cammina ricorsivamente una directory e restituisce ogni file trovato,
 * con dimensione e percorso relativo alla base. Estratto da
 * StorageAuditService (dove questa logica e' stata originariamente
 * scritta e verificata contro l'intera matrice avversariale in
 * StorageAuditServiceTest) perche' MediaWebpAuditService ne ha bisogno
 * identica: unica implementazione, mai due copie della stessa logica
 * di sicurezza sul filesystem.
 *
 * Sicura contro:
 * - directory inesistente (restituisce lista vuota, nessun errore);
 * - symlink che punta fuori da $baseDir (scartato, mai seguito);
 * - symlink rotto (realpath() fallisce, scartato senza errori);
 * - cicli di symlink (ogni directory reale e' visitata una sola volta);
 * - errori di permesso su una sottodirectory (scandir() fallisce, quella
 *   sottodirectory viene saltata, il resto della scansione prosegue).
 *
 * Non legge mai il contenuto dei file (solo filesize()), quindi il costo
 * di memoria resta O(numero di file), non O(dimensione totale).
 */
trait WalksMediaDirectorySafely
{
    /**
     * @return list<array{relative_path: string, absolute_path: string, size_bytes: int, extension: string}>
     */
    private function walkDirectory(string $baseDir): array
    {
        $baseReal = realpath($baseDir);

        if ($baseReal === false || ! is_dir($baseReal)) {
            return [];
        }

        $files = [];
        $visitedDirs = [];
        $this->walkRecursive($baseReal, $baseReal, $files, $visitedDirs);

        return $files;
    }

    /**
     * @param  list<array{relative_path: string, absolute_path: string, size_bytes: int, extension: string}>  $files
     * @param  array<string, true>  $visitedDirs
     */
    private function walkRecursive(string $dir, string $baseReal, array &$files, array &$visitedDirs): void
    {
        if (isset($visitedDirs[$dir])) {
            return;
        }

        $visitedDirs[$dir] = true;

        $entries = @scandir($dir);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            $realPath = @realpath($path);

            if ($realPath === false) {
                // Symlink rotto, o percorso diventato illeggibile tra lo
                // scandir() e questo controllo: scartato, mai un errore.
                continue;
            }

            if ($realPath !== $baseReal && ! str_starts_with($realPath, $baseReal.DIRECTORY_SEPARATOR)) {
                // Esce dai confini di $baseDir (es. un symlink verso una
                // directory esterna): mai seguito.
                continue;
            }

            if (is_dir($realPath)) {
                $this->walkRecursive($realPath, $baseReal, $files, $visitedDirs);

                continue;
            }

            if (! is_file($realPath)) {
                continue;
            }

            $size = @filesize($realPath);

            if ($size === false) {
                continue;
            }

            $relative = ltrim(substr($realPath, strlen($baseReal)), DIRECTORY_SEPARATOR);
            $relative = str_replace('\\', '/', $relative);

            $files[] = [
                'relative_path' => $relative,
                'absolute_path' => $realPath,
                'size_bytes' => $size,
                'extension' => strtolower(pathinfo($realPath, PATHINFO_EXTENSION)),
            ];
        }
    }
}
