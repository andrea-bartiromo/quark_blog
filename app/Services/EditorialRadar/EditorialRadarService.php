<?php

namespace App\Services\EditorialRadar;

use App\Models\Article;
use App\Services\ContentClusters\PercorsoCoverageAuditService;
use App\Services\ContentHealth\ArticleContentHealthService;
use Illuminate\Support\Collection;

class EditorialRadarService
{
    public function __construct(
        private readonly ArticleContentHealthService $contentHealth,
        private readonly PercorsoCoverageAuditService $percorsoCoverage,
    ) {}

    /**
     * Explainable, read-only opportunities backed only by facts available on main.
     * No score, no mutation, no automatic editorial action.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function opportunities(): Collection
    {
        $opportunities = collect();

        Article::query()
            ->published()
            ->with('contentClusters:id')
            ->orderBy('id')
            ->get()
            ->each(function (Article $article) use ($opportunities): void {
                $this->contentHealth->evaluate($article)
                    ->where('status', ArticleContentHealthService::STATUS_WARNING)
                    ->reject(fn (array $finding) => $finding['id'] === 'percorso')
                    ->each(function (array $finding) use ($article, $opportunities): void {
                        $opportunities->push([
                            'key' => "article:{$article->id}:health:{$finding['id']}",
                            'type' => 'UPDATE_CONTENT',
                            'priority' => in_array($finding['id'], ['cover', 'summary', 'sources'], true) ? 'HIGH' : 'MEDIUM',
                            'article_id' => $article->id,
                            'article_slug' => $article->slug,
                            'title' => $article->title,
                            'detected' => $finding['label'],
                            'why' => $finding['reason'],
                            'suggested_action' => 'Rivedi il controllo editoriale indicato; nessuna modifica viene applicata automaticamente.',
                        ]);
                    });
            });

        $coverage = $this->percorsoCoverage->audit();

        foreach ($coverage['published_without_path'] as $article) {
            $opportunities->push([
                'key' => "article:{$article['id']}:percorso:missing",
                'type' => 'PERCORSO_OPPORTUNITY',
                'priority' => 'MEDIUM',
                'article_id' => $article['id'],
                'article_slug' => $article['slug'],
                'title' => $article['title'],
                'detected' => 'Articolo pubblicato senza Percorso',
                'why' => 'L’audit Percorsi non rileva alcuna membership per questo articolo pubblicato.',
                'suggested_action' => 'Valuta manualmente se un Percorso esistente è pertinente; non viene effettuato alcun auto-assignment.',
            ]);
        }

        foreach ($coverage['single_article_paths'] as $path) {
            $opportunities->push([
                'key' => "percorso:{$path['id']}:singleton",
                'type' => 'PERCORSO_OPPORTUNITY',
                'priority' => 'MEDIUM',
                'article_id' => null,
                'article_slug' => null,
                'title' => $path['name'],
                'detected' => 'Percorso con un solo membro',
                'why' => 'Il Percorso contiene un solo articolo e quindi offre una progressione editoriale limitata.',
                'suggested_action' => 'Valuta se ampliare, accorpare o mantenere consapevolmente il Percorso.',
            ]);
        }

        return $opportunities
            ->unique('key')
            ->sortBy(fn (array $row) => [
                match ($row['priority']) { 'HIGH' => 1, 'MEDIUM' => 2, default => 3 },
                $row['type'],
                $row['key'],
            ])
            ->values();
    }
}
