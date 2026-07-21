<?php

namespace Tests\Concerns;

/**
 * Isola i controller (che scrivono direttamente su public_path()) da
 * public/assets reali, sovrascrivendo il binding "path.public" di Laravel
 * per la durata del singolo test. Nessuna modifica al codice applicativo:
 * usePublicPath() e un'API Laravel gia esistente pensata proprio per
 * questo scopo.
 *
 * La directory temporanea viene creata in setUp() e rimossa in tearDown(),
 * con un marker nel nome che la distingue in modo inequivocabile da
 * qualunque path reale del progetto, cosi da rendere sicura la pulizia
 * ricorsiva anche in caso di errore nel test.
 */
trait UsesIsolatedPublicPath
{
    protected string $isolatedPublicPath;

    private const MARKER = 'quark-test-public-';

    protected function setUpIsolatedPublicPath(): void
    {
        $this->isolatedPublicPath = sys_get_temp_dir().'/'.self::MARKER.uniqid('', true);

        // Le rotte Admin\ArticleController e Redazione\ArticleController
        // non chiamano ensureDirectoryExists(): nel progetto reale
        // public/assets/img esiste gia su disco, quindi la fixture deve
        // pre-crearla per riprodurre fedelmente quella precondizione.
        mkdir($this->isolatedPublicPath.'/assets/img/categories', 0775, true);

        $this->app->usePublicPath($this->isolatedPublicPath);
    }

    protected function tearDownIsolatedPublicPath(): void
    {
        if (! isset($this->isolatedPublicPath) || ! str_contains($this->isolatedPublicPath, self::MARKER)) {
            return;
        }

        $this->deleteDirectoryRecursively($this->isolatedPublicPath);
    }

    private function deleteDirectoryRecursively(string $dir): void
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
                $this->deleteDirectoryRecursively($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
