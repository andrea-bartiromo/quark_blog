<?php

namespace App\Support;

/**
 * Cache busting per gli asset pubblici statici (CSS/JS in public/).
 *
 * Produzione Kairus serve i file statici da due document root fisicamente
 * separate: l'albero applicativo (~/kairus_app, dove vive questo
 * repository e da cui public_path() legge) e l'albero effettivamente
 * servito da Apache (~/public_html). Nulla in questo repository sincronizza
 * automaticamente i file statici versionati (CSS/JS) tra le due — a
 * differenza della Libreria media, per cui esiste già
 * PublicMediaSyncService — quindi le due copie possono divergere in
 * contenuto E in mtime. Un incidente reale (public-premium.css, notte del
 * 24/08) lo ha dimostrato: la versione calcolata da filemtime(public_path())
 * rifletteva una copia stale in kairus_app/public, diversa da quella
 * realmente servita da public_html — il browser continuava a cachare la
 * versione vecchia perché la querystring ?v= non cambiava mai.
 *
 * Fix: quando esiste un file REVISION nella radice applicativa (scritto da
 * deploy.sh SOLO dopo un deploy riuscito e verificato, contenente lo SHA
 * Git a 40 caratteri esatto della release), la versione usata è quello SHA,
 * non il mtime di un file. Questo elimina la classe di bug alla radice: lo
 * SHA di release è identico indipendentemente da QUALE albero lo legge, e
 * cambia ad ogni deploy — un browser non può mai continuare a servire una
 * versione precedente della querystring dopo un nuovo deploy, anche se le
 * due document root non sono più perfettamente sincronizzate in mtime.
 * (Non garantisce da solo che public_html riceva davvero il file nuovo —
 * quello resta un problema di sincronizzazione del contenuto, coperto
 * separatamente da scripts/selective-deploy-backup.sh e da un futuro
 * drift-detector; questa classe risolve solo "il browser deve richiedere
 * di nuovo l'asset ad ogni release", non "l'asset richiesto è per forza
 * quello giusto".)
 *
 * In assenza di REVISION (sviluppo locale, test, CI, o una release che non
 * ha ancora completato deploy.sh con successo) si ricade sul comportamento
 * originale basato su filemtime(public_path()) — invariato, nessun
 * comportamento locale/di test cambia.
 */
class VersionedAsset
{
    /**
     * Un solo token su una riga: lo stesso formato che deploy.sh scrive
     * (`printf '%s\n' "$ACTUAL_SHA" > REVISION`, validato a monte come SHA
     * Git a 40 caratteri esadecimali). Non si richiede qui esattamente 40
     * caratteri esadecimali per restare tollerante a un identificativo di
     * release diverso in futuro — solo un singolo token stampabile, senza
     * spazi o newline interni, così da non poter mai rompere la querystring
     * generata.
     */
    private const REVISION_PATTERN = '/^[!-~]+$/';

    /**
     * @param  string  $relativePath  Percorso relativo a public/, es. "css/style.css".
     */
    public static function url(string $relativePath): string
    {
        return asset($relativePath).'?v='.(self::releaseRevision() ?? self::mtimeVersion($relativePath));
    }

    /**
     * @return int|string
     */
    private static function mtimeVersion(string $relativePath)
    {
        $absolutePath = public_path($relativePath);

        return is_file($absolutePath) ? filemtime($absolutePath) : 1;
    }

    /**
     * Deliberatamente senza cache statica a livello di processo: sotto
     * PHP-FPM/mod_php classico (nessun runtime Octane/Swoole in questo
     * repository — verificato in composer.json) una variabile statica non
     * sopravviverebbe comunque tra due richieste HTTP separate, ma
     * sopravviverebbe INVECE tra due test PHPUnit eseguiti nello stesso
     * processo, facendo trapelare lo stato di un test in un altro. Il
     * costo di rileggere un file di poche decine di byte 3-10 volte per
     * pagina è trascurabile rispetto al rischio di un cache statico
     * scorretto.
     */
    private static function releaseRevision(): ?string
    {
        $path = base_path('REVISION');

        if (! is_file($path)) {
            return null;
        }

        $contents = trim((string) @file_get_contents($path));

        if ($contents === '' || preg_match(self::REVISION_PATTERN, $contents) !== 1) {
            return null;
        }

        return $contents;
    }
}
