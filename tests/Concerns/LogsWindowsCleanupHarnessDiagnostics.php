<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Diagnostica TEMPORANEA (round 2-3) per i 4 test "cleans up the local
 * file" ancora falliti su Windows reale nonostante la diagnostica di
 * produzione (PR #124/#125) dimostri che cleanupAfterFailedCreate()
 * riceve un path valido e che unlink() riesce al primo tentativo
 * (file_exists=false subito dopo). Round 3: la nuova evidenza Windows
 * mostra che il file torna a esistere DOPO che unlink() e' riuscito, ma
 * PRIMA che il test lo ritrovi con filesUnder() — questi helper
 * aggiungono i checkpoint G/H/I della timeline richiesta (subito dopo il
 * ritorno della request, dopo un clearstatcache esplicito sul path
 * risorto, e sul confronto finale) per capire se la ricomparsa avviene
 * gia' quando il controllo torna al test (quindi nel kernel HTTP/
 * middleware/framework) o solo nella scansione stessa (quindi un
 * artefatto della stat cache o di filesUnder()).
 */
trait LogsWindowsCleanupHarnessDiagnostics
{
    private function logHarnessCheckpoint(string $checkpoint, array $extra = []): void
    {
        Log::debug('WindowsCleanupHarnessDiagnostics(test): '.$checkpoint, array_merge([
            'checkpoint' => $checkpoint,
            'hrtime_ns' => hrtime(true),
            'public_path_assets_img' => public_path('assets/img'),
            'app_object_id' => spl_object_id($this->app),
        ], $extra));
    }

    /**
     * Come logHarnessCheckpoint(), ma per un path specifico (es. il file
     * ricomparso, individuato come diff tra filesBefore/filesAfter):
     * clearstatcache esplicito prima di ogni controllo, cosi' il
     * checkpoint riflette lo stato reale del filesystem in quell'istante,
     * mai la stat cache di PHP popolata da un controllo precedente nella
     * stessa request/test.
     */
    private function logHarnessPathCheckpoint(string $checkpoint, string $path, array $extra = []): void
    {
        clearstatcache(true, $path);

        Log::debug('WindowsCleanupHarnessDiagnostics(test): '.$checkpoint, array_merge([
            'checkpoint' => $checkpoint,
            'path' => $path,
            'hrtime_ns' => hrtime(true),
            'file_exists' => file_exists($path),
            'is_file' => is_file($path),
            'realpath' => realpath($path),
            'filesize' => file_exists($path) ? @filesize($path) : null,
            'filemtime' => file_exists($path) ? @filemtime($path) : null,
            'md5' => is_file($path) ? @md5_file($path) : null,
        ], $extra));
    }
}
