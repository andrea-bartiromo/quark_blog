<?php

namespace App\Services\Deploy;

/**
 * Cantiere 18 (programma 100-cantieri Kairus). Con lo schema "directory di
 * release separate + switch di symlink" già in uso in produzione (vedi
 * `App\Services\Deploy\CachedConfigPathAudit` e la sezione "Release
 * registry" di `docs/DEPLOYMENT.md`), OGNI percorso non esplicitamente
 * spostato fuori dalla directory di release viene silenziosamente perduto
 * al deploy successivo — la directory stessa smette di esistere una volta
 * che il symlink punta altrove.
 *
 * `docs/DEPLOYMENT.md` documenta già questo esatto rischio per il registro
 * rilasci ("DEPLOY_RELEASE_REGISTRY_PATH... deve puntare FUORI dalla
 * directory di release... o la entry viene perduta al rilascio successivo
 * proprio come DEPLOY_INFO"), ma lo stesso rischio si applica, senza che
 * nulla lo verifichi, al backup MariaDB (`DB_BACKUP_DIRECTORY`,
 * `config/backup.php`): a differenza del registro rilasci, il backup ha
 * SEMPRE un valore di default (`storage_path('backups/mariadb')`), che è
 * per costruzione dentro la directory di release corrente — un
 * `.env` di produzione che non lo sovrascrive esplicitamente perderebbe
 * ogni backup al deploy successivo, vanificando la retention a 7 copie
 * documentata in `docs/STORAGE_AUDIT.md`.
 *
 * Questo servizio è di sola informazione (mai bloccante di per sé — la
 * decisione se trattarlo come gate resta di chi invoca il comando, vedi
 * `deploy.sh`): segnala solo i percorsi configurati che risolvono
 * fisicamente dentro QUESTA directory di release, mai quelli lasciati
 * intenzionalmente disattivati (es. il registro rilasci quando
 * `DEPLOY_RELEASE_REGISTRY_PATH` non è impostato — nessun rischio da
 * segnalare per qualcosa che è già completamente no-op).
 */
class PersistentStoragePreflight
{
    /**
     * Ogni voce: etichetta leggibile, chiave di config da leggere, e la
     * variabile .env da impostare per spostare il percorso fuori dalla
     * directory di release.
     *
     * @var list<array{label: string, config_key: string, env_var: string}>
     */
    private const CHECKED_PATHS = [
        [
            'label' => 'Backup MariaDB/MySQL (Backup V2)',
            'config_key' => 'backup.v2.directory',
            'env_var' => 'DB_BACKUP_DIRECTORY',
        ],
        [
            'label' => 'Registro rilasci (Release Registry)',
            'config_key' => 'deploy.release_registry_path',
            'env_var' => 'DEPLOY_RELEASE_REGISTRY_PATH',
        ],
    ];

    /**
     * @return array{
     *     ok: bool,
     *     release_root: string,
     *     at_risk: list<array{label: string, env_var: string, path: string}>
     * }
     */
    public function report(): array
    {
        $releaseRoot = rtrim(str_replace('\\', '/', base_path()), '/');
        $atRisk = [];

        foreach (self::CHECKED_PATHS as $entry) {
            $value = config($entry['config_key']);

            if (! is_string($value) || $value === '') {
                // Non configurato: per il registro rilasci è lo stato
                // deliberatamente disattivato (nessun rischio, nessuna
                // persistenza attesa). Per il backup non può capitare —
                // ha sempre un default — ma lo stesso principio si
                // applica a qualunque futura voce senza valore.
                continue;
            }

            // Confine di directory reale (non solo un prefisso testuale
            // condiviso, es. "…/kairus_app-old"): stesso principio già
            // applicato da CachedConfigPathAudit.
            $normalized = rtrim(str_replace('\\', '/', $value), '/');
            $insideRelease = $normalized === $releaseRoot || str_starts_with($normalized, $releaseRoot.'/');

            if ($insideRelease) {
                $atRisk[] = [
                    'label' => $entry['label'],
                    'env_var' => $entry['env_var'],
                    'path' => $value,
                ];
            }
        }

        return [
            'ok' => $atRisk === [],
            'release_root' => $releaseRoot,
            'at_risk' => $atRisk,
        ];
    }
}
