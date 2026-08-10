<?php

namespace App\Services\Editorial;

use App\Models\Project;
use App\Models\ProjectActivityLog;

/**
 * Applica in scrittura SOLO i collegamenti che EditorialCalendarMatch
 * considera sicuri (EXACT o NORMALIZED, candidato unico, non già
 * collegato) — mai un match AMBIGUOUS, mai una voce senza articolo. Ogni
 * altra decisione (titoli riscritti, più candidati, voci senza articolo,
 * discrepanze di data/stato) resta un suggerimento da revisionare a mano,
 * mai applicata qui (vedi EditorialCalendarReconciliationService).
 *
 * Idempotente per costruzione: una voce già collegata non è mai in
 * report->safeToAutoLink() (vedi
 * EditorialCalendarMatch::alreadyLinkedToProject), quindi rieseguire questo
 * servizio più volte di fila non produce mai collegamenti duplicati né
 * azioni di Cronologia ripetute. Mai uno scollegamento: questo servizio non
 * chiama mai detach().
 */
class EditorialCalendarLinkingService
{
    public function __construct(
        private readonly EditorialCalendarReconciliationService $reconciliationService,
    ) {}

    /**
     * Sola lettura: calcola cosa verrebbe collegato senza scrivere nulla.
     * È il comportamento di default del comando di sync.
     */
    public function preview(Project $project): EditorialCalendarLinkingResult
    {
        $report = $this->reconciliationService->reconcile($project);

        return new EditorialCalendarLinkingResult($report, [], dryRun: true);
    }

    /**
     * Applica i collegamenti sicuri. $userId è sempre esplicito e sempre
     * null qui: l'origine è sempre "Sync calendario"
     * (ProjectActivityLog::SOURCE_EDITORIAL_SYNC), mai un utente specifico,
     * perché l'azione non è mai avviata da un click umano su questa voce.
     */
    public function apply(Project $project): EditorialCalendarLinkingResult
    {
        $report = $this->reconciliationService->reconcile($project);

        $linked = [];

        foreach ($report->safeToAutoLink() as $reconciliationEntry) {
            $match = $reconciliationEntry->match;
            $article = $match->article;

            if ($article === null) {
                // Non dovrebbe mai accadere (isSafeToAutoLink() lo esclude
                // già), ma niente scritture su un presupposto non garantito.
                continue;
            }

            $project->articles()->attach($article->id, ['created_by' => null]);

            ProjectActivityLog::record(
                project: $project,
                subjectType: 'project_article',
                subjectId: $article->id,
                subjectTitle: $article->title,
                action: "Articolo collegato automaticamente dalla sincronizzazione del calendario editoriale (voce #{$match->entry->position}): «{$article->title}»",
                userId: null,
                source: ProjectActivityLog::SOURCE_EDITORIAL_SYNC,
            );

            $linked[] = new EditorialCalendarLinkedEntry($match->entry, $article, $match->matchType);
        }

        return new EditorialCalendarLinkingResult($report, $linked, dryRun: false);
    }
}
