<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Services\ContentClusterMembershipService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillInitialContentClusters extends Command
{
    protected $signature = 'content-clusters:backfill-initial {--apply : Apply the versioned mapping; default is dry-run}';

    protected $description = 'Plan or safely apply the versioned initial Content Cluster mapping';

    public function handle(ContentClusterMembershipService $memberships): int
    {
        $apply = (bool) $this->option('apply');
        $mapping = config('content-clusters-initial', []);
        $this->components->info($apply ? 'APPLY mode' : 'DRY RUN — no database writes');

        foreach ($mapping as $definition) {
            $cluster = ContentCluster::query()->where('slug', $definition['slug'])->first();
            $this->line(($cluster ? 'UPDATE CLUSTER' : 'CREATE CLUSTER')." {$definition['slug']}");

            $resolved = [];
            foreach ($definition['articles'] as $item) {
                $article = Article::query()->where('slug', $item['slug'])->first();
                if (! $article) {
                    $this->warn("MISSING ARTICLE {$item['slug']}");

                    continue;
                }

                $primaryConflict = (bool) DB::table('article_content_cluster')
                    ->where('article_id', $article->id)
                    ->when($cluster, fn ($q) => $q->where('content_cluster_id', '!=', $cluster->id))
                    ->where('is_primary', true)
                    ->exists();

                if ($item['primary'] && $primaryConflict) {
                    $this->warn("CONFLICT {$item['slug']} already has a different primary; skipped");

                    continue;
                }

                $resolved[] = [
                    'article_id' => $article->id,
                    'position' => (int) $item['position'],
                    'is_primary' => (bool) $item['primary'],
                ];
                $this->line(sprintf('ARTICLE MEMBERSHIP %s POSITION %d PRIMARY %s', $item['slug'], $item['position'], $item['primary'] ? 'true' : 'false'));
            }

            $pillarId = null;
            if (! empty($definition['pillar'])) {
                $pillar = Article::query()->where('slug', $definition['pillar'])->first();
                if (! $pillar) {
                    $this->warn("MISSING ARTICLE {$definition['pillar']} (PILLAR not set)");
                } elseif (! collect($resolved)->contains('article_id', $pillar->id)) {
                    $this->warn("CONFLICT pillar {$definition['pillar']} is not an applicable mapped membership; PILLAR not set");
                } else {
                    $pillarId = $pillar->id;
                    $this->line("PILLAR {$definition['pillar']}");
                }
            } else {
                $this->line('SKIP PILLAR — mapping intentionally leaves it unset');
            }

            if (! $apply) {
                continue;
            }

            DB::transaction(function () use ($definition, $cluster, $resolved, $pillarId, $memberships) {
                $cluster ??= ContentCluster::query()->create([
                    'name' => $definition['name'],
                    'slug' => $definition['slug'],
                    'is_active' => false,
                    'sort_order' => 0,
                ]);

                $memberships->applyMapped($cluster, $resolved, $pillarId);
            });
        }

        $this->newLine();
        $this->components->info($apply ? 'Mapping applied safely.' : 'Dry run complete. Re-run with --apply only in an explicitly intended local/test environment.');

        return self::SUCCESS;
    }
}
