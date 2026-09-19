<?php

namespace App\Services\EditorialOperations;

use App\Models\Article;
use App\Models\Category;
use App\Services\CategoryPublicationReadiness;
use App\Services\ContentHealth\ArticleContentHealthService;

/**
 * Cantiere 54 (programma "100 cantieri Kairus", dipende dal Cantiere 49).
 *
 * Ispezione diretta prima di questo cantiere: resources/views/admin/categories.blade.php
 * già mostra, per ogni categoria, conteggio articoli, stato attivo,
 * visibilità effettiva e checklist di attivazione (CategoryPublicationReadiness,
 * Cantiere 12) — un vero "command center" per lo stato editoriale/di
 * pubblicazione di ciascuna categoria esiste già lì. Il gap reale: nessun
 * punto mostra, PER CATEGORIA, i segnali di salute dei contenuti che
 * EditorialOperationsDashboardService già calcola solo in aggregato
 * sull'intero sito (ArticleContentHealthService) — un editor che gestisce
 * "Salute" o "Energia" non ha modo di vedere quante delle SUE pubblicazioni
 * hanno una criticità di content health, senza scorrere l'intero Command
 * Center generale cercando articoli di quella categoria uno per uno.
 *
 * Stesso principio guida di EditorialOperationsDashboardService (si legga
 * il suo stesso docblock): MAI ricalcolare qui una regola già espressa da
 * un servizio esistente — questo aggregatore raggruppa per categoria un
 * risultato già calcolato da ArticleContentHealthService::evaluate() e da
 * CategoryPublicationReadiness::evaluate(), non decide nulla di nuovo.
 * Read-only per costruzione: nessun metodo qui scrive sul database.
 */
class CategoryCommandCenterService
{
    public function __construct(
        private readonly CategoryPublicationReadiness $readiness,
        private readonly ArticleContentHealthService $contentHealth,
    ) {}

    /** @return list<array<string, mixed>> */
    public function snapshot(): array
    {
        $categories = Category::ordered()->withCount('articles')->get();

        // Selezione completa (non solo id/category/title): ArticleContentHealthService::evaluate()
        // legge cover_image, excerpt, seo_title e altri campi — una select parziale li
        // farebbe risultare "vuoti" e produrrebbe falsi positivi di content health.
        $publishedArticlesByCategory = Article::published()
            ->get()
            ->groupBy('category');

        return $categories->map(function (Category $category) use ($publishedArticlesByCategory) {
            $publishedArticles = $publishedArticlesByCategory->get($category->slug, collect());

            $warningCount = 0;
            $articlesWithWarnings = [];

            foreach ($publishedArticles as $article) {
                $warnings = $this->contentHealth->evaluate($article)
                    ->where('status', ArticleContentHealthService::STATUS_WARNING);

                if ($warnings->isNotEmpty()) {
                    $warningCount += $warnings->count();
                    $articlesWithWarnings[] = [
                        'article_id' => $article->id,
                        'title' => $article->title,
                        'warning_count' => $warnings->count(),
                    ];
                }
            }

            return [
                'category_id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_active' => $category->is_active,
                'visibility_label' => $category->effectiveVisibilityLabel(),
                'total_article_count' => $category->articles_count,
                'published_article_count' => $publishedArticles->count(),
                'readiness' => $this->readiness->evaluate($category),
                'content_health_warning_count' => $warningCount,
                'articles_with_warnings' => $articlesWithWarnings,
            ];
        })->all();
    }
}
