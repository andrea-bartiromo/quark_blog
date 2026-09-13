<?php

namespace App\Services\SearchProfile;

use App\Models\Article;
use App\Models\ArticleSearchProfile;
use Illuminate\Support\Collection;

/**
 * Cantiere 2 (programma "Kairus Organic Discovery"). Segnala — non
 * blocca — quando la query primaria di un articolo coincide, dopo
 * normalizzazione, con quella di un altro articolo: un avviso editoriale
 * non vincolante, mai un gate di pubblicazione. La sovrapposizione più
 * ampia tra query/domande secondarie e i dati Search Console resta
 * compito del Cantiere 5 ("Cannibalizzazione di ricerca"), qui non
 * anticipata.
 */
class ArticleSearchProfileCollisionService
{
    /**
     * @return Collection<int, Article>
     */
    public function collidingArticles(Article $article, ?string $primaryQuery): Collection
    {
        $normalized = $this->normalize((string) $primaryQuery);

        if ($normalized === '') {
            return collect();
        }

        return ArticleSearchProfile::query()
            ->where('article_id', '!=', $article->id)
            ->whereNotNull('primary_query')
            ->with('article')
            ->get()
            ->filter(fn (ArticleSearchProfile $profile) => $this->normalize((string) $profile->primary_query) === $normalized)
            ->map(fn (ArticleSearchProfile $profile) => $profile->article)
            ->filter()
            ->values();
    }

    /**
     * Stessa normalizzazione conservativa già in uso per confronti di
     * testo editoriale in questo codebase (EditorialCalendarMatchingService::
     * normalizeTitle(), ConceptDuplicateAuditService::normalize()) —
     * duplicata qui, non condivisa via injection, per lo stesso motivo già
     * documentato in ConceptDuplicateAuditService: bounded context distinti
     * che non hanno altro in comune. Solo match esatto dopo normalizzazione,
     * mai fuzzy matching (nessun precedente di soglia in questo codebase).
     */
    private function normalize(string $text): string
    {
        $text = trim($text);
        $text = str_replace(['’', '‘', '´', '`'], "'", $text);
        $text = str_replace(['“', '”', '"'], '', $text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = rtrim($text, " \t\n\r\0\x0B?!.,;:");

        return trim($text);
    }
}
