<?php

namespace App\Services\Editorial;

/**
 * Esito di un'esecuzione di EditorialCalendarLinkingService::apply() (o del
 * suo dry-run): la fotografia di riconciliazione usata come base, più i
 * collegamenti effettivamente applicati (vuoto in dry-run).
 */
final readonly class EditorialCalendarLinkingResult
{
    /** @param  list<EditorialCalendarLinkedEntry>  $linked */
    public function __construct(
        public EditorialCalendarReconciliationReport $report,
        public array $linked,
        public bool $dryRun,
    ) {}

    public function linkedCount(): int
    {
        return count($this->linked);
    }
}
