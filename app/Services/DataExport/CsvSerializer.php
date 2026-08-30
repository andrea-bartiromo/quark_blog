<?php

namespace App\Services\DataExport;

final class CsvSerializer
{
    /** @param list<string> $headers @param list<array<string, mixed>> $rows */
    public function serialize(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new \RuntimeException('Impossibile inizializzare lo stream CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $header) => $this->safeCell($row[$header] ?? null), $headers), ',', '"', '');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if ($csv === false) {
            throw new \RuntimeException('Impossibile leggere lo stream CSV.');
        }

        return $csv;
    }

    public function safeCell(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $value = $value === null ? '' : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);

        return preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'".$value : $value;
    }
}
