<?php

namespace App\Observers;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\Category;
use App\Services\ArticleCategoryAffinityService;

class ArticleLinkSuggestionCategoryAffinityObserver
{
    private const SECONDARY_CATEGORY_BONUS = 10;

    /**
     * Il motore storico assegna già +10 quando le categorie principali
     * coincidono. Qui estendiamo lo stesso segnale alle associazioni
     * secondarie, senza mai creare un suggerimento basato sulla sola
     * categoria: interveniamo esclusivamente su proposte che hanno già
     * superato i controlli lessicali/temporali del motore esistente.
     */
    public function saved(ArticleLinkSuggestion $suggestion): void
    {
        if ($suggestion->status !== ArticleLinkSuggestion::STATUS_PROPOSED || $suggestion->target_article_id === null) {
            return;
        }

        // Evita doppio bonus su successive scritture della stessa proposta.
        if (str_contains((string) $suggestion->reason, 'categoria condivisa:')) {
            return;
        }

        $source = Article::with('secondaryCategories:id,slug')->find($suggestion->source_article_id);
        $target = Article::with('secondaryCategories:id,slug')->find($suggestion->target_article_id);

        if (! $source || ! $target) {
            return;
        }

        // La coincidenza tra principali è già conteggiata dal motore base.
        if ($source->category === $target->category) {
            return;
        }

        $affinity = app(ArticleCategoryAffinityService::class);
        $sharedSlug = $affinity->sharedSlug(
            $affinity->slugsFor($source),
            $affinity->slugsFor($target)
        );

        if ($sharedSlug === null) {
            return;
        }

        $label = Category::where('slug', $sharedSlug)->value('name')
            ?? config('laboratorio.categories.'.$sharedSlug, $sharedSlug);

        $reason = trim((string) $suggestion->reason);
        $reason = $reason === ''
            ? 'Categoria condivisa: '.$label
            : rtrim($reason, ". \t\n\r\0\x0B").'; categoria condivisa: '.$label;

        $suggestion->updateQuietly([
            'confidence_score' => min(100, (int) $suggestion->confidence_score + self::SECONDARY_CATEGORY_BONUS),
            'reason' => $reason,
        ]);
    }
}
