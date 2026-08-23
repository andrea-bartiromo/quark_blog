<?php

namespace App\Services\SearchConsole;

use App\Models\Article;

/**
 * Fa corrispondere il page_url grezzo di una riga Search Console a un
 * Article reale di Kairus. Deliberatamente semplice e trasparente: match
 * esatto sullo slug estratto dal path "/articolo/{slug}", nessun
 * fuzzy-matching, nessuna euristica su titolo/query. Un page_url che non
 * corrisponde resta senza articolo — segnale editoriale (nessuna landing
 * page dedicata), non un errore di matching da forzare.
 */
class SearchConsoleQueryArticleMatcher
{
    public function match(string $pageUrl): ?Article
    {
        $path = parse_url($pageUrl, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        if (preg_match('#/articolo/([^/]+)/?$#', $path, $matches) !== 1) {
            return null;
        }

        return Article::query()->where('slug', $matches[1])->first();
    }
}
