<?php

namespace App\Services\Editorial;

use Carbon\Carbon;

/**
 * Una singola voce validata del calendario editoriale, così come estratta
 * da EditorialCalendarParser::parse() — mai costruita altrove: il parser è
 * l'unico punto che decide se una riga è una voce di calendario valida.
 *
 * Immutabile per costruzione: rappresenta un fatto già accertato sul
 * documento al momento del parsing, non uno stato che possa cambiare.
 */
final readonly class EditorialCalendarEntry
{
    public function __construct(
        /** Posizione 1-based tra le sole voci valide, nell'ordine in cui compaiono nel documento. */
        public int $position,
        public Carbon $date,
        public string $title,
        public ?string $filone,
        /** Stato dichiarato nel documento (es. "pubblicato", "bozza"), testo verbatim — mai dedotto. */
        public ?string $status,
        /** Titolo della sezione/intestazione Markdown sotto cui compare la voce, se presente. */
        public ?string $section,
        public int $lineNumber,
        public string $rawLine,
    ) {}
}
