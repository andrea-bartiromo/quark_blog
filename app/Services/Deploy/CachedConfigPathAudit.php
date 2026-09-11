<?php

namespace App\Services\Deploy;

/**
 * Prompt 8 (programma 100-prompt Kairus, Fase P0 — affidabilità del
 * rilascio). `php artisan config:cache` risolve e congela nel file
 * `bootstrap/cache/config.php` ogni valore di config calcolato tramite
 * `storage_path()`/`base_path()`/`public_path()` (log, sessioni, cache
 * su file, disco locale — vedi config/logging.php, config/session.php,
 * config/cache.php, config/filesystems.php): valori RISOLTI una volta
 * sola, non più ricalcolati finché la cache resta in vigore.
 *
 * Con lo schema a directory di release separate + switch di symlink già
 * in uso in produzione, ogni deploy ha un percorso assoluto diverso da
 * quello precedente. `deploy.sh` rigenera sempre la cache da zero
 * (`optimize:clear` seguito da `config:cache`) nella directory corrente,
 * quindi questo scenario non dovrebbe mai verificarsi per costruzione —
 * ma un `bootstrap/cache/config.php` sopravvissuto da una release con un
 * percorso diverso (una copia/rsync che include per errore
 * bootstrap/cache, un simlink condiviso per sbaglio, un operatore che
 * salta il refresh cache) farebbe scrivere log/sessioni/cache nella
 * directory SBAGLIATA — spesso non più esistente — in modo silenzioso:
 * nessun errore immediato, solo dati persi o mai scritti.
 *
 * Questo servizio verifica il SINTOMO esatto: ogni valore di config
 * risolto tramite storage_path() nella configurazione GIÀ CARICATA da
 * questo processo (da cache o no) deve iniziare con lo storage_path()
 * REALE di QUESTO processo. Se non lo fa, la cache è stata scritta da
 * (o per) una directory diversa da questa.
 */
class CachedConfigPathAudit
{
    /**
     * Ogni chiave di config il cui valore, in questa applicazione, è
     * sempre calcolato tramite storage_path() — indipendentemente da
     * quale driver/canale sia effettivamente attivo in un dato
     * ambiente: un valore stantio su una qualunque di queste chiavi è
     * comunque la prova che la cache non riflette questa directory.
     *
     * @var list<string>
     */
    private const STORAGE_PATH_KEYS = [
        'logging.channels.single.path',
        'logging.channels.daily.path',
        'logging.channels.newsletter_reconfirmation_audit.path',
        'logging.channels.emergency.path',
        'session.files',
        'cache.stores.file.path',
        'cache.stores.file.lock_path',
        'filesystems.disks.local.root',
        'filesystems.disks.public.root',
    ];

    /**
     * @return array{
     *     ok: bool,
     *     expected_prefix: string,
     *     problems: list<array{key:string, value:string}>
     * }
     */
    public function report(): array
    {
        $expectedPrefix = rtrim(str_replace('\\', '/', storage_path()), '/');
        $problems = [];

        foreach (self::STORAGE_PATH_KEYS as $key) {
            $value = config($key);

            if (! is_string($value) || $value === '') {
                // Chiave non popolata in questo ambiente (es. un canale
                // di log mai configurato): nulla da verificare, non un
                // problema.
                continue;
            }

            $normalized = rtrim(str_replace('\\', '/', $value), '/');

            if (! str_starts_with($normalized, $expectedPrefix)) {
                $problems[] = ['key' => $key, 'value' => $value];
            }
        }

        return [
            'ok' => $problems === [],
            'expected_prefix' => $expectedPrefix,
            'problems' => $problems,
        ];
    }
}
