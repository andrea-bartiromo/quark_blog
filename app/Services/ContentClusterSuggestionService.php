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

    public function accept(ContentClusterSuggestion $suggestion, User $editor): void
    {
        DB::transaction(function () use ($suggestion, $editor) {
            $current = ContentClusterSuggestion::query()->with(['article', 'contentCluster'])->lockForUpdate()->findOrFail($suggestion->id);
            if ($current->status === ContentClusterSuggestion::STATUS_ACCEPTED) {
                return;
            }
            if ($current->status === ContentClusterSuggestion::STATUS_REJECTED) {
                throw ValidationException::withMessages(['suggestion' => 'Il suggerimento è stato rifiutato.']);
            }
            if (! $current->article || ! $current->contentCluster?->is_active) {
                $current->update(['status' => ContentClusterSuggestion::STATUS_STALE]);
                throw ValidationException::withMessages(['suggestion' => 'Articolo o Percorso non sono più disponibili per questa suggestion.']);
            }

            $alreadyMember = DB::table('article_content_cluster')
                ->where('article_id', $current->article_id)
                ->where('content_cluster_id', $current->content_cluster_id)
                ->exists();

            if (! $alreadyMember) {
                $evidence = $this->evidenceForPair($current->article, $current->contentCluster);
                if ($evidence === null || ! hash_equals($current->evidence_hash, $evidence['evidence_hash'])) {
                    $current->update(['status' => ContentClusterSuggestion::STATUS_STALE]);
                    throw ValidationException::withMessages(['suggestion' => 'L’evidence è cambiata: rigenera i suggerimenti.']);
                }
                $this->memberships->addMembership($current->contentCluster, $current->article, $current->suggested_primary);
            }

            $current->update(['status' => ContentClusterSuggestion::STATUS_ACCEPTED, 'reviewed_by' => $editor->id, 'reviewed_at' => now()]);
        });
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
        $clusters = ContentCluster::query()->active()->get(['id', 'slug'])->keyBy('slug');
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
            ->get()->groupBy('category');

        $mappedByArticle = collect();
        foreach (config('content-clusters-initial', []) as $definition) {
            foreach ($definition['articles'] ?? [] as $item) {
                $mappedByArticle->push(['article_slug' => $item['slug'], 'cluster_slug' => $definition['slug'], 'primary' => (bool) ($item['primary'] ?? false)]);
            }
        }
        $mappedByArticle = $mappedByArticle->groupBy('article_slug');
        $desired = collect();

        foreach ($articles as $article) {
            foreach ($mappedByArticle->get($article->slug, collect()) as $mapping) {
                $cluster = $clusters->get($mapping['cluster_slug']);
                if ($cluster && ! $members->has($this->key($article->id, $cluster->id))) {
                    $this->addEvidence($desired, $article, $cluster, 100, 'Initial mapping versionato: match esatto sullo slug.', $mapping['primary']);
                }
            }
            foreach ($categorySignals->get($article->category, collect()) as $signal) {
                $cluster = $clusters->firstWhere('id', (int) $signal->content_cluster_id);
                if ($cluster && ! $members->has($this->key($article->id, $cluster->id))) {
                    $count = (int) $signal->member_count;
                    $this->addEvidence($desired, $article, $cluster, min(85, 55 + min($count, 6) * 5), "Categoria {$article->category}: {$count} membership editoriali confermate.", false);
                }
            }
        }

        return $desired->map(fn ($evidence) => $this->finalizeEvidence($evidence));
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

        $desired = collect();

        foreach (config('content-clusters-initial', []) as $definition) {
            if ((string) ($definition['slug'] ?? '') !== (string) $cluster->slug) {
                continue;
            }

            foreach ($definition['articles'] ?? [] as $item) {
                if ((string) ($item['slug'] ?? '') !== (string) $article->slug) {
                    continue;
                }

                $this->addEvidence(
                    $desired,
                    $article,
                    $cluster,
                    100,
                    'Initial mapping versionato: match esatto sullo slug.',
                    (bool) ($item['primary'] ?? false)
                );
            }
        }

        if ($article->category !== null) {
            $categoryCount = DB::table('article_content_cluster')
                ->join('content_clusters', 'content_clusters.id', '=', 'article_content_cluster.content_cluster_id')
                ->join('articles', 'articles.id', '=', 'article_content_cluster.article_id')
                ->where('content_clusters.is_active', true)
                ->where('article_content_cluster.content_cluster_id', $cluster->id)
                ->where('articles.category', $article->category)
                ->count();

            if ($categoryCount >= 2) {
                $this->addEvidence(
                    $desired,
                    $article,
                    $cluster,
                    min(85, 55 + min($categoryCount, 6) * 5),
                    "Categoria {$article->category}: {$categoryCount} membership editoriali confermate.",
                    false
                );
            }
        }

        $evidence = $desired->get($this->key($article->id, $cluster->id));

        return $evidence === null
            ? null
            : $this->finalizeEvidence($evidence);
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
        return $articleId.':'.$clusterId;
    }
}
