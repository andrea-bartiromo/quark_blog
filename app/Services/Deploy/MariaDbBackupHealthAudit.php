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
 *
 * Cantiere 72 (programma "100 cantieri Kairus"): quando
 * `backup.v2.retention` è configurato, riporta anche se il numero di
 * coppie valide su disco (per mode, 'periodic'/'pre-migration') supera
 * quel limite — `MariaDbBackupService::applyRetention()` tratta un
 * fallimento di pulizia come un warning "mai bloccante"
 * (docs/BACKUP_V2_OPERATIONS.md, "Failure semantics"), quindi senza
 * questo segnale un fallimento ripetuto resterebbe invisibile a chiunque
 * non legga i log di ogni singola esecuzione. Resta di sola lettura: non
 * elimina mai nulla, si limita a rendere osservabile un rischio già
 * documentato ma finora non verificabile.
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
     *     retention_configured: int|null,
     *     retention_invalid: bool,
     *     pair_counts_by_mode: array<string, int>,
     *     retention_exceeded_modes: list<string>,
     * }
     */
    public function report(): array
    {
        $connection = (string) config('database.default');
        [$maxAgeHours, $maxAgeInvalid] = $this->maxAgeHours();
        [$retentionConfigured, $retentionInvalid] = $this->retentionLimit();

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
                'retention_configured' => $retentionConfigured,
                'retention_invalid' => $retentionInvalid,
                'pair_counts_by_mode' => [],
                'retention_exceeded_modes' => [],
            ];
        }

        $directory = (string) config('backup.v2.directory');
        $identityHash = $this->currentIdentityHash($connection);

        // Cantiere 72 (programma "100 cantieri Kairus"): la retention
        // (MariaDbBackupService::applyRetention()) è "mai bloccante" per
        // design — un fallimento di pulizia resta un warning, mai un
        // errore che farebbe apparire fallito un backup locale in realtà
        // riuscito (vedi docs/BACKUP_V2_OPERATIONS.md, "Failure
        // semantics"). Questo però significa che un fallimento di pulizia
        // RIPETUTO oggi è invisibile a chiunque non legga i log di ogni
        // singola esecuzione: nessuna verifica di sola lettura segnalava,
        // finora, che il numero di coppie valide su disco ha superato il
        // limite configurato. La retention è applicata per-mode
        // (MariaDbBackupService::applyRetention(), glob scoped su
        // "-{mode}-"), quindi il conteggio deve restare per-mode: sommare
        // 'periodic' e 'pre-migration' insieme farebbe sembrare superato
        // un limite in realtà rispettato da entrambi i mode separatamente.
        //
        // Finding Codex (P1, PR #613): senza retention configurata (il
        // default di repository) o con una configurazione non valida,
        // retentionExceededModes resta comunque vuoto — validare ogni
        // coppia (hash+size, l'intera cronologia per questa identità)
        // sarebbe quindi un costo sincrono in ogni deploy.sh senza alcun
        // beneficio, esattamente il pattern che l'ottimizzazione
        // one-candidate-alla-volta di latestValidBackup() sotto evita già
        // per il controllo di staleness.
        $pairCountsByMode = ($identityHash !== null && $retentionConfigured !== null)
            ? $this->validPairCountsByMode($directory, $identityHash)
            : [];
        $retentionExceededModes = $retentionConfigured === null
            ? []
            : array_keys(array_filter($pairCountsByMode, fn (int $count): bool => $count > $retentionConfigured));

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
                'retention_configured' => $retentionConfigured,
                'retention_invalid' => $retentionInvalid,
                'pair_counts_by_mode' => $pairCountsByMode,
                'retention_exceeded_modes' => $retentionExceededModes,
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
            'ok' => ! $stale && ! $maxAgeInvalid && ! $retentionInvalid && $retentionExceededModes === [],
            'directory' => $directory,
            'latest' => $latest,
            'max_age_hours' => $maxAgeHours,
            'max_age_invalid' => $maxAgeInvalid,
            'stale' => $stale,
            'retention_configured' => $retentionConfigured,
            'retention_invalid' => $retentionInvalid,
            'pair_counts_by_mode' => $pairCountsByMode,
            'retention_exceeded_modes' => $retentionExceededModes,
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
     * Stessi due mode reali di MariaDbBackupService::create()
     * (`in_array($mode, ['periodic', 'pre-migration'], true)`).
     */
    private const KNOWN_MODES = ['periodic', 'pre-migration'];

    /**
     * Cantiere 72: conta le coppie artefatto+metadata VALIDE per questa
     * identità database, raggruppate per mode — a differenza di
     * latestValidBackup(), che si ferma al primo candidato valido, qui
     * ogni coppia deve essere validata (hash+size) per poterla contare
     * davvero, quindi tocca l'intera cronologia per questa identità. Dato
     * che la retention limita già il numero di coppie accumulate in
     * condizioni normali, il costo resta proporzionale a "quante ne sono
     * accumulate", non alla directory intera.
     *
     * Finding Codex (P2, PR #613): il mode viene derivato dal FILENAME
     * con lo stesso identico glob di
     * MariaDbBackupService::applyRetention() (`mariadb-{hash}-*-{mode}-*.sql`),
     * mai dal campo 'mode' dei metadata — i metadata sono mutabili/opzionali
     * e un valore divergente o assente (es. legacy, senza quel campo)
     * farebbe contare separatamente file che applyRetention() considera
     * invece parte dello STESSO gruppo, nascondendo un vero superamento
     * del limite dietro due conteggi entrambi sotto soglia.
     *
     * @return array<string, int>
     */
    private function validPairCountsByMode(string $directory, string $identityHash): array
    {
        if ($directory === '' || ! is_dir($directory)) {
            return [];
        }

        $counts = [];
        $countedPaths = [];

        foreach (self::KNOWN_MODES as $mode) {
            $artifacts = glob($directory.'/mariadb-'.$identityHash.'-*-'.$mode.'-*.sql') ?: [];

            foreach ($artifacts as $artifact) {
                if (isset($countedPaths[$artifact]) || $this->readValidMetadata($artifact) === null) {
                    continue;
                }

                $countedPaths[$artifact] = true;
                $counts[$mode] = ($counts[$mode] ?? 0) + 1;
            }
        }

        // Qualunque coppia valida per questa identità che non corrisponda
        // al glob di nessun mode noto non verrebbe mai selezionata da
        // NESSUNA chiamata di applyRetention() (sempre scoped su un mode
        // specifico) — resta comunque un file che si accumula senza
        // limite, quindi un segnale da non perdere, nel bucket 'unknown'.
        $allArtifacts = glob($directory.'/mariadb-'.$identityHash.'-*.sql') ?: [];

        foreach ($allArtifacts as $artifact) {
            if (isset($countedPaths[$artifact]) || $this->readValidMetadata($artifact) === null) {
                continue;
            }

            $counts['unknown'] = ($counts['unknown'] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Stesso valore di MariaDbBackupService::retentionLimit(), ma senza
     * mai lanciare un'eccezione: questo servizio è di sola lettura e un
     * valore malformato deve restare un segnale osservabile
     * (retention_invalid), mai un errore che interrompe la verifica.
     *
     * @return array{0: int|null, 1: bool} [valore analizzato o null, configurato-ma-non-valido]
     */
    private function retentionLimit(): array
    {
        $retention = config('backup.v2.retention');

        if ($retention === null || $retention === '') {
            return [null, false];
        }

        if (! ctype_digit((string) $retention) || (int) $retention < 1) {
            return [null, true];
        }

        return [(int) $retention, false];
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
