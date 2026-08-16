<?php

namespace App\Services\Concerns;

/**
 * Riconosce se un path e' assoluto in modo davvero cross-platform.
 * `str_starts_with($path, '/')` da solo (il pattern che era sparso in piu'
 * punti del progetto) riconosce solo i path assoluti Unix: su Windows un
 * path assoluto come "C:\Users\..." o "C:/Users/..." non inizia mai per
 * "/", quindi veniva trattato erroneamente come relativo e concatenato a
 * base_path() — producendo un path incoerente che poi falliva a runtime
 * (mkdir(): No such file or directory, o una directory scansionata che in
 * realta' non esiste mai). Estratto da ImportLegacyMedia (dove questo
 * controllo esisteva gia', ma solo li') perche' MediaWebpCleanupService,
 * ConvertMediaToWebp e ClassifyExistingMedia ne hanno bisogno identico:
 * unica implementazione, mai piu' copie che potrebbero divergere.
 */
trait DetectsAbsolutePaths
{
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }
}
