<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentCluster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentClusterMembershipService
{
    /**
     * @param  array<int, array{article_id:int, position?:int|null, is_primary?:bool}>  $memberships
     */
    public function sync(ContentCluster $cluster, array $memberships, ?int $pillarArticleId): void
    {
        DB::transaction(function () use ($cluster, $memberships, $pillarArticleId) {
            $articleIds = collect($memberships)->pluck('article_id')->map(fn ($id) => (int) $id)->unique()->values();

            if ($pillarArticleId !== null && ! $articleIds->contains($pillarArticleId)) {
                throw ValidationException::withMessages([
                    'pillar_article_id' => 'Il pillar deve appartenere al Percorso.',
                ]);
            }

            // Lock delle righe Article in ordine stabile: due editor che cambiano
            // contemporaneamente il primary dello stesso articolo vengono serializzati.
            if ($articleIds->isNotEmpty()) {
                Article::query()->whereIn('id', $articleIds)->orderBy('id')->lockForUpdate()->get(['id']);
            }

            $normalized = collect($memberships)
                ->sortBy(fn (array $membership) => [
                    $membership['position'] ?? PHP_INT_MAX,
                    $membership['article_id'],
                ])
                ->values();

            $sync = [];
            foreach ($normalized as $index => $membership) {
                $articleId = (int) $membership['article_id'];
                $isPrimary = (bool) ($membership['is_primary'] ?? false);

                if ($isPrimary) {
                    DB::table('article_content_cluster')
                        ->where('article_id', $articleId)
                        ->where('content_cluster_id', '!=', $cluster->id)
                        ->update(['is_primary' => false, 'updated_at' => now()]);
                }

                $sync[$articleId] = [
                    'position' => ($index + 1) * 10,
                    'is_primary' => $isPrimary,
                ];
            }

            $cluster->articles()->sync($sync);
            $cluster->update(['pillar_article_id' => $pillarArticleId]);
        });
    }
}
