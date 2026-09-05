<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\ContentHealth\ArticleContentHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CertifyScheduledArticles extends Command
{
    protected $signature = 'editorial:scheduled-certification
        {--days=14 : Ampiezza della finestra futura, da 1 a 31 giorni}
        {--from= : Istante iniziale ISO-8601 (UTC); omesso usa now()}
        {--json : Output JSON machine-readable}';

    protected $description = 'Certifica in sola lettura gli articoli programmati nella prossima finestra editoriale';

    public function handle(ArticleContentHealthService $health): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 31],
        ]);

        if ($days === false) {
            $this->error('--days deve essere un intero tra 1 e 31.');

            return self::FAILURE;
        }

        try {
            $from = filled($this->option('from'))
                ? Carbon::parse((string) $this->option('from'), 'UTC')->utc()
                : now()->utc();
        } catch (\Throwable) {
            $this->error('--from deve essere un istante ISO-8601 valido.');

            return self::FAILURE;
        }

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

        $rows = $articles->map(fn (Article $article) => $this->row($article, $health, $collisionCounts));
        $payload = [
            'generated_at' => now()->utc()->toISOString(),
            'from' => $from->toISOString(),
            'until' => $until->toISOString(),
            'timezone' => 'UTC',
            'read_only' => true,
            'count' => $rows->count(),
            'items' => $rows->all(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info("Programmati dal {$payload['from']} al {$payload['until']} (UTC): {$payload['count']}");
        $this->table(
            ['ID', 'Data UTC', 'Titolo', 'Health', 'Percorsi', 'Concept', 'Fonti', 'Collisione', 'Pagina'],
            $rows->map(fn (array $row) => [
                $row['id'],
                $row['published_at'],
                $row['title'],
                $row['content_health']['warning_count'].' warning',
                implode(', ', $row['percorsi']),
                implode(', ', $row['concepts']),
                $row['has_sources'] ? 'sì' : 'no',
                $row['collision_count'] > 1 ? (string) $row['collision_count'] : 'no',
                $row['public_page_expectation'],
            ])->all(),
        );

        return self::SUCCESS;
    }

    /**
     * @param  Collection<string, int>  $collisionCounts
     * @return array<string, mixed>
     */
    private function row(Article $article, ArticleContentHealthService $health, Collection $collisionCounts): array
    {
        $checks = $health->evaluate($article);
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
