<?php

namespace App\Services\Editorial;

use App\Models\Article;
use App\Models\Project;

/**
 * Fotografia completa, di sola lettura, dello stato di riconciliazione tra
 * il documento di calendario di un progetto e gli articoli reali del CMS —
 * l'unica fonte usata da comando di sync, comando di audit, dashboard e
 * risolutore della prossima azione: mai una seconda implementazione della
 * stessa logica.
 */
final readonly class EditorialCalendarReconciliationReport
{
    /**
     * @param  list<EditorialCalendarReconciliationEntry>  $entries  Una per ogni voce del calendario, nell'ordine del documento.
     * @param  list<EditorialCalendarParseError>  $parseErrors  Righe del documento non interpretabili in sicurezza.
     * @param  list<Article>  $articlesOutsidePlan  Articoli collegati al progetto che non corrispondono (più) a nessuna voce del calendario.
     */
    public function __construct(
        public Project $project,
        public ?int $documentId,
        public array $entries,
        public array $parseErrors,
        public array $articlesOutsidePlan,
    ) {}

    public function totalEntries(): int
    {
        return count($this->entries);
    }

    /** @return list<EditorialCalendarReconciliationEntry> */
    public function safeToAutoLink(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn (EditorialCalendarReconciliationEntry $e) => $e->match->isSafeToAutoLink() && ! $e->match->alreadyLinkedToProject
        ));
    }

    /** @return list<EditorialCalendarReconciliationEntry> */
    public function alreadyLinked(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn (EditorialCalendarReconciliationEntry $e) => $e->match->alreadyLinkedToProject
        ));
    }

    /** @return list<EditorialCalendarReconciliationEntry> */
    public function missingArticles(): array
    {
        return $this->withDiscrepancy(EditorialCalendarReconciliationEntry::DISCREPANCY_MISSING_ARTICLE);
    }

    /** @return list<EditorialCalendarReconciliationEntry> */
    public function requiringReview(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn (EditorialCalendarReconciliationEntry $e) => in_array($e->discrepancyType, [
                EditorialCalendarReconciliationEntry::DISCREPANCY_REQUIRES_REVIEW,
                EditorialCalendarReconciliationEntry::DISCREPANCY_TITLE_MAJOR_CHANGE,
            ], true)
        ));
    }

    /** @return list<EditorialCalendarReconciliationEntry> */
    public function dateDiscrepancies(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn (EditorialCalendarReconciliationEntry $e) => in_array($e->discrepancyType, [
                EditorialCalendarReconciliationEntry::DISCREPANCY_DATE_EARLY,
                EditorialCalendarReconciliationEntry::DISCREPANCY_DATE_LATE,
            ], true)
        ));
    }

    /** @return list<EditorialCalendarReconciliationEntry> */
    public function titleDiscrepancies(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn (EditorialCalendarReconciliationEntry $e) => in_array($e->discrepancyType, [
                EditorialCalendarReconciliationEntry::DISCREPANCY_TITLE_MINOR_CHANGE,
                EditorialCalendarReconciliationEntry::DISCREPANCY_TITLE_MAJOR_CHANGE,
            ], true)
        ));
    }

    /** @return list<EditorialCalendarReconciliationEntry> */
    public function statusMismatches(): array
    {
        return $this->withDiscrepancy(EditorialCalendarReconciliationEntry::DISCREPANCY_STATUS_MISMATCH);
    }

    /** @return list<EditorialCalendarReconciliationEntry> */
    private function withDiscrepancy(string $type): array
    {
        return array_values(array_filter($this->entries, fn (EditorialCalendarReconciliationEntry $e) => $e->discrepancyType === $type));
    }
}
