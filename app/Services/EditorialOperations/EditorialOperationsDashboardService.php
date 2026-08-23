<?php

namespace App\Services\EditorialOperations;

use App\Models\Article;
use App\Services\ContentClusters\PercorsoCoverageAuditService;
use App\Services\ContentHealth\ArticleContentHealthService;
use App\Services\EditorialQuality\SeoMetadataQualityAuditService;
use App\Services\EditorialQuality\SourceImageAttributionHealthService;

class EditorialOperationsDashboardService
{
    public function __construct(
        private readonly ArticleContentHealthService $contentHealth,
        private readonly PercorsoCoverageAuditService $percorsoCoverage,
        private readonly SourceImageAttributionHealthService $attribution,
        private readonly SeoMetadataQualityAuditService $seo,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $articles = Article::query()
            ->whereIn('status', [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED])
            ->with('contentClusters:id')
            ->orderBy('published_at')
            ->orderBy('id')
            ->get();

        $toPublish = $articles
            ->where('status', Article::STATUS_SCHEDULED)
            ->map(fn (Article $article) => [
                'article_id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'published_at' => $article->published_at?->toISOString(),
            ])->values()->all();

        $toFix = $articles->map(function (Article $article): ?array {
            $healthWarnings = $this->contentHealth->evaluate($article)
                ->where('status', ArticleContentHealthService::STATUS_WARNING)
                ->values();
            $attributionWarnings = collect($this->attribution->evaluate($article))
                ->where('status', SourceImageAttributionHealthService::WARNING)
                ->values();

            if ($healthWarnings->isEmpty() && $attributionWarnings->isEmpty()) {
                return null;
            }

            return [
                'article_id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'health_warnings' => $healthWarnings->all(),
                'attribution_warnings' => $attributionWarnings->all(),
            ];
        })->filter()->values()->all();

        $coverage = $this->percorsoCoverage->audit();
        $seo = $this->seo->audit();

        return [
            'da_pubblicare' => $toPublish,
            'da_sistemare' => $toFix,
            'contenuti_isolati' => $coverage['published_without_path'],
            'seo' => [
                'summary' => $seo['summary'],
                'articles' => $seo['articles'],
            ],
            'opportunita' => [
                'available' => false,
                'reason' => 'Radar runtime non è ancora su main; nessun dato viene inventato.',
            ],
            'distribuzione' => [
                'available' => false,
                'reason' => 'Social Attribution non è ancora su main; nessun dato viene inventato.',
            ],
        ];
    }
}
