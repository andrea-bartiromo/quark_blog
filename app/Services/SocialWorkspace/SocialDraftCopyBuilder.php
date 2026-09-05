<?php

namespace App\Services\SocialWorkspace;

use App\Models\Article;

/**
 * Puro, nessuna generazione via IA, nessun hashtag automatico. Produce
 * solo un punto di partenza conservativo e sempre modificabile per il
 * campo copy di una nuova bozza — titolo + sommario, quando presente. Non
 * tocca in alcun modo il contenuto dell'articolo sorgente.
 */
class SocialDraftCopyBuilder
{
    public function initial(Article $article): string
    {
        $lines = array_filter([
            $article->title,
            $article->excerpt,
        ], fn (?string $line) => filled($line));

        return implode("\n\n", $lines);
    }
}
