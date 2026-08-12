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
        foreach (['host', 'port', 'database', 'username'] as $required) {
            if (! is_string($db[$required] ?? null) || trim($db[$required]) === '') {
                throw new RuntimeException("Database backup configuration is missing {$required}.");
            }
        }

        $binary = (string) config('backup.v2.binary');
        if ($binary === '' || (! str_contains($binary, DIRECTORY_SEPARATOR) && ! $this->commandExists($binary))) {
            throw new RuntimeException('Configured database dump binary is not available.');
        }
        if (str_contains($binary, DIRECTORY_SEPARATOR) && ! is_executable($binary)) {
            throw new RuntimeException('Configured database dump binary is not executable.');
        }

        $directory = (string) config('backup.v2.directory');
        $this->prepareDirectory($directory);

        $lock = Cache::lock('backup:v2:'.sha1($connection.'|'.$db['host'].'|'.$db['port'].'|'.$db['database']), (int) config('backup.v2.lock_seconds', 900));

        try {
            return $lock->block(1, fn () => $this->createLocked($binary, $directory, $db, $mode, $releaseSha));
        } catch (LockTimeoutException) {
            throw new RuntimeException('Another Backup V2 operation is already running.');
        }
    }

    private function createLocked(string $binary, string $directory, array $db, string $mode, ?string $releaseSha): array
    {
        $token = bin2hex(random_bytes(8));
        $stamp = now('UTC')->format('Ymd\\THis\\Z');
        $shaPart = $releaseSha ? '-'.substr($releaseSha, 0, 12) : '';
        $base = "mariadb-{$stamp}-{$mode}{$shaPart}-{$token}";
        $temporary = $directory.'/.'.$base.'.sql.tmp';
        $final = $directory.'/'.$base.'.sql';
        $metadataPath = $final.'.json';
        $optionFile = tempnam(sys_get_temp_dir(), 'kairus-db-');

        if ($optionFile === false) {
            throw new RuntimeException('Unable to create temporary database credential file.');
        }

        try {
            $this->writeOptionFile($optionFile, $db);
            $this->runner->dump($binary, $optionFile, (string) $db['database'], $temporary);
            $this->validateDump($temporary);

            @chmod($temporary, 0600);
            if (! @rename($temporary, $final)) {
                throw new RuntimeException('Unable to atomically promote validated database backup.');
            }

            $metadata = [
                'created_at_utc' => now('UTC')->toIso8601String(),
                'engine' => (string) config('database.default'),
                'mode' => $mode,
                'application_revision' => $releaseSha,
                'sha256' => hash_file('sha256', $final),
                'size_bytes' => filesize($final),
                'validation' => 'basic-sql-validated',
            ];

            $encoded = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
            if (file_put_contents($metadataPath.'.tmp', $encoded, LOCK_EX) === false || ! @rename($metadataPath.'.tmp', $metadataPath)) {
                @unlink($final);
                @unlink($metadataPath.'.tmp');
                throw new RuntimeException('Unable to publish backup metadata atomically.');
            }
            @chmod($metadataPath, 0600);

            $this->applyRetention($directory);

            return ['artifact' => $final, 'metadata' => $metadataPath] + $metadata;
        } catch (Throwable $e) {
            @unlink($temporary);
            @unlink($metadataPath.'.tmp');
            throw $e;
        } finally {
            @unlink($optionFile);
        }
    }

    private function writeOptionFile(string $path, array $db): void
    {
        $values = [
            'host' => $db['host'],
            'port' => $db['port'],
            'user' => $db['username'],
            'password' => (string) ($db['password'] ?? ''),
        ];

        foreach ($values as $value) {
            if (str_contains((string) $value, "\n") || str_contains((string) $value, "\r")) {
                throw new RuntimeException('Database backup credential configuration contains invalid control characters.');
            }
        }

        $contents = "[client]\n";
        foreach ($values as $key => $value) {
            $escaped = addcslashes((string) $value, "\\\"");
            $contents .= $key.'="'.$escaped."\"\n";
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false || ! @chmod($path, 0600)) {
            throw new RuntimeException('Unable to prepare restrictive database credential file.');
        }
    }

    private function validateDump(string $path): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('Database dump is missing or empty.');
        }

        $sample = file_get_contents($path, false, null, 0, 65536);
        if (! is_string($sample) || preg_match('/<(?:html|!doctype)/i', $sample) || ! preg_match('/(?:CREATE TABLE|INSERT INTO|MariaDB dump|MySQL dump)/i', $sample)) {
            throw new RuntimeException('Database dump failed basic SQL structure validation.');
        }
    }

    private function prepareDirectory(string $directory): void
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
    }

    private function applyRetention(string $directory): void
    {
        $retention = config('backup.v2.retention');
        if ($retention === null || $retention === '') {
            return;
        }
        if (! ctype_digit((string) $retention) || (int) $retention < 1) {
            throw new RuntimeException('Backup V2 retention must be a positive integer when configured.');
        }

        $files = glob($directory.'/mariadb-*.sql') ?: [];
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, (int) $retention) as $old) {
            if (! @unlink($old)) {
                throw new RuntimeException('Backup retention cleanup failed after successful publication.');
            }
            @unlink($old.'.json');
        }
    }

    private function commandExists(string $binary): bool
    {
        $path = getenv('PATH') ?: '';
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if ($directory !== '' && is_executable($directory.DIRECTORY_SEPARATOR.$binary)) {
                return true;
            }
        }

        return false;
    }
}
