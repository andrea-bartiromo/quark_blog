<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\ContentClusterSuggestion;
use App\Models\User;
use App\Services\ContentClusterSuggestionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentClusterPhase2CArticleTest extends TestCase
{
    use DatabaseMigrations;

    public function test_article_create_exact_mapping_creates_pending_suggestion_without_membership(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'automatic-exact', 'is_active' => true]);
        $this->mapping($cluster, [['slug' => 'automatic-candidate', 'position' => 10, 'primary' => false]]);

        $article = $this->article('Automatic candidate', 'fisica');

        $suggestion = $this->suggestion($article, $cluster);
        $this->assertSame(ContentClusterSuggestion::STATUS_PENDING, $suggestion->status);
        $this->assertSame(100, $suggestion->confidence);
        $this->assertContains('Initial mapping versionato: match esatto sullo slug.', $suggestion->reasons);
        $this->assertDatabaseMissing('article_content_cluster', [
            'article_id' => $article->id,
            'content_cluster_id' => $cluster->id,
        ]);
    }

    public function test_article_create_category_evidence_creates_pending_suggestion(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'category-auto', 'is_active' => true]);
        $first = $this->article('Category member one', 'fisica');
        $second = $this->article('Category member two', 'fisica');
        $this->attachDirectly($cluster, $first, 10);
        $this->attachDirectly($cluster, $second, 20);
        config()->set('content-clusters-initial', []);

        $candidate = $this->article('Category automatic candidate', 'fisica');

        $suggestion = $this->suggestion($candidate, $cluster);
        $this->assertSame(ContentClusterSuggestion::STATUS_PENDING, $suggestion->status);
        $this->assertSame(65, $suggestion->confidence);
        $this->assertContains('Categoria fisica: 2 membership editoriali confermate.', $suggestion->reasons);
    }

    public function test_irrelevant_article_and_irrelevant_updates_do_not_create_or_churn_suggestions(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'stable', 'is_active' => true]);
        $this->mapping($cluster, [['slug' => 'stable-candidate', 'position' => 10, 'primary' => false]]);
        $article = $this->article('Stable candidate', 'fisica');
        $suggestion = $this->suggestion($article, $cluster);
        $updatedAt = $suggestion->updated_at;

        $article->update(['title' => 'Title only changed']);
        $article->update(['status' => Article::STATUS_DRAFT]);

        $this->assertSame($updatedAt->toISOString(), $suggestion->fresh()->updated_at->toISOString());

        config()->set('content-clusters-initial', []);
        $irrelevant = $this->article('Unrelated', 'astronomia');
        $this->assertDatabaseMissing('content_cluster_suggestions', ['article_id' => $irrelevant->id]);
    }

    public function test_slug_change_refreshes_exact_mapping(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'slug-refresh', 'is_active' => true]);
        $this->mapping($cluster, [['slug' => 'new-mapped-slug', 'position' => 10, 'primary' => false]]);
        $article = $this->article('Old unmapped slug', 'fisica');

        $this->assertDatabaseMissing('content_cluster_suggestions', [
            'article_id' => $article->id,
            'content_cluster_id' => $cluster->id,
        ]);

        $article->update(['slug' => 'new-mapped-slug']);

        $this->assertSame(ContentClusterSuggestion::STATUS_PENDING, $this->suggestion($article, $cluster)->status);
    }

    public function test_category_change_stales_old_evidence_and_creates_new_evidence(): void
    {
        $oldCluster = ContentCluster::factory()->create(['slug' => 'old-category', 'is_active' => true]);
        $newCluster = ContentCluster::factory()->create(['slug' => 'new-category', 'is_active' => true]);
        $this->seedCategoryMembers($oldCluster, 'fisica', 2);
        $this->seedCategoryMembers($newCluster, 'chimica', 2);
        config()->set('content-clusters-initial', []);
        $article = $this->article('Changing category', 'fisica');
        $oldSuggestion = $this->suggestion($article, $oldCluster);

        $article->update(['category' => 'chimica']);

        $this->assertSame(ContentClusterSuggestion::STATUS_STALE, $oldSuggestion->fresh()->status);
        $this->assertSame(ContentClusterSuggestion::STATUS_PENDING, $this->suggestion($article, $newCluster)->status);
    }

    public function test_draft_and_scheduled_creation_stays_editorial_only(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'planning', 'is_active' => true]);
        $this->mapping($cluster, [
            ['slug' => 'draft-auto', 'position' => 10, 'primary' => false],
            ['slug' => 'scheduled-auto', 'position' => 20, 'primary' => false],
        ]);

        $draft = $this->article('Draft auto', 'fisica', Article::STATUS_DRAFT);
        $scheduled = $this->article('Scheduled auto', 'fisica', Article::STATUS_SCHEDULED);

        $this->assertSame(ContentClusterSuggestion::STATUS_PENDING, $this->suggestion($draft, $cluster)->status);
        $this->assertSame(ContentClusterSuggestion::STATUS_PENDING, $this->suggestion($scheduled, $cluster)->status);
        $this->assertSame(Article::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertSame(Article::STATUS_SCHEDULED, $scheduled->fresh()->status);
        $this->assertDatabaseMissing('article_content_cluster', ['article_id' => $draft->id]);
        $this->assertDatabaseMissing('article_content_cluster', ['article_id' => $scheduled->id]);
    }

    public function test_refresh_is_idempotent_and_does_not_touch_unrelated_suggestions(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'scoped', 'is_active' => true]);
        $this->mapping($cluster, [
            ['slug' => 'scoped-one', 'position' => 10, 'primary' => false],
            ['slug' => 'scoped-two', 'position' => 20, 'primary' => false],
        ]);
        $first = $this->article('Scoped one', 'fisica');
        $second = $this->article('Scoped two', 'fisica');
        $firstSuggestion = $this->suggestion($first, $cluster);
        $secondSuggestion = $this->suggestion($second, $cluster);
        $firstUpdatedAt = $firstSuggestion->updated_at;
        $secondUpdatedAt = $secondSuggestion->updated_at;

        $service = app(ContentClusterSuggestionService::class);
        $service->refreshForArticle($first);
        $service->refreshForArticle($first);

        $this->assertSame(1, ContentClusterSuggestion::query()->where('article_id', $first->id)->count());
        $this->assertSame($firstUpdatedAt->toISOString(), $firstSuggestion->fresh()->updated_at->toISOString());
        $this->assertSame($secondUpdatedAt->toISOString(), $secondSuggestion->fresh()->updated_at->toISOString());
    }

    public function test_rejected_state_is_preserved_for_same_evidence_and_staled_when_evidence_changes(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'rejected-auto', 'is_active' => true]);
        $this->mapping($cluster, [['slug' => 'reject-auto', 'position' => 10, 'primary' => false]]);
        $article = $this->article('Reject auto', 'fisica');
        $service = app(ContentClusterSuggestionService::class);
        $suggestion = $this->suggestion($article, $cluster);
        $service->reject($suggestion, $this->editor());

        $service->refreshForArticle($article);
        $this->assertSame(ContentClusterSuggestion::STATUS_REJECTED, $suggestion->fresh()->status);

        config()->set('content-clusters-initial', []);
        $service->refreshForArticle($article);
        $this->assertSame(ContentClusterSuggestion::STATUS_STALE, $suggestion->fresh()->status);
    }

    public function test_accepted_state_is_not_reopened_and_automatic_trigger_never_assigns_membership(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'accepted-auto', 'is_active' => true]);
        $this->mapping($cluster, [['slug' => 'accepted-auto-candidate', 'position' => 10, 'primary' => false]]);
        $article = $this->article('Accepted auto candidate', 'fisica');
        $service = app(ContentClusterSuggestionService::class);
        $suggestion = $this->suggestion($article, $cluster);

        $this->assertDatabaseMissing('article_content_cluster', [
            'article_id' => $article->id,
            'content_cluster_id' => $cluster->id,
        ]);

        $service->accept($suggestion, $this->editor());
        $service->refreshForArticle($article);

        $this->assertSame(ContentClusterSuggestion::STATUS_ACCEPTED, $suggestion->fresh()->status);
        $this->assertSame(1, ContentClusterSuggestion::query()
            ->where('article_id', $article->id)
            ->where('content_cluster_id', $cluster->id)
            ->count());
    }

    public function test_global_generation_and_article_refresh_produce_identical_combined_evidence(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'canonical', 'is_active' => true]);
        $this->seedCategoryMembers($cluster, 'fisica', 2);
        $this->mapping($cluster, [['slug' => 'canonical-candidate', 'position' => 10, 'primary' => true]]);
        $article = $this->article('Canonical candidate', 'fisica');
        $automatic = $this->suggestion($article, $cluster);
        $automaticSnapshot = [$automatic->evidence_hash, $automatic->confidence, $automatic->reasons, $automatic->suggested_primary];

        $automatic->delete();
        app(ContentClusterSuggestionService::class)->regenerate();
        $global = $this->suggestion($article, $cluster);

        $this->assertSame($automaticSnapshot, [$global->evidence_hash, $global->confidence, $global->reasons, $global->suggested_primary]);
        $this->assertSame(100, $global->confidence);
        $this->assertTrue($global->suggested_primary);
        $this->assertContains('Initial mapping versionato: match esatto sullo slug.', $global->reasons);
        $this->assertContains('Categoria fisica: 2 membership editoriali confermate.', $global->reasons);
    }

    public function test_article_refresh_query_shape_is_independent_of_catalog_size(): void
    {
        $clusterA = ContentCluster::factory()->create(['slug' => 'query-a', 'is_active' => true]);
        ContentCluster::factory()->create(['slug' => 'query-b', 'is_active' => true]);
        $this->mapping($clusterA, [['slug' => 'query-target', 'position' => 10, 'primary' => false]]);
        $target = $this->article('Query target', 'fisica');

        Article::withoutEvents(function (): void {
            for ($i = 0; $i < 100; $i++) {
                $this->article("Unrelated {$i}", 'astronomia');
            }
        });

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ContentClusterSuggestionService::class)->refreshForArticle($target);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, count($queries));
    }

    private function mapping(ContentCluster $cluster, array $articles): void
    {
        config()->set('content-clusters-initial', [[
            'slug' => $cluster->slug,
            'name' => $cluster->name,
            'pillar' => null,
            'articles' => $articles,
        ]]);
    }

    private function seedCategoryMembers(ContentCluster $cluster, string $category, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $article = $this->article("{$cluster->slug} {$category} member {$i}", $category);
            $this->attachDirectly($cluster, $article, $i * 10);
        }
    }

    private function attachDirectly(ContentCluster $cluster, Article $article, int $position): void
    {
        DB::table('article_content_cluster')->insert([
            'article_id' => $article->id,
            'content_cluster_id' => $cluster->id,
            'position' => $position,
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function suggestion(Article $article, ContentCluster $cluster): ContentClusterSuggestion
    {
        return ContentClusterSuggestion::query()
            ->where('article_id', $article->id)
            ->where('content_cluster_id', $cluster->id)
            ->firstOrFail();
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    private function article(string $title, string $category, string $status = Article::STATUS_PUBLISHED): Article
    {
        $user = User::factory()->create();

        return Article::create([
            'user_id' => $user->id,
            'title' => $title,
            'slug' => str($title)->slug(),
            'body' => 'Corpo.',
            'excerpt' => 'Estratto.',
            'category' => $category,
            'status' => $status,
            'read_minutes' => 1,
            'published_at' => $status === Article::STATUS_SCHEDULED
                ? now()->addDay()
                : ($status === Article::STATUS_PUBLISHED ? now()->subDay() : null),
        ]);
    }
}
