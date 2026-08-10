<?php

namespace App\Services\Editorial;

use App\Models\Article;

/**
 * Un collegamento effettivamente applicato da EditorialCalendarLinkingService
 * — solo voci che erano già EditorialCalendarMatch::isSafeToAutoLink().
 */
final readonly class EditorialCalendarLinkedEntry
{
    public function __construct(
        public EditorialCalendarEntry $entry,
        public Article $article,
        public string $matchType,
    ) {}
}
