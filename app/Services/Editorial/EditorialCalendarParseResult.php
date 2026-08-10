<?php

namespace App\Services\Editorial;

/**
 * Esito completo di EditorialCalendarParser::parse(): le voci valide
 * estratte, nell'ordine del documento, più gli errori di parsing
 * incontrati — mai uno dei due al posto dell'altro.
 */
final readonly class EditorialCalendarParseResult
{
    /**
     * @param  list<EditorialCalendarEntry>  $entries
     * @param  list<EditorialCalendarParseError>  $errors
     */
    public function __construct(
        public array $entries,
        public array $errors,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }
}
