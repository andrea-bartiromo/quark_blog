<?php

namespace Tests\Concerns;

/**
 * Isola storage_path() e database_path() su una directory temporanea per la
 * durata del singolo test, sullo stesso modello di UsesIsolatedPublicPath:
 * sovrascrive i binding "path.storage" e "path.database" di Laravel (API
 * gia' esistenti pensate proprio per questo scopo), nessuna modifica al
 * codice applicativo.
 *
 * Non tocca la connessione Eloquent di test (resta quella in-memory di
 * phpunit.xml): database_path('database.sqlite') qui serve solo come file
 * "produzione simulato" che StorageAuditService misura da filesystem,
 * indipendente dalla connessione DB realmente usata dai test.
 */
trait UsesIsolatedStoragePath
{
    protected string $isolatedStoragePath;

    protected string $isolatedDatabasePath;

    private const STORAGE_MARKER = 'kairus-test-storage-';

    protected function setUpIsolatedStoragePath(): void
    {
        $unique = uniqid('', true);
        $this->isolatedStoragePath = sys_get_temp_dir().'/'.self::STORAGE_MARKER.$unique;
        $this->isolatedDatabasePath = sys_get_temp_dir().'/'.self::STORAGE_MARKER.'db-'.$unique;

        mkdir($this->isolatedStoragePath.'/logs', 0775, true);
        mkdir($this->isolatedStoragePath.'/backups', 0775, true);
        mkdir($this->isolatedStoragePath.'/framework', 0775, true);
        mkdir($this->isolatedStoragePath.'/app', 0775, true);
        mkdir($this->isolatedDatabasePath, 0775, true);

        $this->app->useStoragePath($this->isolatedStoragePath);
        $this->app->useDatabasePath($this->isolatedDatabasePath);
    }

    protected function tearDownIsolatedStoragePath(): void
    {
        if (isset($this->isolatedStoragePath) && str_contains($this->isolatedStoragePath, self::STORAGE_MARKER)) {
            $this->deleteIsolatedStoragePathRecursively($this->isolatedStoragePath);
        }

        if (isset($this->isolatedDatabasePath) && str_contains($this->isolatedDatabasePath, self::STORAGE_MARKER)) {
            $this->deleteIsolatedStoragePathRecursively($this->isolatedDatabasePath);
        }
    }

    private function deleteIsolatedStoragePathRecursively(string $dir): void
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
                $this->deleteIsolatedStoragePathRecursively($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
