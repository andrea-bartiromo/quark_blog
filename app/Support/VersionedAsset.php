<?php

namespace App\Support;

/**
 * Cache busting per gli asset pubblici statici (CSS/JS in public/), basato
 * sul mtime reale del file — mai un contatore manuale (?v=10) da
 * ricordarsi di incrementare a ogni modifica, ed esattamente lo stesso
 * fallback già usato per public/css/admin.css in layouts/admin.blade.php.
 * Un contatore manuale dimenticato serve a un browser/CDN una versione
 * stale indefinitamente; un file mai versionato (il caso più comune tra
 * gli asset pubblici esistenti) rischia lo stesso problema fin dal primo
 * deploy successivo alla release iniziale.
 */
class VersionedAsset
{
    /**
     * @param  string  $relativePath  Percorso relativo a public/, es. "css/style.css".
     */
    public static function url(string $relativePath): string
    {
        $absolutePath = public_path($relativePath);
        $version = is_file($absolutePath) ? filemtime($absolutePath) : 1;

        return asset($relativePath).'?v='.$version;
    }
}
