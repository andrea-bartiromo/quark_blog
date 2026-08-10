<?php

namespace App\Services\Editorial;

use App\Models\Article;

/**
 * Esito del confronto tra UNA voce di calendario e il pool di articoli del
 * CMS, prodotto esclusivamente da EditorialCalendarMatchingService.
 */
final readonly class EditorialCalendarMatch
{
    /**
     * @param  list<Article>  $candidates  Tutti gli articoli plausibili trovati, anche quando article è null (0 o >1 candidati).
     */
    public function __construct(
        public EditorialCalendarEntry $entry,
        public string $matchType,
        public ?Article $article,
        public array $candidates,
        public bool $alreadyLinkedToProject,
    ) {}

    /**
     * Vero solo per un match EXACT o NORMALIZED con un candidato unico —
     * mai per AMBIGUOUS, anche quando ha un solo candidato "vicino": quel
     * caso è sempre e solo un suggerimento, mai un'applicazione automatica
     * (vedi EditorialCalendarMatchingService).
     */
    public function isSafeToAutoLink(): bool
    {
        return in_array($this->matchType, [
            EditorialCalendarMatchingService::MATCH_EXACT,
            EditorialCalendarMatchingService::MATCH_NORMALIZED,
        ], true) && $this->article !== null;
    }
}
