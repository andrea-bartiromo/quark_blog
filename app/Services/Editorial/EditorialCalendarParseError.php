<?php

namespace App\Services\Editorial;

/**
 * Una riga del documento di calendario che INIZIA come una voce (un token
 * simile a una data) ma non può essere interpretata fino in fondo in
 * sicurezza — mai scartata in silenzio, mai un dato inventato per
 * completarla. Vedi EditorialCalendarParser per la distinzione rispetto
 * alle righe di prosa (ignorate senza errore).
 */
final readonly class EditorialCalendarParseError
{
    public function __construct(
        public int $lineNumber,
        public string $rawLine,
        public string $reason,
    ) {}
}
