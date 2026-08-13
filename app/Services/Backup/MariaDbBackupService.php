<?php

namespace App\Services\Backup;

use App\Contracts\DatabaseDumpRunner;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class MariaDbBackupService
{
    public function __construct(private readonly DatabaseDumpRunner $runner) {}

    public function create(string $mode = 'periodic', ?string $releaseSha = null): array
    {
        if (! in_array($mode, ['periodic', 'pre-migration'], true)) {
            throw new RuntimeException('Unsupported backup mode.');
        }
        if ($mode === 'pre-migration' && ! preg_match('/^[0-9a-f]{40}$/i', (string) $releaseSha)) {
            throw new RuntimeException('Pre-migration backup requires an exact 40-character release SHA.');
        }
        $connection = (string) config('database.default');
        if (! in_array($connection, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Backup V2 supports only mysql or mariadb connections.');
        }
        $db = config("database.connections.{$connection}");
        if (! is_array($db)) {
            throw new RuntimeException('Database backup connection configuration is missing.');
        }
        foreach (['database', 'username'] as $required) {
            if (! is_string($db[$required] ?? null) || trim($db[$required]) === '') {
                throw new RuntimeException("Database backup configuration is missing {$required}.");
            }
        }
        $socket = trim((string) ($db['unix_socket'] ?? ''));
        if ($socket === '') {
            foreach (['host', 'port'] as $required) {
                if (! is_string($db[$required] ?? null) || trim($db[$required]) === '') {
                    throw new RuntimeException("Database backup configuration is missing {$required}.");
                }
            }
        }
        $retention = $this->retentionLimit();
        $lockSeconds = $this->lockSeconds();
        $lockStore = $this->lockStore();
        $binary = $this->resolveDumpBinary();
        $directory = (string) config('backup.v2.directory');
        $this->prepareDirectory($directory);
        $identity = $connection.'|'.($socket !== '' ? 'socket:'.$socket : ($db['host'].'|'.$db['port'])).'|'.$db['database'];
        $identityHash = substr(hash('sha256', $identity), 0, 16);
        $lock = Cache::store($lockStore)->lock('backup:v2:'.$identityHash, $lockSeconds);

        try {
            return $lock->block(1, fn () => $this->createLocked($binary, $directory, $db, $mode, $releaseSha, $retention, $identityHash));
        } catch (LockTimeoutException) {
            throw new RuntimeException('Another Backup V2 operation is already running for this database identity.');
        }
    }

    private function createLocked(string $binary, string $directory, array $db, string $mode, ?string $releaseSha, ?int $retention, string $identityHash): array
    {
        $token = bin2hex(random_bytes(8));
        $stamp = now('UTC')->format('Ymd\\THis\\Z');
        $shaPart = $releaseSha ? '-'.substr($releaseSha, 0, 12) : '';
        $base = "mariadb-{$identityHash}-{$stamp}-{$mode}{$shaPart}-{$token}";
        $temporary = $directory.'/.'.$base.'.sql.tmp';
        $final = $directory.'/'.$base.'.sql';
        $metadataPath = $final.'.json';
        $metadataTemporary = $metadataPath.'.tmp';
        $optionFile = tempnam(sys_get_temp_dir(), 'kairus-db-');
        $artifactPromoted = false;
        $metadataPublished = false;
        if ($optionFile === false) {
            throw new RuntimeException('Unable to create temporary database credential file.');
        }

        try {
            $this->writeOptionFile($optionFile, $db);
            try {
                $this->runner->dump($binary, $optionFile, (string) $db['database'], $temporary);
            } catch (Throwable $e) {
                throw new RuntimeException('Database dump process failed.', 0, $e);
            }
            $this->validateDump($temporary);
            $this->setPrivatePermissions($temporary, 'database backup temporary artifact');
            $this->promoteValidatedBackup($temporary, $final);
            $artifactPromoted = true;
            $this->setPrivatePermissions($final, 'database backup artifact');
            $metadata = [
                'created_at_utc' => now('UTC')->toIso8601String(),
                'engine' => (string) config('database.default'),
                'database_identity' => $identityHash,
                'mode' => $mode,
                'application_revision' => $this->applicationRevision($releaseSha),
                'sha256' => hash_file('sha256', $final),
                'size_bytes' => filesize($final),
                'validation' => 'basic-sql-validated',
            ];
            if (! is_string($metadata['sha256']) || ! is_int($metadata['size_bytes']) || $metadata['size_bytes'] < 1) {
                throw new RuntimeException('Unable to calculate published backup metadata.');
            }
            $encoded = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
            if (file_put_contents($metadataTemporary, $encoded, LOCK_EX) === false) {
                throw new RuntimeException('Unable to prepare backup metadata.');
            }
            $this->setPrivatePermissions($metadataTemporary, 'backup metadata temporary artifact');
            if (! @rename($metadataTemporary, $metadataPath)) {
                throw new RuntimeException('Unable to publish backup metadata atomically.');
            }
            $metadataPublished = true;
            $this->setPrivatePermissions($metadataPath, 'backup metadata');
            $warnings = $this->applyRetention($directory, $final, $identityHash, $mode, $retention);

            return ['artifact' => $final, 'metadata' => $metadataPath, 'warnings' => $warnings] + $metadata;
        } catch (Throwable $e) {
            @unlink($temporary);
            @unlink($metadataTemporary);
            if ($artifactPromoted && ! $metadataPublished) {
                @unlink($final);
            }
            if ($metadataPublished && ! is_file($final)) {
                @unlink($metadataPath);
            }
            throw $e;
        } finally {
            @unlink($optionFile);
            clearstatcache(true, $optionFile);
        }
    }

    private function writeOptionFile(string $path, array $db): void
    {
        if (! @chmod($path, 0600)) {
            throw new RuntimeException('Unable to secure temporary database credential file.');
        }
        $socket = trim((string) ($db['unix_socket'] ?? ''));
        $values = $socket !== ''
            ? ['socket' => $socket, 'user' => $db['username'], 'password' => (string) ($db['password'] ?? '')]
            : ['host' => $db['host'], 'port' => $db['port'], 'user' => $db['username'], 'password' => (string) ($db['password'] ?? '')];
        foreach ($values as $value) {
            if (str_contains((string) $value, "\n") || str_contains((string) $value, "\r")) {
                throw new RuntimeException('Database backup credential configuration contains invalid control characters.');
            }
        }
        $contents = "[client]\n";
        foreach ($values as $key => $value) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);
            $contents .= $key.'="'.$escaped."\"\n";
        }
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to prepare restrictive database credential file.');
        }
        $this->setPrivatePermissions($path, 'temporary database credential file');
    }

    private function validateDump(string $path): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('Database dump is missing or empty.');
        }
        $sample = file_get_contents($path, false, null, 0, 262144);
        $hasDumpMarker = is_string($sample) && preg_match('/(?:MariaDB dump|MySQL dump)/i', $sample) === 1;
        $hasCreateTable = is_string($sample) && preg_match('/CREATE\s+TABLE/i', $sample) === 1;
        if (! is_string($sample) || preg_match('/<(?:html|!doctype)/i', $sample) || ! $hasDumpMarker || ! $hasCreateTable) {
            throw new RuntimeException('Database dump failed basic SQL structure validation.');
        }
    }

    protected function prepareDirectory(string $directory): void
    {
        if ($directory === '') {
            throw new RuntimeException('Backup destination is not configured.');
        }
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Backup destination cannot be created.');
        }
        if (! is_writable($directory)) {
            throw new RuntimeException('Backup destination is not writable.');
        }
        if (PHP_OS_FAMILY !== 'Windows' && ! @chmod($directory, 0700)) {
            throw new RuntimeException('Backup destination permissions cannot be restricted.');
        }
    }

    protected function promoteValidatedBackup(string $temporary, string $final): void
    {
        if (! @rename($temporary, $final)) {
            throw new RuntimeException('Unable to atomically promote validated database backup.');
        }
    }

    protected function applyRetention(string $directory, string $current, string $identityHash, string $mode, ?int $retention): array
    {
        if ($retention === null) {
            return [];
        }
        $knownGood = array_values(array_filter(
            glob($directory.'/mariadb-'.$identityHash.'-*-'.$mode.'-*.sql') ?: [],
            fn (string $artifact): bool => $this->isKnownGoodPair($artifact)
        ));
        usort($knownGood, function (string $a, string $b): int {
            $mtime = filemtime($b) <=> filemtime($a);

            return $mtime !== 0 ? $mtime : strcmp(basename($b), basename($a));
        });
        $keep = array_slice($knownGood, 0, $retention);
        if (! in_array($current, $keep, true)) {
            array_unshift($keep, $current);
        }
        $warnings = [];
        foreach ($knownGood as $old) {
            if (in_array($old, $keep, true) || $old === $current) {
                continue;
            }
            if (! $this->deleteRetentionPair($old)) {
                $warnings[] = 'Backup retention cleanup could not remove one older validated backup pair.';
            }
        }

        return array_values(array_unique($warnings));
    }

    protected function deleteRetentionPair(string $artifact): bool
    {
        $metadata = $artifact.'.json';
        if (! @unlink($artifact)) {
            return false;
        }

        return ! is_file($metadata) || @unlink($metadata);
    }

    private function isKnownGoodPair(string $artifact): bool
    {
        $metadataPath = $artifact.'.json';
        if (! is_file($artifact) || ! is_file($metadataPath)) {
            return false;
        }
        $decoded = json_decode((string) @file_get_contents($metadataPath), true);
        if (! is_array($decoded) || ! isset($decoded['sha256'], $decoded['size_bytes'])) {
            return false;
        }
        $size = filesize($artifact);
        $hash = hash_file('sha256', $artifact);

        return is_int($size) && $size > 0
            && (int) $decoded['size_bytes'] === $size
            && is_string($hash)
            && hash_equals((string) $decoded['sha256'], $hash);
    }

    private function retentionLimit(): ?int
    {
        $retention = config('backup.v2.retention');
        if ($retention === null || $retention === '') {
            return null;
        }
        if (! ctype_digit((string) $retention) || (int) $retention < 1) {
            throw new RuntimeException('Backup V2 retention must be a positive integer when configured.');
        }

        return (int) $retention;
    }

    private function lockSeconds(): int
    {
        $seconds = config('backup.v2.lock_seconds');
        if (! is_int($seconds) && ! ctype_digit((string) $seconds)) {
            throw new RuntimeException('Backup V2 lock duration must be a positive integer.');
        }
        $seconds = (int) $seconds;
        if ($seconds < 1) {
            throw new RuntimeException('Backup V2 lock duration must be a positive integer.');
        }

        return $seconds;
    }

    private function lockStore(): string
    {
        $store = trim((string) config('backup.v2.lock_store'));
        $driver = config("cache.stores.{$store}.driver");
        if ($store === '' || ! is_string($driver) || in_array($driver, ['array', 'null'], true)) {
            throw new RuntimeException('Backup V2 requires a configured cross-process cache lock store.');
        }

        return $store;
    }

    private function resolveDumpBinary(): string
    {
        $configured = trim((string) config('backup.v2.binary'));
        if ($configured !== '') {
            if (! $this->binaryAvailable($configured)) {
                throw new RuntimeException('Configured database dump binary is not available.');
            }

            return $configured;
        }
        foreach (['mariadb-dump', 'mysqldump'] as $candidate) {
            if ($this->binaryAvailable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No compatible MariaDB/MySQL dump binary is available.');
    }

    private function binaryAvailable(string $binary): bool
    {
        if (str_contains($binary, DIRECTORY_SEPARATOR) || str_contains($binary, '/') || str_contains($binary, '\\')) {
            return is_file($binary) && (PHP_OS_FAMILY === 'Windows' || is_executable($binary));
        }
        $extensions = [''];
        if (PHP_OS_FAMILY === 'Windows') {
            $extensions = array_merge($extensions, array_filter(explode(';', (string) getenv('PATHEXT'))));
        }
        foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory) {
            foreach ($extensions as $extension) {
                $path = $directory.DIRECTORY_SEPARATOR.$binary.$extension;
                if (is_file($path) && (PHP_OS_FAMILY === 'Windows' || is_executable($path))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function setPrivatePermissions(string $path, string $label): void
    {
        if (! @chmod($path, 0600)) {
            throw new RuntimeException("Unable to restrict {$label} permissions.");
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            clearstatcache(true, $path);
            $mode = fileperms($path);
            if (! is_int($mode) || ($mode & 0777) !== 0600) {
                throw new RuntimeException("Unable to verify {$label} permissions.");
            }
        }
    }

    private function applicationRevision(?string $releaseSha): ?string
    {
        if (preg_match('/^[0-9a-f]{40}$/i', (string) $releaseSha)) {
            return $releaseSha;
        }
        $revisionFile = (string) config('backup.v2.revision_file');
        if ($revisionFile !== '' && is_file($revisionFile)) {
            $revision = trim((string) file_get_contents($revisionFile));
            if (preg_match('/^[0-9a-f]{40}$/i', $revision)) {
                return $revision;
            }
        }

        return null;
    }
}
