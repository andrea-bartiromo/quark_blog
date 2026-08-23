<?php

namespace App\Services\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use Illuminate\Support\Collection;

/**
 * Audit diagnostico e read-only della copertura editoriale dei Percorsi.
 * Non assegna articoli, non riordina pivot e non modifica cluster.
 */
class PercorsoCoverageAuditService
{
    /**
     * @return array<string, mixed>
     */
    public function audit(): array
    {
        $articles = Article::query()
            ->with('contentClusters:id,name,slug,is_active')
            ->whereIn('status', [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED])
            ->orderBy('id')
            ->get(['id', 'title', 'slug', 'status', 'published_at']);

        $clusters = ContentCluster::query()
            ->with([
                'articles:id,title,slug,status,published_at',
                'pillarArticle:id,title,slug,status,published_at',
            ])
            ->ordered()
            ->get();

        $publishedWithoutPath = $articles
            ->filter(fn (Article $article) => $article->status === Article::STATUS_PUBLISHED && $article->contentClusters->isEmpty())
            ->map(fn (Article $article) => $this->articleSummary($article))
            ->values()
            ->all();

        $scheduledWithoutPath = $articles
            ->filter(fn (Article $article) => $article->status === Article::STATUS_SCHEDULED && $article->contentClusters->isEmpty())
            ->map(fn (Article $article) => $this->articleSummary($article))
            ->values()
            ->all();

        $clusterRows = $clusters->map(fn (ContentCluster $cluster) => $this->clusterRow($cluster))->values();

        return [
            'published_without_path' => $publishedWithoutPath,
            'scheduled_without_path' => $scheduledWithoutPath,
            'single_article_paths' => $clusterRows->filter(fn (array $row) => $row['member_count'] === 1)->values()->all(),
            'paths_with_duplicate_positions' => $clusterRows->filter(fn (array $row) => $row['duplicate_positions'] !== [])->values()->all(),
            'paths_with_non_publishable_members' => $clusterRows->filter(fn (array $row) => $row['non_publishable_members'] !== [])->values()->all(),
            'paths_with_incoherent_pillar' => $clusterRows->filter(fn (array $row) => $row['pillar_issue'] !== null)->values()->all(),
            'articles_in_multiple_paths' => $articles
                ->filter(fn (Article $article) => $article->contentClusters->count() > 1)
                ->map(fn (Article $article) => [
                    ...$this->articleSummary($article),
                    'path_count' => $article->contentClusters->count(),
                    'paths' => $article->contentClusters->pluck('slug')->values()->all(),
                ])
                ->values()
                ->all(),
            'clusters' => $clusterRows->all(),
            'policy_notes' => [
                'missing_pillar_is_not_an_error' => true,
                'multiple_paths_are_reported_not_failed' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clusterRow(ContentCluster $cluster): array
    {
        $positions = $cluster->articles
            ->map(fn (Article $article) => $article->pivot?->position)
            ->filter(fn ($position) => $position !== null)
            ->map(fn ($position) => (int) $position)
            ->values();

        $duplicatePositions = $positions
            ->countBy()
            ->filter(fn (int $count) => $count > 1)
            ->keys()
            ->map(fn ($position) => (int) $position)
            ->sort()
            ->values()
            ->all();

        $nonPublishableMembers = $cluster->articles
            ->filter(fn (Article $article) => in_array($article->status, [Article::STATUS_DRAFT, Article::STATUS_REVIEW], true))
            ->map(fn (Article $article) => $this->articleSummary($article))
            ->values()
            ->all();

        $memberIds = $cluster->articles->pluck('id');
        $pillarIssue = null;

        if ($cluster->pillar_article_id !== null) {
            if ($cluster->pillarArticle === null) {
                $pillarIssue = 'pillar_target_missing';
            } elseif (! $memberIds->contains($cluster->pillar_article_id)) {
                $pillarIssue = 'pillar_not_in_path';
            } elseif (in_array($cluster->pillarArticle->status, [Article::STATUS_DRAFT, Article::STATUS_REVIEW], true)) {
                $pillarIssue = 'pillar_not_publishable';
            }
        }

        return [
            'id' => $cluster->id,
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'is_active' => (bool) $cluster->is_active,
            'member_count' => $cluster->articles->count(),
            'duplicate_positions' => $duplicatePositions,
            'non_publishable_members' => $nonPublishableMembers,
            'pillar_article_id' => $cluster->pillar_article_id,
            'pillar_issue' => $pillarIssue,
        ];
    }

    /**
     * @return array{id:int,title:string,slug:string,status:string}
     */
    private function articleSummary(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'status' => $article->status,
        ];
    }
}
