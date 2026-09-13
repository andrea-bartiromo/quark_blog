<?php

namespace App\Services\EditorialOperations;

use App\Models\Article;
use App\Services\ContentHealth\ArticleContentHealthService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cantiere 37 (programma 100-cantieri Kairus). Estratto da
 * App\Console\Commands\CertifyScheduledArticles (editorial:scheduled-
 * certification, sola lettura, finestra futura configurabile) — stessa
 * identica query/logica, mai ricalcolata: il comando CLI ora delega qui,
 * comportamento invariato (vedi CertifyScheduledArticlesCommandTest,
 * lasciato intatto per provarlo). Il gap reale che questo cantiere
 * colma non è la logica (già corretta e già testata), ma la sua
 * assenza da qualunque superficie web admin: prima di questo cantiere
 * era raggiungibile solo da riga di comando, con un default di 14
 * giorni — mai una vista "Report pubblicazioni programmate" con la
 * finestra di 30 giorni nominata dal titolo di questo cantiere.
 */
class ScheduledArticlesCertificationService
{
    public function __construct(private readonly ArticleContentHealthService $health) {}

    /** @return array{generated_at: string, from: string, until: string, timezone: string, read_only: bool, count: int, items: list<array<string, mixed>>} */
    public function report(Carbon $from, int $days): array
    {
        $until = $from->copy()->addDays($days);
        $articles = Article::query()
            ->where('status', Article::STATUS_SCHEDULED)
            ->whereBetween('published_at', [$from, $until])
            ->with([
                'contentClusters:id,name',
                'contentConcepts.concept:id,name',
            ])
            ->orderBy('published_at')
            ->orderBy('id')
            ->get();

        $collisionCounts = $articles->countBy(
            fn (Article $article) => $article->published_at?->utc()->toISOString() ?? 'missing'
        );

        $rows = $articles->map(fn (Article $article) => $this->row($article, $collisionCounts));

        return [
            'generated_at' => now()->utc()->toISOString(),
            'from' => $from->toISOString(),
            'until' => $until->toISOString(),
            'timezone' => 'UTC',
            'read_only' => true,
            'count' => $rows->count(),
            'items' => $rows->all(),
        ];
    }

    /**
     * @param  Collection<string, int>  $collisionCounts
     * @return array<string, mixed>
     */
    private function row(Article $article, Collection $collisionCounts): array
    {
        $checks = $this->health->evaluate($article);
        $publishedAt = $article->published_at?->utc()->toISOString();

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'published_at' => $publishedAt,
            'editorial_published_at' => $article->published_at?->timezone(Article::EDITORIAL_TIMEZONE)->toISOString(),
            'content_health' => [
                'warning_count' => $checks->where('status', ArticleContentHealthService::STATUS_WARNING)->count(),
                'warnings' => $checks->where('status', ArticleContentHealthService::STATUS_WARNING)->pluck('id')->values()->all(),
            ],
            'percorsi' => $article->contentClusters->pluck('name')->sort()->values()->all(),
            'concepts' => $article->contentConcepts->pluck('concept.name')->filter()->sort()->values()->all(),
            'has_sources' => filled($article->primary_sources),
            'collision_count' => $collisionCounts->get($publishedAt, 0),
            'public_page_expectation' => '404_until_publication',
        ];
    }
}
