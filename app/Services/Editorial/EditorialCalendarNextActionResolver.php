<?php

namespace App\Services\Editorial;

use App\Models\Article;
use App\Models\Project;

/**
 * Suggerimento "prossima azione" (Blocco E, form progetto) per progetti
 * editoriali con un calendario collegato — concreto e non ripetitivo:
 * nomina sempre la voce specifica del calendario coinvolta, mai un
 * messaggio generico identico indipendentemente dallo stato reale.
 *
 * Fallback esplicito per ogni progetto SENZA un documento di calendario
 * marcato (compresi i progetti non editoriali): la stessa regola
 * esistente Project::suggestedNextAction() basata sulle task, non
 * toccata — nessuna regressione sul comportamento già testato lì.
 *
 * Priorità (dalla decisione più urgente/bloccante alla meno):
 *   1. voci che richiedono revisione umana (match ambiguo — mai
 *      risolvibile in automatico, quindi è sempre la priorità più alta);
 *   2. il prossimo articolo pianificato ancora senza un articolo nel CMS,
 *      per data più vicina;
 *   3. la prima discrepanza di data (pubblicato/programmato fuori dalla
 *      data pianificata);
 *   4. la prima discrepanza di stato dichiarato;
 *   5. se tutto il calendario è riconciliato, il suggerimento basato su
 *      task del progetto (stessa regola del fallback).
 */
class EditorialCalendarNextActionResolver
{
    public function __construct(
        private readonly EditorialCalendarReconciliationService $reconciliationService,
    ) {}

    public function resolve(Project $project): ?string
    {
        if ($project->editorialCalendarDocument() === null) {
            return $project->suggestedNextAction();
        }

        $report = $this->reconciliationService->reconcile($project);

        if ($requiresReview = $this->earliest($report->requiringReview())) {
            $candidateCount = count($requiresReview->match->candidates);

            return "Verificare manualmente la voce #{$requiresReview->entry()->position} del calendario: «{$requiresReview->entry()->title}» ({$candidateCount} articoli candidati con titolo simile)";
        }

        if ($missing = $this->earliest($report->missingArticles())) {
            return "Prossimo articolo da scrivere: «{$missing->entry()->title}» previsto per il {$missing->entry()->date->format('d/m/Y')}";
        }

        if ($dateDiscrepancy = $this->earliest($report->dateDiscrepancies())) {
            $direction = $dateDiscrepancy->discrepancyType === EditorialCalendarReconciliationEntry::DISCREPANCY_DATE_EARLY
                ? 'in anticipo'
                : 'in ritardo';

            return "Verificare la data: «{$dateDiscrepancy->entry()->title}» risulta {$direction} rispetto al calendario (pianificato per il {$dateDiscrepancy->entry()->date->format('d/m/Y')})";
        }

        if ($statusMismatch = $this->earliest($report->statusMismatches())) {
            $realStatusLabel = Article::statusOptions()[$statusMismatch->match->article->status] ?? $statusMismatch->match->article->status;

            return "Verificare lo stato di «{$statusMismatch->entry()->title}»: il calendario lo dichiara «{$statusMismatch->entry()->status}» ma nel CMS risulta «{$realStatusLabel}»";
        }

        return $project->suggestedNextAction();
    }

    /**
     * @param  list<EditorialCalendarReconciliationEntry>  $entries
     */
    private function earliest(array $entries): ?EditorialCalendarReconciliationEntry
    {
        if ($entries === []) {
            return null;
        }

        usort($entries, fn (EditorialCalendarReconciliationEntry $a, EditorialCalendarReconciliationEntry $b) => $a->entry()->date <=> $b->entry()->date);

        return $entries[0];
    }
}
