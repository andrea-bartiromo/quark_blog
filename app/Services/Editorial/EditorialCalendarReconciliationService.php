<?php

namespace App\Services\Editorial;

use App\Models\Article;
use App\Models\Project;

/**
 * Motore condiviso di riconciliazione (FASE 2+3+4 della missione
 * automazione Piano Editoriale): dato un progetto con un documento
 * calendario marcato (Project::editorialCalendarDocument()), produce la
 * fotografia completa usata da comando di sync, comando di audit,
 * dashboard e prossima azione — un'unica implementazione, mai una
 * seconda logica che potrebbe divergere silenziosamente.
 *
 * Sola lettura: nessuna scrittura su DB, mai. Chi vuole APPLICARE i match
 * sicuri usa EditorialCalendarLinkingService con il risultato di
 * reconcile(), separatamente.
 */
class EditorialCalendarReconciliationService
{
    /**
     * Parole chiave riconosciute con certezza nello stato dichiarato del
     * calendario (testo libero, mai validato a monte dal parser — vedi
     * EditorialCalendarParser). Un testo che non contiene nessuna di
     * queste parole non produce mai uno STATUS_MISMATCH: è preferibile
     * non segnalare nulla piuttosto che interpretare in modo scorretto
     * un testo scritto in un modo non previsto.
     */
    private const STATUS_KEYWORDS = [
        Article::STATUS_PUBLISHED => ['pubblicat'],
        Article::STATUS_SCHEDULED => ['programmat', 'schedulat', 'pianificat'],
        Article::STATUS_DRAFT => ['bozza', 'da scrivere', 'da fare'],
        Article::STATUS_REVIEW => ['revision', 'da revisionare', 'in verifica'],
    ];

    public function __construct(
        private readonly EditorialCalendarParser $parser,
        private readonly EditorialCalendarMatchingService $matcher,
    ) {}

    public function reconcile(Project $project): EditorialCalendarReconciliationReport
    {
        $document = $project->editorialCalendarDocument();

        if ($document === null) {
            return new EditorialCalendarReconciliationReport($project, null, [], [], []);
        }

        $parseResult = $this->parser->parse($document->content ?? '');

        $articlePool = Article::all(['id', 'title', 'status', 'published_at']);
        $linkedArticles = $project->articles()->get(['articles.id']);
        $linkedArticleIds = $linkedArticles->pluck('id');

        $matches = $this->matcher->matchAll($parseResult->entries, $articlePool, $linkedArticleIds);

        $entries = array_map(
            fn (EditorialCalendarMatch $match) => new EditorialCalendarReconciliationEntry(
                $match,
                $this->classifyDiscrepancy($match)
            ),
            $matches
        );

        $matchedArticleIds = collect($matches)
            ->filter(fn (EditorialCalendarMatch $m) => $m->article !== null)
            ->pluck('article.id');

        $articlesOutsidePlan = $articlePool
            ->whereIn('id', $linkedArticleIds)
            ->whereNotIn('id', $matchedArticleIds)
            ->values()
            ->all();

        return new EditorialCalendarReconciliationReport(
            $project,
            $document->id,
            $entries,
            $parseResult->errors,
            $articlesOutsidePlan,
        );
    }

    private function classifyDiscrepancy(EditorialCalendarMatch $match): string
    {
        if ($match->matchType === EditorialCalendarMatchingService::MATCH_NONE) {
            return EditorialCalendarReconciliationEntry::DISCREPANCY_MISSING_ARTICLE;
        }

        if ($match->matchType === EditorialCalendarMatchingService::MATCH_AMBIGUOUS) {
            return count($match->candidates) === 1
                ? EditorialCalendarReconciliationEntry::DISCREPANCY_TITLE_MAJOR_CHANGE
                : EditorialCalendarReconciliationEntry::DISCREPANCY_REQUIRES_REVIEW;
        }

        // Da qui in poi il match è EXACT o NORMALIZED: un solo articolo
        // autorevole con cui confrontare data e stato.
        $article = $match->article;

        $dateDiscrepancy = $this->classifyDateDiscrepancy($match->entry, $article);
        if ($dateDiscrepancy !== null) {
            return $dateDiscrepancy;
        }

        if ($this->hasStatusMismatch($match->entry, $article)) {
            return EditorialCalendarReconciliationEntry::DISCREPANCY_STATUS_MISMATCH;
        }

        if ($match->matchType === EditorialCalendarMatchingService::MATCH_NORMALIZED) {
            return EditorialCalendarReconciliationEntry::DISCREPANCY_TITLE_MINOR_CHANGE;
        }

        return EditorialCalendarReconciliationEntry::DISCREPANCY_NONE;
    }

    /**
     * Confronta solo la DATA (non l'orario): il calendario pianifica
     * giorni, non orari precisi. Nessun confronto se l'articolo non ha
     * ancora una data reale (bozza/in revisione) — non è una discrepanza,
     * è semplicemente "non ancora programmato".
     */
    private function classifyDateDiscrepancy(EditorialCalendarEntry $entry, Article $article): ?string
    {
        $realDate = $article->publishedAtForEditors();

        if ($realDate === null) {
            return null;
        }

        $plannedDate = $entry->date->toDateString();
        $realDateOnly = $realDate->toDateString();

        if ($plannedDate === $realDateOnly) {
            return null;
        }

        return $realDateOnly < $plannedDate
            ? EditorialCalendarReconciliationEntry::DISCREPANCY_DATE_EARLY
            : EditorialCalendarReconciliationEntry::DISCREPANCY_DATE_LATE;
    }

    /**
     * Mai un falso positivo: un testo di stato che non contiene NESSUNA
     * parola chiave riconosciuta non produce mai un mismatch, anche se lo
     * stato reale è diverso da qualunque cosa l'autore avesse in mente —
     * meglio un silenzio che un'interpretazione inventata.
     */
    private function hasStatusMismatch(EditorialCalendarEntry $entry, Article $article): bool
    {
        if ($entry->status === null) {
            return false;
        }

        $declaredStatusText = mb_strtolower($entry->status, 'UTF-8');

        foreach (self::STATUS_KEYWORDS as $status => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($declaredStatusText, $keyword)) {
                    return $status !== $article->status;
                }
            }
        }

        return false;
    }
}
