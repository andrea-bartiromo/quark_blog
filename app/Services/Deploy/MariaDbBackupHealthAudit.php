<?php

namespace App\Services\Deploy;

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
 * esiste almeno una valida (stesso controllo di integrità sha256/size di
 * `MariaDbBackupService::isKnownGoodPair()`) e, quando
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
     *     stale: bool,
     * }
     */
    public function report(): array
    {
        $connection = (string) config('database.default');

        if (! in_array($connection, ['mysql', 'mariadb'], true)) {
            // Backup V2 supporta solo mysql/mariadb: su altre connessioni
            // (es. sqlite in locale/test) la verifica non è applicabile,
            // non è un rischio da segnalare.
            return [
                'applicable' => false,
                'ok' => true,
                'directory' => (string) config('backup.v2.directory'),
                'latest' => null,
                'max_age_hours' => $this->maxAgeHours(),
                'stale' => false,
            ];
        }

        $directory = (string) config('backup.v2.directory');
        $maxAgeHours = $this->maxAgeHours();
        $latest = $this->latestValidBackup($directory);

        if ($latest === null) {
            return [
                'applicable' => true,
                'ok' => false,
                'directory' => $directory,
                'latest' => null,
                'max_age_hours' => $maxAgeHours,
                'stale' => false,
            ];
        }

        $stale = $maxAgeHours !== null && $latest['age_hours'] > $maxAgeHours;

        return [
            'applicable' => true,
            'ok' => ! $stale,
            'directory' => $directory,
            'latest' => $latest,
            'max_age_hours' => $maxAgeHours,
            'stale' => $stale,
        ];
    }

    /**
     * @return array{path: string, created_at_utc: string, age_hours: float}|null
     */
    private function latestValidBackup(string $directory): ?array
    {
        if ($directory === '' || ! is_dir($directory)) {
            return null;
        }

        $candidates = glob($directory.'/mariadb-*.sql') ?: [];
        $best = null;

        foreach ($candidates as $artifact) {
            $metadata = $this->readValidMetadata($artifact);

            if ($metadata === null) {
                continue;
            }

            if ($best === null || $metadata['created_at_utc'] > $best['created_at_utc']) {
                $best = [
                    'path' => $artifact,
                    'created_at_utc' => $metadata['created_at_utc'],
                    'age_hours' => $this->ageInHours($metadata['created_at_utc']),
                ];
            }
        }

        return $best;
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

    private function maxAgeHours(): ?int
    {
        $configured = config('backup.v2.max_age_hours');

        if ($configured === null || $configured === '') {
            return null;
        }

        if (! ctype_digit((string) $configured) || (int) $configured < 1) {
            return null;
        }

        return (int) $configured;
    }
}
