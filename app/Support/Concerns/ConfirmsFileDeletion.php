<?php

namespace App\Support\Concerns;

/**
 * Windows-safe confirmation that a file is truly gone after an unlink()
 * call already reported success.
 *
 * Evidenza reale (log Windows): unlink() restituisce true, una verifica
 * immediata — e persino una singola riverifica ~100ms dopo — vedono il
 * file assente, eppure lo stesso file può ricomparire poco dopo (un
 * handle non ancora rilasciato dal sistema operativo, o una scansione
 * antivirus in tempo reale). Una singola riverifica non è quindi
 * sufficiente: qui il file deve risultare assente per PIÙ controlli
 * consecutivi, non uno solo, prima che una rimozione "apparentemente
 * riuscita" venga trattata come definitiva.
 *
 * Condiviso tra ImageService (rimozione del sorgente JPG/PNG dopo una
 * conversione automatica a WebP) e PublicMediaSyncService (rimozione
 * verso la document root pubblica secondaria): stessa evidenza Windows,
 * stessa strategia di conferma. Prima di questo trait, solo
 * PublicMediaSyncService aveva una riverifica (singola) dopo un
 * unlink() riuscito — ImageService::removeSourceWithRetry() tornava
 * immediatamente su removeFile() === true, lasciando esposto esattamente
 * lo stesso bug per il file sorgente nei flussi WebP.
 *
 * Costo tenuto deliberatamente basso sul percorso comune
 * (Linux/production, nessuna ricomparsa): questo metodo viene invocato
 * solo DOPO che un tentativo di unlink() ha già riportato successo — un
 * vero fallimento di unlink() non passa mai da qui, resta gestito dal
 * ciclo di retry del chiamante. Ogni controllo di conferma riusa lo
 * stesso intervallo (100ms) già adottato in precedenza per la singola
 * riverifica: portarli a più controlli consecutivi allunga la finestra
 * totale di conferma di poche centinaia di millisecondi al massimo — mai
 * secondi — e solo per una rimozione che si stava già rivelando incerta,
 * non per il caso normale.
 */
trait ConfirmsFileDeletion
{
    /**
     * @param  string|false  $originalContentHash  digest SHA-256 del contenuto
     *                                             del file PRIMA del tentativo di unlink() che ha appena
     *                                             riportato successo, o false se non leggibile (in quel caso
     *                                             un'eventuale ricomparsa viene trattata in modo conservativo
     *                                             come il nostro stesso file, coerente col comportamento
     *                                             preesistente).
     * @param  callable(string, int): void|null  $onDifferentContentReappeared
     *                                                                          invocata al massimo una volta, se un controllo trova un
     *                                                                          contenuto diverso da $originalContentHash allo stesso path
     *                                                                          (un'altra scrittura legittima e concorrente, non il nostro
     *                                                                          file risorto) — mai per la sola assenza confermata del file.
     */
    private function confirmFileReallyGone(
        string $path,
        string|false $originalContentHash,
        int $checks,
        int $delayMicroseconds,
        ?callable $onDifferentContentReappeared = null,
    ): bool {
        for ($check = 1; $check <= $checks; $check++) {
            usleep($delayMicroseconds);
            clearstatcache(true, $path);

            if (! file_exists($path)) {
                // Questo controllo conferma l'assenza, ma non basta da
                // solo: si prosegue con i controlli restanti prima di
                // dichiarare la rimozione definitiva.
                continue;
            }

            if ($originalContentHash !== false) {
                $currentHash = @hash_file('sha256', $path);

                if ($currentHash !== false && $currentHash !== $originalContentHash) {
                    // Non è il nostro file risorto: un contenuto diverso
                    // è comparso allo stesso path (es. un'altra richiesta
                    // concorrente che ha scritto legittimamente un
                    // proprio file con lo stesso nome). Il nostro compito
                    // — rimuovere il file che avevamo scritto noi — resta
                    // comunque concluso: nessun controllo ulteriore serve,
                    // e questo file non va mai ri-cancellato.
                    $onDifferentContentReappeared?->__invoke($path, $check);

                    return true;
                }
            }

            // Lo stesso file (o un file di contenuto ignoto, quando
            // $originalContentHash non era disponibile) è di nuovo
            // presente: la rimozione non è confermata, nessun bisogno di
            // proseguire con altri controlli per QUESTO tentativo — il
            // ciclo di retry del chiamante ne farà un altro.
            return false;
        }

        return true;
    }
}
