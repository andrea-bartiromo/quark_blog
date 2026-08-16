<?php

namespace App\Contracts;

interface DatabaseDumpRunner
{
    /**
     * Stream a logical database dump to $outputPath using $optionFile for credentials.
     * Implementations must not expose credential contents in argv, logs or exceptions.
     */
    public function dump(string $binary, string $optionFile, string $database, string $outputPath): void;
}
