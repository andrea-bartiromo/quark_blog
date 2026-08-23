<?php

namespace App\Services\Discovery;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Services\ContentClusters\ContentClusterPublicSequence;
use App\Services\InternalLinking\InternalLinkAuditService;
use Illuminate\Support\Collection;

class ArticleDiscoveryAuditService
{
    private const PER_PAGE = 12;

    public function __construct(
        private readonly InternalLinkAuditService $internalLinks,
        private readonly ContentClusterPublicSequence $publicSequence,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function audit(): Collection
    {
        $articles = Article::query()
            ->published()
            ->with([
                'secondaryCategories:id,slug',
                'author:id',
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        $publishedIds = $articles->pluck('id')->map(fn ($id) => (int) $id)->values();
        $publishedIdSet = $publishedIds->flip();

        $navigableCategorySlugs = Category::query()->pluck('slug')
            ->merge(array_keys(config('laboratorio.categories', [])))
            ->filter()
            ->unique()
            ->flip();

        // Load the whole active Percorsi corpus in two bounded queries (clusters
        // + pivot/articles), then apply the shared stop-at-first-gap contract in
        // memory. This avoids both false discovery paths and one-query-per-path.
        $activeClusters = ContentCluster::query()
            ->active()
            ->with(['articles' => fn ($query) => $query->select('articles.id')])
            ->get();
        $reachablePathCounts = array_fill_keys($publishedIds->all(), 0);

        foreach ($activeClusters as $cluster) {
            $sequence = $this->publicSequence->resolveLoaded($cluster, $publishedIds)['articles'];

            foreach ($sequence as $member) {
                $memberId = (int) $member->id;
                if ($publishedIdSet->has($memberId)) {
                    $reachablePathCounts[$memberId] = ($reachablePathCounts[$memberId] ?? 0) + 1;
                }
            }
        }

        $archivePosition = $articles->values()->mapWithKeys(fn (Article $article, int $index) => [$article->id => $index]);
        $publishedIdBySlug = $articles->pluck('id', 'slug');
        $incomingByArticleId = array_fill_keys($publishedIds->all(), 0);

        // InternalLinkAuditService correctly classifies/normalizes links, but
        // Discovery counts incoming navigation only from source articles public
        // now. Draft/review/scheduled sources can never establish a public path.
        foreach ($this->internalLinks->audit(status: Article::STATUS_PUBLISHED)->rows as $row) {
            $resolvedTargets = collect($row->outgoingLinks)
                ->filter(fn (array $link) => in_array($link['classification'], ['valid', 'redirected'], true))
                ->pluck('resolvedSlug')
                ->filter()
                ->unique();

            foreach ($resolvedTargets as $resolvedSlug) {
                $targetId = $publishedIdBySlug->get($resolvedSlug);
                if ($targetId !== null && (int) $targetId !== $row->articleId) {
                    $incomingByArticleId[(int) $targetId]++;
                }
            }
        }

        return $articles->map(function (Article $article) use (
            $archivePosition,
            $incomingByArticleId,
            $articles,
            $navigableCategorySlugs,
            $reachablePathCounts,
        ): array {
            $categorySlugs = collect([$article->category])
                ->merge($article->secondaryCategories->pluck('slug'))
                ->filter(fn ($slug) => filled($slug) && $navigableCategorySlugs->has($slug))
                ->unique()
                ->values();

            $categoryPages = $categorySlugs->mapWithKeys(function (string $slug) use ($article, $articles): array {
                $matching = $articles->filter(fn (Article $candidate) => $candidate->category === $slug || $candidate->secondaryCategories->contains('slug', $slug)
                )->values();
                $index = $matching->search(fn (Article $candidate) => $candidate->id === $article->id);

                return [$slug => $index === false ? null : intdiv((int) $index, self::PER_PAGE) + 1];
            })->filter(fn ($page) => $page !== null);

            $archivePage = intdiv((int) $archivePosition[$article->id], self::PER_PAGE) + 1;
            $incoming = (int) ($incomingByArticleId[$article->id] ?? 0);
            $activePaths = (int) ($reachablePathCounts[$article->id] ?? 0);

            $entryPoints = collect([
                'notizie' => ['type' => 'ARCHIVE_ENTRY', 'page' => $archivePage],
                'author' => ['type' => 'ARCHIVE_ENTRY', 'available' => $article->author !== null],
                'categories' => ['type' => 'ARCHIVE_ENTRY', 'pages' => $categoryPages->all()],
                'percorsi' => ['type' => 'STRUCTURAL_NAVIGATION', 'count' => $activePaths],
                'body_incoming' => ['type' => 'EDITORIAL_BODY_LINK', 'count' => $incoming],
            ]);

            $pathCount = 1
                + ($article->author !== null ? 1 : 0)
                + $categoryPages->count()
                + ($activePaths > 0 ? 1 : 0)
                + ($incoming > 0 ? 1 : 0);
            $weakDiscovery = $categoryPages->isEmpty() && $activePaths === 0 && $incoming === 0;

            return [
                'article_id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'archive_page_number' => $archivePage,
                'category_page_numbers' => $categoryPages->all(),
                'body_incoming_count' => $incoming,
                'active_path_count' => $activePaths,
                'discovery_path_count' => $pathCount,
                'discovery_class' => match (true) {
                    $pathCount === 0 => 'ZERO_PATHS',
                    $pathCount === 1 => 'ONE_PATH',
                    default => 'MULTIPLE_PATHS',
                },
                'minimum_deterministic_depth' => $archivePage === 1 ? 2 : 3,
                'entry_points' => $entryPoints->all(),
                'risks' => collect([
                    $incoming === 0 ? 'NO_BODY_INCOMING_LINKS' : null,
                    $activePaths === 0 ? 'NO_ACTIVE_PATH' : null,
                    $categoryPages->isEmpty() ? 'NO_CATEGORY_PATH' : null,
                    $weakDiscovery ? 'WEAK_DISCOVERY' : null,
                ])->filter()->values()->all(),
                'not_measured' => ['homepage_modules', 'recommendations', 'trova_queries'],
            ];
        })->values();
    }
}
