<?php

namespace Tests\Concerns;

/**
 * Attiva MEDIA_PUBLIC_ROOT per la durata del singolo test, puntandolo a una
 * directory temporanea isolata che simula la document root pubblica
 * secondaria (es. public_html su cPanel). Va usato insieme a
 * UsesIsolatedPublicPath, che isola public/assets/img: le due directory
 * temporanee restano cosi' fisicamente distinte, come nello scenario reale
 * che PublicMediaSyncService deve gestire.
 */
trait UsesIsolatedMediaPublicRoot
{
    protected string $isolatedMediaPublicRoot;

    private const PUBLIC_ROOT_MARKER = 'kairus-test-media-public-root-';

    protected function setUpIsolatedMediaPublicRoot(): void
    {
        $this->isolatedMediaPublicRoot = sys_get_temp_dir().'/'.self::PUBLIC_ROOT_MARKER.uniqid('', true);

        mkdir($this->isolatedMediaPublicRoot, 0775, true);

        config(['media.public_root' => $this->isolatedMediaPublicRoot]);
    }

    protected function tearDownIsolatedMediaPublicRoot(): void
    {
        if (! isset($this->isolatedMediaPublicRoot) || ! str_contains($this->isolatedMediaPublicRoot, self::PUBLIC_ROOT_MARKER)) {
            return;
        }

        $this->deleteMediaPublicRootRecursively($this->isolatedMediaPublicRoot);
    }

    private function deleteMediaPublicRootRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;

            if (is_dir($path) && ! is_link($path)) {
                $this->deleteMediaPublicRootRecursively($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
