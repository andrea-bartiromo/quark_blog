<?php

namespace App\Support;

/**
 * Risolve l'unica radice in cui le immagini della Libreria Media sono
 * effettivamente servite. Su cPanel puo' essere distinta da
 * public_path('assets/img') della release Laravel.
 *
 * MEDIA_PUBLIC_ROOT e' gia' la configurazione usata da
 * PublicMediaSyncService e ResponsiveImageVariantService: questo helper
 * evita che gli audit ricostruiscano una terza nozione di root pubblica.
 */
final class MediaServedRoot
{
    public static function path(): string
    {
        $configured = config('media.public_root');

        if (is_string($configured) && trim($configured) !== '') {
            return rtrim(str_replace('\\', '/', trim($configured)), '/');
        }

        return rtrim(str_replace('\\', '/', public_path('assets/img')), '/');
    }

    public static function contains(string $diskName): bool
    {
        if (
            $diskName === ''
            || str_contains($diskName, "\0")
            || str_contains($diskName, '..')
            || str_contains($diskName, '\\')
            || str_starts_with($diskName, '/')
        ) {
            return false;
        }

        return is_file(self::path().'/'.$diskName);
    }
}
