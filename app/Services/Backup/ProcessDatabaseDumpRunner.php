<?php

namespace App\Services\Backup;

use App\Contracts\DatabaseDumpRunner;
use RuntimeException;
use Symfony\Component\Process\Process;

class ProcessDatabaseDumpRunner implements DatabaseDumpRunner
{
    public function dump(string $binary, string $optionFile, string $database, string $outputPath): void
    {
        $process = new Process([
            $binary,
            '--defaults-extra-file='.$optionFile,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--result-file='.$outputPath,
            $database,
        ]);

        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Database dump process failed with exit code '.($process->getExitCode() ?? 'unknown').'.');
        }
    }
}
