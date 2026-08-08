<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Diagnostica TEMPORANEA (round 2) per i 4 test "cleans up the local file"
 * ancora falliti su Windows reale nonostante la diagnostica di produzione
 * (PR #124) dimostri che cleanupAfterFailedCreate() riceve un path valido e
 * che unlink() riesce al primo tentativo. Se il layer di produzione e' gia'
 * scagionato, l'unico posto rimasto da osservare e' il test harness stesso:
 * quando cambia public_path(), se l'istanza dell'Application viene
 * ricreata durante la richiesta HTTP, e se filesBefore/filesAfter vengono
 * davvero calcolati sulla stessa directory fisica dove il cleanup ha agito.
 */
trait LogsWindowsCleanupHarnessDiagnostics
{
    private function logHarnessCheckpoint(string $checkpoint, array $extra = []): void
    {
        Log::debug('WindowsCleanupHarnessDiagnostics(test): '.$checkpoint, array_merge([
            'checkpoint' => $checkpoint,
            'public_path_assets_img' => public_path('assets/img'),
            'app_object_id' => spl_object_id($this->app),
        ], $extra));
    }
}
