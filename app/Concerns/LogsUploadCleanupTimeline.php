<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * DIAGNOSTICA TEMPORANEA (round 3, PR #125) — da rimuovere una volta
 * trovata la causa reale.
 *
 * La diagnostica delle PR #124/#125 ha già dimostrato, su Windows reale,
 * che cleanupAfterFailedCreate() riceve un path valido e che unlink()
 * riesce al primo tentativo (file_exists=false subito dopo). Eppure, al
 * termine della request HTTP, il test trova di nuovo lo stesso file.
 * Questo trait aggiunge i checkpoint E/F della timeline richiesta:
 * immediatamente dopo che cleanupAfterFailedCreate() ritorna al
 * controller, e il più tardi possibile prima che il controller restituisca
 * una risposta — per capire se il file torna a esistere già dentro il
 * controller (quindi per una causa applicativa) o solo dopo, nel
 * frattempo tra il controller e il test (quindi framework/kernel/OS).
 */
trait LogsUploadCleanupTimeline
{
    private function logCleanupTimelineCheckpoint(string $checkpoint, string $path): void
    {
        clearstatcache(true, $path);

        Log::debug('UploadCleanupTimeline: diagnostica temporanea.', [
            'checkpoint' => $checkpoint,
            'path' => $path,
            'hrtime_ns' => hrtime(true),
            'file_exists' => file_exists($path),
            'is_file' => is_file($path),
            'realpath' => realpath($path),
            'filesize' => file_exists($path) ? @filesize($path) : null,
            'filemtime' => file_exists($path) ? @filemtime($path) : null,
        ]);
    }
}
