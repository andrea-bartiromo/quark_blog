<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\ContentClusterSuggestion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentClusterSuggestionService
{
    public function __construct(private readonly ContentClusterMembershipService $memberships) {}

    public function regenerate(): array
    {
        $desired = $this->desired();
        $existing = ContentClusterSuggestion::query()->get()->keyBy(fn ($s) => $this->key($s->article_id, $s->content_cluster_id));
        $now = now();
        $rows = [];
        $keptRejected = 0;

        foreach ($desired as $key => $evidence) {
            $current = $existing->get($key);
            if ($current?->status === ContentClusterSuggestion::STATUS_ACCEPTED) {
                continue;
            }
            if ($current?->status === ContentClusterSuggestion::STATUS_REJECTED && hash_equals($current->evidence_hash, $evidence['evidence_hash'])) {
                $keptRejected++;

                continue;
            }

            $rows[] = $evidence + [
                'status' => $current?->status === ContentClusterSuggestion::STATUS_REJECTED ? ContentClusterSuggestion::STATUS_STALE : ContentClusterSuggestion::STATUS_PENDING,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'created_at' => $current?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($rows, $desired, $existing, $now) {
            foreach (array_chunk($rows, 250) as $chunk) {
                DB::table('content_cluster_suggestions')->upsert($chunk, ['article_id', 'content_cluster_id'], [
                    'status', 'confidence', 'reasons', 'evidence_hash', 'suggested_primary', 'reviewed_by', 'reviewed_at', 'updated_at',
                ]);
            }

            $staleIds = $existing
                ->reject(fn ($suggestion, $key) => $desired->has($key))
                ->filter(fn ($suggestion) => in_array($suggestion->status, [ContentClusterSuggestion::STATUS_PENDING, ContentClusterSuggestion::STATUS_REJECTED], true))
                ->pluck('id');

            if ($staleIds->isNotEmpty()) {
                ContentClusterSuggestion::query()->whereIn('id', $staleIds)->update(['status' => ContentClusterSuggestion::STATUS_STALE, 'updated_at' => $now]);
            }
        });

        return [
            'pending' => ContentClusterSuggestion::query()->where('status', ContentClusterSuggestion::STATUS_PENDING)->count(),
            'stale' => ContentClusterSuggestion::query()->where('status', ContentClusterSuggestion::STATUS_STALE)->count(),
            'unchanged_rejected' => $keptRejected,
        ];
    }

    public function refreshForArticle(Article $article): void
    {
        $article->refresh();
        $clusters = ContentCluster::query()->active()->get(['id', 'slug']);
        $existing = ContentClusterSuggestion::query()
            ->where('article_id', $article->id)
            ->get()
            ->keyBy('content_cluster_id');
        $desired = $this->evidenceForArticle($article, $clusters);

        foreach ($clusters as $cluster) {
            $this->applyPairState($article, $cluster, $desired->get($cluster->id), $existing->get($cluster->id));
        }

        $staleIds = $existing
            ->reject(fn ($suggestion) => $desired->has($suggestion->content_cluster_id))
            ->filter(fn ($suggestion) => in_array($suggestion->status, [ContentClusterSuggestion::STATUS_PENDING, ContentClusterSuggestion::STATUS_REJECTED], true))
            ->pluck('id');

        if ($staleIds->isNotEmpty()) {
            ContentClusterSuggestion::query()->whereIn('id', $staleIds)->update([
                'status' => ContentClusterSuggestion::STATUS_STALE,
                'updated_at' => now(),
            ]);
        }
    }

    public function refreshForClusterCategory(ContentCluster $cluster, string $category): void
    {
        $categoryCount = $cluster->is_active
            ? DB::table('article_content_cluster')
                ->join('articles', 'articles.id', '=', 'article_content_cluster.article_id')
                ->where('article_content_cluster.content_cluster_id', $cluster->id)
                ->where('articles.category', $category)
                ->count()
            : 0;

        Article::query()
            ->where('category', $category)
            ->select(['id', 'slug', 'category', 'status', 'published_at'])
            ->orderBy('id')
            ->chunkById(100, function ($articles) use ($cluster, $categoryCount) {
                $articleIds = $articles->pluck('id');
                $memberIds = DB::table('article_content_cluster')
                    ->where('content_cluster_id', $cluster->id)
                    ->whereIn('article_id', $articleIds)
                    ->pluck('article_id')
                    ->map(fn ($id) => (int) $id)
                    ->flip();
                $existing = ContentClusterSuggestion::query()
                    ->where('content_cluster_id', $cluster->id)
                    ->whereIn('article_id', $articleIds)
                    ->get()
                    ->keyBy('article_id');

                foreach ($articles as $article) {
                    $evidence = null;
                    if ($cluster->is_active && ! $memberIds->has($article->id)) {
                        $evidence = $this->buildEvidence(
                            $article,
                            $cluster,
                            $this->mappingsForArticleAndCluster($article, $cluster),
                            $categoryCount
                        );
                    }

                    $this->applyPairState($article, $cluster, $evidence, $existing->get($article->id));
                }
            });
    }

    public function accept(ContentClusterSuggestion $suggestion, User $editor): void
    {
        $validationMessage = DB::transaction(function () use ($suggestion, $editor): ?string {
            $current = ContentClusterSuggestion::query()->with(['article', 'contentCluster'])->lockForUpdate()->findOrFail($suggestion->id);
            if ($current->status === ContentClusterSuggestion::STATUS_ACCEPTED) {
                return null;
            }
            if ($current->status === ContentClusterSuggestion::STATUS_REJECTED) {
                return 'Il suggerimento è stato rifiutato.';
            }
            if (! $current->article || ! $current->contentCluster?->is_active) {
                $current->update(['status' => ContentClusterSuggestion::STATUS_STALE]);

                return 'Articolo o Percorso non sono più disponibili per questa suggestion.';
            }

            $alreadyMember = DB::table('article_content_cluster')
                ->where('article_id', $current->article_id)
                ->where('content_cluster_id', $current->content_cluster_id)
                ->exists();

            if (! $alreadyMember) {
                $evidence = $this->evidenceForPair($current->article, $current->contentCluster);
                if ($evidence === null || ! hash_equals($current->evidence_hash, $evidence['evidence_hash'])) {
                    $current->update(['status' => ContentClusterSuggestion::STATUS_STALE]);

                    return 'L’evidence è cambiata: rigenera i suggerimenti.';
                }
                $this->memberships->addMembership($current->contentCluster, $current->article, $current->suggested_primary);
            }

            $current->update(['status' => ContentClusterSuggestion::STATUS_ACCEPTED, 'reviewed_by' => $editor->id, 'reviewed_at' => now()]);

            return null;
        });

        if ($validationMessage !== null) {
            throw ValidationException::withMessages(['suggestion' => $validationMessage]);
        }
    }

    public function reject(ContentClusterSuggestion $suggestion, User $editor): void
    {
        DB::transaction(function () use ($suggestion, $editor) {
            $current = ContentClusterSuggestion::query()->lockForUpdate()->findOrFail($suggestion->id);
            if ($current->status === ContentClusterSuggestion::STATUS_ACCEPTED) {
                throw ValidationException::withMessages(['suggestion' => 'Una suggestion già accettata non può essere rifiutata.']);
            }
            $current->update(['status' => ContentClusterSuggestion::STATUS_REJECTED, 'reviewed_by' => $editor->id, 'reviewed_at' => now()]);
        });
    }

    private function desired(): Collection
    {
        $clusters = ContentCluster::query()->active()->get(['id', 'slug'])->keyBy('id');
        $clustersBySlug = $clusters->keyBy('slug');
        $articles = Article::query()->get(['id', 'slug', 'category', 'status', 'published_at']);
        $members = DB::table('article_content_cluster')->get(['article_id', 'content_cluster_id'])
            ->mapWithKeys(fn ($row) => [$this->key((int) $row->article_id, (int) $row->content_cluster_id) => true]);
        $categorySignals = DB::table('article_content_cluster')
            ->join('content_clusters', 'content_clusters.id', '=', 'article_content_cluster.content_cluster_id')
            ->join('articles', 'articles.id', '=', 'article_content_cluster.article_id')
            ->where('content_clusters.is_active', true)
            ->whereNotNull('articles.category')
            ->select('article_content_cluster.content_cluster_id', 'articles.category', DB::raw('COUNT(*) AS member_count'))
            ->groupBy('article_content_cluster.content_cluster_id', 'articles.category')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->groupBy('category');

        $mappedByArticle = collect();
        foreach (config('content-clusters-initial', []) as $definition) {
            foreach ($definition['articles'] ?? [] as $item) {
                $mappedByArticle->push([
                    'article_slug' => (string) ($item['slug'] ?? ''),
                    'cluster_slug' => (string) ($definition['slug'] ?? ''),
                    'primary' => (bool) ($item['primary'] ?? false),
                ]);
            }
        }
        $mappedByArticle = $mappedByArticle->groupBy('article_slug');
        $desired = collect();

        foreach ($articles as $article) {
            $candidates = collect();

            foreach ($mappedByArticle->get((string) $article->slug, collect()) as $mapping) {
                $cluster = $clustersBySlug->get($mapping['cluster_slug']);
                if ($cluster && ! $members->has($this->key($article->id, $cluster->id))) {
                    $candidate = $candidates->get($cluster->id, ['cluster' => $cluster, 'mappings' => [], 'category_count' => 0]);
                    $candidate['mappings'][] = $mapping;
                    $candidates->put($cluster->id, $candidate);
                }
            }

            foreach ($categorySignals->get($article->category, collect()) as $signal) {
                $cluster = $clusters->get((int) $signal->content_cluster_id);
                if ($cluster && ! $members->has($this->key($article->id, $cluster->id))) {
                    $candidate = $candidates->get($cluster->id, ['cluster' => $cluster, 'mappings' => [], 'category_count' => 0]);
                    $candidate['category_count'] = (int) $signal->member_count;
                    $candidates->put($cluster->id, $candidate);
                }
            }

            foreach ($candidates as $candidate) {
                $evidence = $this->buildEvidence(
                    $article,
                    $candidate['cluster'],
                    $candidate['mappings'],
                    $candidate['category_count']
                );

                if ($evidence !== null) {
                    $desired->put($this->key($article->id, $candidate['cluster']->id), $evidence);
                }
            }
        }

        return $desired;
    }

    private function evidenceForArticle(Article $article, Collection $clusters): Collection
    {
        $members = DB::table('article_content_cluster')
            ->where('article_id', $article->id)
            ->pluck('content_cluster_id')
            ->map(fn ($id) => (int) $id)
            ->flip();
        $categoryCounts = collect();

        if ($article->category !== null && $article->category !== '' && $clusters->isNotEmpty()) {
            $categoryCounts = DB::table('article_content_cluster')
                ->join('articles', 'articles.id', '=', 'article_content_cluster.article_id')
                ->whereIn('article_content_cluster.content_cluster_id', $clusters->pluck('id'))
                ->where('articles.category', $article->category)
                ->select('article_content_cluster.content_cluster_id', DB::raw('COUNT(*) AS member_count'))
                ->groupBy('article_content_cluster.content_cluster_id')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->content_cluster_id => (int) $row->member_count]);
        }

        $desired = collect();
        foreach ($clusters as $cluster) {
            if ($members->has($cluster->id)) {
                continue;
            }

            $evidence = $this->buildEvidence(
                $article,
                $cluster,
                $this->mappingsForArticleAndCluster($article, $cluster),
                (int) $categoryCounts->get($cluster->id, 0)
            );

            if ($evidence !== null) {
                $desired->put($cluster->id, $evidence);
            }
        }

        return $desired;
    }

    private function evidenceForPair(Article $article, ContentCluster $cluster): ?array
    {
        if (! $cluster->is_active) {
            return null;
        }

        $alreadyMember = DB::table('article_content_cluster')
            ->where('article_id', $article->id)
            ->where('content_cluster_id', $cluster->id)
            ->exists();

        if ($alreadyMember) {
            return null;
        }

        $mappings = $this->mappingsForArticleAndCluster($article, $cluster);

        $categoryCount = 0;
        if ($article->category !== null && $article->category !== '') {
            $categoryCount = DB::table('article_content_cluster')
                ->join('articles', 'articles.id', '=', 'article_content_cluster.article_id')
                ->where('article_content_cluster.content_cluster_id', $cluster->id)
                ->where('articles.category', $article->category)
                ->count();
        }

        return $this->buildEvidence($article, $cluster, $mappings, $categoryCount);
    }

    private function mappingsForArticleAndCluster(Article $article, ContentCluster $cluster): array
    {
        $mappings = [];

        foreach (config('content-clusters-initial', []) as $definition) {
            if ((string) ($definition['slug'] ?? '') !== (string) $cluster->slug) {
                continue;
            }

            foreach ($definition['articles'] ?? [] as $item) {
                if ((string) ($item['slug'] ?? '') === (string) $article->slug) {
                    $mappings[] = ['primary' => (bool) ($item['primary'] ?? false)];
                }
            }
        }

        return $mappings;
    }

    private function buildEvidence(Article $article, ContentCluster $cluster, array $mappings, int $categoryCount): ?array
    {
        $desired = collect();

        foreach ($mappings as $mapping) {
            $this->addEvidence(
                $desired,
                $article,
                $cluster,
                100,
                'Initial mapping versionato: match esatto sullo slug.',
                (bool) ($mapping['primary'] ?? false)
            );
        }

        if ($categoryCount >= 2 && $article->category !== null && $article->category !== '') {
            $this->addEvidence(
                $desired,
                $article,
                $cluster,
                min(85, 55 + min($categoryCount, 6) * 5),
                "Categoria {$article->category}: {$categoryCount} membership editoriali confermate.",
                false
            );
        }

        $evidence = $desired->get($this->key($article->id, $cluster->id));

        return $evidence === null ? null : $this->finalizeEvidence($evidence);
    }

    private function applyPairState(Article $article, ContentCluster $cluster, ?array $evidence, ?ContentClusterSuggestion $current): void
    {
        if ($evidence === null) {
            if ($current && in_array($current->status, [ContentClusterSuggestion::STATUS_PENDING, ContentClusterSuggestion::STATUS_REJECTED], true)) {
                $current->update(['status' => ContentClusterSuggestion::STATUS_STALE]);
            }

            return;
        }

        if ($current?->status === ContentClusterSuggestion::STATUS_ACCEPTED) {
            return;
        }

        if ($current?->status === ContentClusterSuggestion::STATUS_REJECTED && hash_equals($current->evidence_hash, $evidence['evidence_hash'])) {
            return;
        }

        if ($current?->status === ContentClusterSuggestion::STATUS_PENDING && hash_equals($current->evidence_hash, $evidence['evidence_hash'])) {
            return;
        }

        ContentClusterSuggestion::query()->updateOrCreate(
            ['article_id' => $article->id, 'content_cluster_id' => $cluster->id],
            $evidence + [
                'status' => $current?->status === ContentClusterSuggestion::STATUS_REJECTED
                    ? ContentClusterSuggestion::STATUS_STALE
                    : ContentClusterSuggestion::STATUS_PENDING,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );
    }

    private function finalizeEvidence(array $evidence): array
    {
        sort($evidence['reasons']);
        $evidence['reasons'] = json_encode($evidence['reasons'], JSON_THROW_ON_ERROR);
        $evidence['evidence_hash'] = hash('sha256', implode('|', [$evidence['article_id'], $evidence['content_cluster_id'], $evidence['confidence'], $evidence['reasons'], (int) $evidence['suggested_primary']]));

        return $evidence;
    }

    private function addEvidence(Collection $desired, Article $article, ContentCluster $cluster, int $confidence, string $reason, bool $primary): void
    {
        $key = $this->key($article->id, $cluster->id);
        $current = $desired->get($key, ['article_id' => $article->id, 'content_cluster_id' => $cluster->id, 'confidence' => 0, 'reasons' => [], 'suggested_primary' => false]);
        $current['confidence'] = max($current['confidence'], $confidence);
        $current['reasons'][] = $reason;
        $current['suggested_primary'] = $current['suggested_primary'] || $primary;
        $desired->put($key, $current);
    }

    private function key(int $articleId, int $clusterId): string
    {
        return $articleId . ':' . $clusterId;
    }
}
