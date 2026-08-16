<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\ContentClusterSuggestion;
use App\Models\User;
use App\Services\ContentClusterMembershipService;
use App\Services\ContentClusterSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentClusterPhase2BTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_is_deterministic_idempotent_and_explainable(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'test-path', 'is_active' => true]);
        $article = $this->article('Mapped', 'fisica');
        $this->mapping($cluster, [['slug' => $article->slug, 'position' => 10, 'primary' => false]]);

        $service = app(ContentClusterSuggestionService::class);
        $service->regenerate();
        $service->regenerate();

        $suggestion = ContentClusterSuggestion::firstOrFail();
        $this->assertSame(1, ContentClusterSuggestion::count());
        $this->assertSame(ContentClusterSuggestion::STATUS_PENDING, $suggestion->status);
        $this->assertSame(100, $suggestion->confidence);
        $this->assertContains('Initial mapping versionato: match esatto sullo slug.', $suggestion->reasons);
    }

    public function test_existing_membership_and_inactive_cluster_do_not_generate_actionable_suggestions(): void
    {
        $active = ContentCluster::factory()->create(['slug' => 'active', 'is_active' => true]);
        $inactive = ContentCluster::factory()->create(['slug' => 'inactive', 'is_active' => false]);
        $member = $this->article('Member', 'fisica');
        $inactiveCandidate = $this->article('Inactive candidate', 'chimica');
        $active->articles()->attach($member->id, ['position' => 10, 'is_primary' => false]);
        config()->set('content-clusters-initial', [
            ['slug' => 'active', 'name' => 'Active', 'pillar' => null, 'articles' => [['slug' => $member->slug, 'position' => 10, 'primary' => false]]],
            ['slug' => 'inactive', 'name' => 'Inactive', 'pillar' => null, 'articles' => [['slug' => $inactiveCandidate->slug, 'position' => 10, 'primary' => false]]],
        ]);

        app(ContentClusterSuggestionService::class)->regenerate();

        $this->assertSame(0, ContentClusterSuggestion::count());
    }

    public function test_rejected_suggestion_stays_rejected_until_evidence_changes_then_becomes_stale(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'test-path', 'is_active' => true]);
        $article = $this->article('Reject me', 'fisica');
        $editor = $this->editor();
        $this->mapping($cluster, [['slug' => $article->slug, 'position' => 10, 'primary' => false]]);
        $service = app(ContentClusterSuggestionService::class);
        $service->regenerate();
        $suggestion = ContentClusterSuggestion::firstOrFail();

        $service->reject($suggestion, $editor);
        $service->regenerate();
        $this->assertSame(ContentClusterSuggestion::STATUS_REJECTED, $suggestion->fresh()->status);

        config()->set('content-clusters-initial', []);
        $service->regenerate();
        $this->assertSame(ContentClusterSuggestion::STATUS_STALE, $suggestion->fresh()->status);
    }

    public function test_acceptance_uses_membership_service_and_is_idempotent(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'test-path', 'is_active' => true]);
        $article = $this->article('Accept me', 'fisica');
        $editor = $this->editor();
        $this->mapping($cluster, [['slug' => $article->slug, 'position' => 10, 'primary' => false]]);
        $service = app(ContentClusterSuggestionService::class);
        $service->regenerate();
        $suggestion = ContentClusterSuggestion::firstOrFail();

        $service->accept($suggestion, $editor);
        $service->accept($suggestion->fresh(), $editor);

        $this->assertDatabaseHas('article_content_cluster', ['article_id' => $article->id, 'content_cluster_id' => $cluster->id, 'is_primary' => false]);
        $this->assertSame(1, DB::table('article_content_cluster')->where('article_id', $article->id)->where('content_cluster_id', $cluster->id)->count());
        $this->assertSame(ContentClusterSuggestion::STATUS_ACCEPTED, $suggestion->fresh()->status);
    }

    public function test_category_only_suggestion_can_be_accepted_with_matching_pair_evidence(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'category-path', 'is_active' => true]);
        $firstMember = $this->article('Category member one', 'fisica');
        $secondMember = $this->article('Category member two', 'fisica');
        $candidate = $this->article('Category candidate', 'fisica');
        $editor = $this->editor();

        $cluster->articles()->attach($firstMember->id, [
            'position' => 10,
            'is_primary' => false,
        ]);
        $cluster->articles()->attach($secondMember->id, [
            'position' => 20,
            'is_primary' => false,
        ]);

        config()->set('content-clusters-initial', []);

        $service = app(ContentClusterSuggestionService::class);
        $service->regenerate();

        $suggestion = ContentClusterSuggestion::query()
            ->where('article_id', $candidate->id)
            ->where('content_cluster_id', $cluster->id)
            ->firstOrFail();

        $this->assertContains(
            'Categoria fisica: 2 membership editoriali confermate.',
            $suggestion->reasons
        );

        $service->accept($suggestion, $editor);

        $this->assertDatabaseHas('article_content_cluster', [
            'article_id' => $candidate->id,
            'content_cluster_id' => $cluster->id,
            'is_primary' => false,
        ]);

        $this->assertSame(
            ContentClusterSuggestion::STATUS_ACCEPTED,
            $suggestion->fresh()->status
        );
    }

    public function test_changed_category_evidence_marks_pending_suggestion_stale_on_acceptance(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'category-stale', 'is_active' => true]);
        $firstMember = $this->article('Stale member one', 'fisica');
        $secondMember = $this->article('Stale member two', 'fisica');
        $candidate = $this->article('Stale candidate', 'fisica');
        $editor = $this->editor();

        $cluster->articles()->attach($firstMember->id, [
            'position' => 10,
            'is_primary' => false,
        ]);
        $cluster->articles()->attach($secondMember->id, [
            'position' => 20,
            'is_primary' => false,
        ]);

        config()->set('content-clusters-initial', []);

        $service = app(ContentClusterSuggestionService::class);
        $service->regenerate();

        $suggestion = ContentClusterSuggestion::query()
            ->where('article_id', $candidate->id)
            ->where('content_cluster_id', $cluster->id)
            ->firstOrFail();

        $secondMember->update(['category' => 'chimica']);

        try {
            $service->accept($suggestion, $editor);
            $this->fail('Expected changed evidence to mark suggestion stale.');
        } catch (ValidationException) {
            $this->assertSame(
                ContentClusterSuggestion::STATUS_STALE,
                $suggestion->fresh()->status
            );

            $this->assertDatabaseMissing('article_content_cluster', [
                'article_id' => $candidate->id,
                'content_cluster_id' => $cluster->id,
            ]);
        }
    }

    public function test_primary_conflict_is_fail_safe_and_never_overwrites_existing_primary(): void
    {
        $existing = ContentCluster::factory()->create(['slug' => 'existing', 'is_active' => true]);
        $suggested = ContentCluster::factory()->create(['slug' => 'suggested', 'is_active' => true]);
        $article = $this->article('Primary conflict', 'fisica');
        $editor = $this->editor();
        $existing->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        $this->mapping($suggested, [['slug' => $article->slug, 'position' => 10, 'primary' => true]]);
        $service = app(ContentClusterSuggestionService::class);
        $service->regenerate();
        $suggestion = ContentClusterSuggestion::where('content_cluster_id', $suggested->id)->firstOrFail();

        try {
            $service->accept($suggestion, $editor);
            $this->fail('Expected primary conflict.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('article_content_cluster', ['article_id' => $article->id, 'content_cluster_id' => $existing->id, 'is_primary' => true]);
            $this->assertDatabaseMissing('article_content_cluster', ['article_id' => $article->id, 'content_cluster_id' => $suggested->id]);
        }
    }

    public function test_scheduled_and_draft_articles_can_be_suggested_without_becoming_public(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'planning', 'is_active' => true]);
        $scheduled = $this->article('Scheduled suggestion', 'fisica', Article::STATUS_SCHEDULED);
        $draft = $this->article('Draft suggestion', 'fisica', Article::STATUS_DRAFT);
        $this->mapping($cluster, [
            ['slug' => $scheduled->slug, 'position' => 10, 'primary' => false],
            ['slug' => $draft->slug, 'position' => 20, 'primary' => false],
        ]);

        app(ContentClusterSuggestionService::class)->regenerate();

        $this->assertSame(2, ContentClusterSuggestion::where('status', ContentClusterSuggestion::STATUS_PENDING)->count());
        $this->assertSame(Article::STATUS_SCHEDULED, $scheduled->fresh()->status);
        $this->assertSame(Article::STATUS_DRAFT, $draft->fresh()->status);
    }

    public function test_large_catalog_is_paginated_searchable_and_does_not_render_full_catalog_metadata(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create();
        $selected = $this->article('Selected member', 'fisica');
        app(ContentClusterMembershipService::class)->sync($cluster, [['article_id' => $selected->id, 'position' => 10]], null);
        for ($i = 1; $i <= 65; $i++) {
            $this->article(sprintf('Catalog %03d', $i), $i % 2 ? 'fisica' : 'chimica');
        }

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));
        $response->assertOk()->assertViewHas('catalog', fn ($catalog) => $catalog->perPage() === 30 && $catalog->count() === 30);
        $response->assertSee('name="membership_ids[]" value="'.$selected->id.'"', false);
        $unselected = Article::where('title', 'Catalog 001')->firstOrFail();
        $response->assertDontSee('name="memberships['.$unselected->id.'][position]"', false);

        $filtered = $this->actingAs($editor)->get(route('admin.content-clusters.edit', [$cluster, 'q' => 'Catalog 065', 'category' => 'fisica']));
        $filtered->assertOk()->assertViewHas('catalog', fn ($catalog) => $catalog->count() === 1 && $catalog->first()->title === 'Catalog 065');
    }

    public function test_metadata_update_without_membership_payload_never_removes_memberships(): void
    {
        $editor = $this->editor();
        $article = $this->article('Persistent member', 'fisica');
        $cluster = ContentCluster::factory()->create(['name' => 'Before', 'slug' => 'before']);
        app(ContentClusterMembershipService::class)->sync($cluster, [['article_id' => $article->id]], null);

        $this->actingAs($editor)->put(route('admin.content-clusters.update', $cluster), ['name' => 'After', 'slug' => 'after'])->assertRedirect();

        $this->assertDatabaseHas('article_content_cluster', ['article_id' => $article->id, 'content_cluster_id' => $cluster->id]);
    }

    public function test_analytics_contract_contains_only_non_personal_metadata_and_expected_events(): void
    {
        $this->assertSame([
            'path_view', 'path_next_click', 'path_previous_click', 'path_view_all_click', 'second_reading',
        ], config('content-clusters-analytics.events'));
        $metadata = config('content-clusters-analytics.metadata');
        $this->assertContains('article_id', $metadata);
        $this->assertNotContains('email', $metadata);
        $this->assertNotContains('ip', $metadata);
        $this->assertNotContains('user_agent', $metadata);
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
            'published_at' => $status === Article::STATUS_SCHEDULED ? now()->addDay() : ($status === Article::STATUS_PUBLISHED ? now()->subDay() : null),
        ]);
    }
}
