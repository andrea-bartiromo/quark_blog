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
 *
 * Rispetta uno scollegamento manuale: se l'evento più recente in Cronologia
 * per un articolo è "scollegato" (unlink dalla tab Articoli del progetto —
 * l'unico punto dell'applicazione che chiama detach()), quell'articolo non
 * viene mai riproposto per il collegamento automatico, anche se il match
 * tornerebbe altrimenti sicuro. Senza questo controllo la sincronizzazione
 * schedulata (ogni 5 minuti, vedi routes/console.php) annullerebbe una
 * decisione umana esplicita entro pochi minuti.
 */
class EditorialCalendarLinkingService
{
    public function __construct(
        private readonly EditorialCalendarReconciliationService $reconciliationService,
    ) {}

    /**
     * Sola lettura: calcola cosa verrebbe collegato senza scrivere nulla —
     * stessa selezione di apply(), solo senza scrivere. È il comportamento
     * di default del comando di sync.
     */
    public function preview(Project $project): EditorialCalendarLinkingResult
    {
        $report = $this->reconciliationService->reconcile($project);

        $prospective = array_map(
            fn (EditorialCalendarReconciliationEntry $entry) => new EditorialCalendarLinkedEntry(
                $entry->match->entry,
                $entry->match->article,
                $entry->match->matchType,
            ),
            $this->eligibleForAutoLink($project, $report)
        );

        return new EditorialCalendarLinkingResult($report, $prospective, dryRun: true);
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

        foreach ($this->eligibleForAutoLink($project, $report) as $reconciliationEntry) {
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
                newValue: ProjectActivityLog::PROJECT_ARTICLE_LINKED,
                source: ProjectActivityLog::SOURCE_EDITORIAL_SYNC,
            );

            $linked[] = new EditorialCalendarLinkedEntry($match->entry, $article, $match->matchType);
        }

        return new EditorialCalendarLinkingResult($report, $linked, dryRun: false);
    }

    /**
     * @return list<EditorialCalendarReconciliationEntry>
     */
    private function eligibleForAutoLink(Project $project, EditorialCalendarReconciliationReport $report): array
    {
        return array_values(array_filter(
            $report->safeToAutoLink(),
            fn (EditorialCalendarReconciliationEntry $entry) => $entry->match->article !== null
                && ! $this->wasManuallyUnlinked($project, $entry->match->article->id)
        ));
    }

    private function wasManuallyUnlinked(Project $project, int $articleId): bool
    {
        $latest = ProjectActivityLog::query()
            ->where('project_id', $project->id)
            ->where('subject_type', 'project_article')
            ->where('subject_id', $articleId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $latest !== null && $latest->new_value === ProjectActivityLog::PROJECT_ARTICLE_UNLINKED;
    }
}
