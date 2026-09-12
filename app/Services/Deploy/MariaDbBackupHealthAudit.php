<?php

namespace App\Services\Deploy;

use Illuminate\Support\Facades\DB;

/**
 * Cantiere 19 (programma 100-cantieri Kairus). `backup:database-v2`
 * (`App\Services\Backup\MariaDbBackupService`) è manuale/opt-in: nessuno
 * scheduler lo esegue automaticamente (vedi `routes/console.php`, che
 * pianifica il vecchio `backup:database` SOLO per `database.default ===
 * 'sqlite'`, e `docs/DEPLOYMENT.md`, che qualifica come "decisione
 * ingegneristica distinta e deliberatamente vagliata" l'idea di
 * pianificare Backup V2 nella pipeline di deploy). Nulla, però, verifica
 * OGGI che un backup valido esista davvero o quanto sia vecchio: un
 * operatore che dimentica di eseguirlo manualmente non ha alcun segnale.
 *
 * Questo servizio è di sola lettura e non crea, pianifica né elimina mai
 * alcun backup: legge soltanto le coppie artefatto+metadata già presenti
 * nella directory configurata (`backup.v2.directory`) e riporta se ne
 * esiste almeno una valida per l'identità database CORRENTE (stesso
 * controllo di integrità sha256/size di
 * `MariaDbBackupService::isKnownGoodPair()`, stesso calcolo di
 * `identityHash` di `MariaDbBackupService::create()` — finding Codex P1,
 * PR #567: un confronto con wildcard su tutti gli identityHash presenti
 * nella directory farebbe riportare "ok" indefinitamente un vecchio
 * backup di un database DIVERSO, se produzione cambia nome database/host
 * mantenendo la stessa directory persistente) e, quando
 * `backup.v2.max_age_hours` è configurato, se la più recente supera la
 * soglia di età. Come la retention (`DB_BACKUP_RETENTION`), la soglia di
 * età è deliberatamente opt-in: nessun default di repository presume una
 * cadenza operativa che qui non può essere nota.
 */
class MariaDbBackupHealthAudit
{
    /**
     * @return array{
     *     applicable: bool,
     *     ok: bool,
     *     directory: string,
     *     latest: array{path: string, created_at_utc: string, age_hours: float}|null,
     *     max_age_hours: int|null,
     *     max_age_invalid: bool,
     *     stale: bool,
     * }
     */
    public function report(): array
    {
        $connection = (string) config('database.default');
        [$maxAgeHours, $maxAgeInvalid] = $this->maxAgeHours();

        if (! in_array($connection, ['mysql', 'mariadb'], true)) {
            // Backup V2 supporta solo mysql/mariadb: su altre connessioni
            // (es. sqlite in locale/test) la verifica non è applicabile,
            // non è un rischio da segnalare.
            return [
                'applicable' => false,
                'ok' => true,
                'directory' => (string) config('backup.v2.directory'),
                'latest' => null,
                'max_age_hours' => $maxAgeHours,
                'max_age_invalid' => $maxAgeInvalid,
                'stale' => false,
            ];
        }

        $directory = (string) config('backup.v2.directory');
        $identityHash = $this->currentIdentityHash($connection);

        // Finding Codex (P2, PR #567): senza retention configurata i dump
        // si accumulano senza limite, e hash_file() su ognuno durante ogni
        // `deploy.sh` sincrono leggerebbe l'intera storia dei backup. Si
        // ordinano prima solo i metadata (letture piccole, nessun hash) dal
        // più recente al più vecchio, e si convalida (hash) un candidato
        // alla volta finché non se ne trova uno valido — nel caso comune
        // un solo hash_file(), mai l'intera directory.
        $latest = $identityHash === null ? null : $this->latestValidBackup($directory, $identityHash);

        if ($latest === null) {
            return [
                'applicable' => true,
                'ok' => false,
                'directory' => $directory,
                'latest' => null,
                'max_age_hours' => $maxAgeHours,
                'max_age_invalid' => $maxAgeInvalid,
                'stale' => false,
            ];
        }

        // Finding Codex (P2, PR #567): un valore configurato ma malformato
        // (es. "-3", "abc") veniva prima silenziosamente trattato come "non
        // configurato", disabilitando il controllo di staleness invece di
        // segnalare l'errore di configurazione — a differenza della
        // retention analoga (MariaDbBackupService::retentionLimit()), che
        // rifiuta esplicitamente un valore non valido.
        $stale = ! $maxAgeInvalid && $maxAgeHours !== null && $latest['age_hours'] > $maxAgeHours;

        return [
            'applicable' => true,
            'ok' => ! $stale && ! $maxAgeInvalid,
            'directory' => $directory,
            'latest' => $latest,
            'max_age_hours' => $maxAgeHours,
            'max_age_invalid' => $maxAgeInvalid,
            'stale' => $stale,
        ];
    }

    /**
     * Stesso calcolo di `MariaDbBackupService::create()`: null quando la
     * configurazione della connessione non contiene i campi minimi
     * necessari (mai il caso in produzione, ma questo servizio è di sola
     * lettura e non deve mai presumere una configurazione valida).
     */
    private function currentIdentityHash(string $connection): ?string
    {
        $db = DB::connection($connection)->getConfig();

        if (! is_array($db)) {
            return null;
        }

        foreach (['database', 'username'] as $required) {
            if (! is_string($db[$required] ?? null) || trim($db[$required]) === '') {
                return null;
            }
        }

        $socket = trim((string) ($db['unix_socket'] ?? ''));

        if ($socket === '') {
            foreach (['host', 'port'] as $required) {
                if (! is_string($db[$required] ?? null) || trim($db[$required]) === '') {
                    return null;
                }
            }
        }

        $identity = $connection.'|'.($socket !== '' ? 'socket:'.$socket : ($db['host'].'|'.$db['port'])).'|'.$db['database'];

        return substr(hash('sha256', $identity), 0, 16);
    }

    /**
     * @return array{path: string, created_at_utc: string, age_hours: float}|null
     */
    private function latestValidBackup(string $directory, string $identityHash): ?array
    {
        if ($directory === '' || ! is_dir($directory)) {
            return null;
        }

        $metadataPaths = glob($directory.'/mariadb-'.$identityHash.'-*.sql.json') ?: [];
        $candidates = [];

        foreach ($metadataPaths as $metadataPath) {
            $artifact = substr($metadataPath, 0, -strlen('.json'));
            $decoded = json_decode((string) @file_get_contents($metadataPath), true);

            if (! is_array($decoded) || ! isset($decoded['created_at_utc']) || ! is_string($decoded['created_at_utc'])) {
                continue;
            }

            $candidates[] = ['artifact' => $artifact, 'created_at_utc' => $decoded['created_at_utc']];
        }

        usort($candidates, static fn (array $a, array $b): int => $b['created_at_utc'] <=> $a['created_at_utc']);

        foreach ($candidates as $candidate) {
            $metadata = $this->readValidMetadata($candidate['artifact']);

            if ($metadata !== null) {
                return [
                    'path' => $candidate['artifact'],
                    'created_at_utc' => $metadata['created_at_utc'],
                    'age_hours' => $this->ageInHours($metadata['created_at_utc']),
                ];
            }
        }

        return null;
    }

    /**
     * @return array{created_at_utc: string}|null
     */
    private function readValidMetadata(string $artifact): ?array
    {
        $metadataPath = $artifact.'.json';

        if (! is_file($artifact) || ! is_file($metadataPath)) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($metadataPath), true);

        if (! is_array($decoded)
            || ! isset($decoded['sha256'], $decoded['size_bytes'], $decoded['created_at_utc'])
            || ! is_string($decoded['sha256'])
            || ! is_int($decoded['size_bytes'])
            || ! is_string($decoded['created_at_utc'])
        ) {
            return null;
        }

        $size = @filesize($artifact);
        $hash = @hash_file('sha256', $artifact);

        if (! is_int($size) || $size < 1 || $size !== $decoded['size_bytes']) {
            return null;
        }

        if (! is_string($hash) || ! hash_equals($decoded['sha256'], $hash)) {
            return null;
        }

        return ['created_at_utc' => $decoded['created_at_utc']];
    }

    private function ageInHours(string $createdAtUtc): float
    {
        try {
            $createdAt = new \DateTimeImmutable($createdAtUtc);
        } catch (\Exception) {
            return PHP_FLOAT_MAX;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return max(0.0, ($now->getTimestamp() - $createdAt->getTimestamp()) / 3600);
    }

    /**
     * @return array{0: int|null, 1: bool} [valore analizzato o null, configurato-ma-non-valido]
     */
    private function maxAgeHours(): array
    {
        $configured = config('backup.v2.max_age_hours');

        if ($configured === null || $configured === '') {
            return [null, false];
        }

        if (! ctype_digit((string) $configured) || (int) $configured < 1) {
            return [null, true];
        }

        return [(int) $configured, false];
    }
}
