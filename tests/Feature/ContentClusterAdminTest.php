<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContentClusterMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentClusterAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_content_cluster_admin(): void
    {
        $this->get(route('admin.content-clusters.index'))->assertRedirect(route('login'));
    }

    public function test_editor_can_create_cluster_with_membership_pillar_and_ordering(): void
    {
        $editor = $this->editor();
        $first = $this->article($editor, 'Primo articolo');
        $second = $this->article($editor, 'Secondo articolo', Article::STATUS_SCHEDULED);

        $response = $this->actingAs($editor)->post(route('admin.content-clusters.store'), [
            'name' => 'IA spiegata',
            'slug' => '',
            'is_active' => '1',
            'sort_order' => 2,
            'pillar_article_id' => $first->id,
            'memberships' => [
                ['selected' => '1', 'article_id' => $second->id, 'position' => 50],
                ['selected' => '1', 'article_id' => $first->id, 'position' => 10, 'is_primary' => '1'],
            ],
        ]);

        $cluster = ContentCluster::where('slug', 'ia-spiegata')->firstOrFail();
        $response->assertRedirect(route('admin.content-clusters.edit', $cluster));
        $this->assertTrue($cluster->is_active);
        $this->assertSame($first->id, $cluster->pillar_article_id);
        $this->assertDatabaseHas('article_content_cluster', ['content_cluster_id' => $cluster->id, 'article_id' => $first->id, 'position' => 10, 'is_primary' => true]);
        $this->assertDatabaseHas('article_content_cluster', ['content_cluster_id' => $cluster->id, 'article_id' => $second->id, 'position' => 20]);
    }

    public function test_pillar_must_be_a_member_and_failed_create_is_atomic(): void
    {
        $editor = $this->editor();
        $pillar = $this->article($editor, 'Pillar');

        $this->actingAs($editor)->from(route('admin.content-clusters.create'))->post(route('admin.content-clusters.store'), [
            'name' => 'Spazio',
            'pillar_article_id' => $pillar->id,
            'memberships' => [],
        ])->assertSessionHasErrors('pillar_article_id');

        $this->assertDatabaseMissing('content_clusters', ['slug' => 'spazio']);
    }

    public function test_primary_cluster_is_unique_per_article_and_moves_transactionally(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor, 'Articolo condiviso');
        $a = ContentCluster::factory()->create(['name' => 'A', 'slug' => 'a']);
        $b = ContentCluster::factory()->create(['name' => 'B', 'slug' => 'b']);
        $service = app(ContentClusterMembershipService::class);

        $service->sync($a, [['article_id' => $article->id, 'position' => 1, 'is_primary' => true]], null);
        $service->sync($b, [['article_id' => $article->id, 'position' => 1, 'is_primary' => true]], null);

        $this->assertDatabaseHas('article_content_cluster', ['article_id' => $article->id, 'content_cluster_id' => $a->id, 'is_primary' => false]);
        $this->assertDatabaseHas('article_content_cluster', ['article_id' => $article->id, 'content_cluster_id' => $b->id, 'is_primary' => true]);
        $this->assertSame(1, \DB::table('article_content_cluster')->where('article_id', $article->id)->where('is_primary', true)->count());
    }

    public function test_deleting_article_cascades_membership_and_nulls_pillar(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor, 'Pillar eliminabile');
        $cluster = ContentCluster::factory()->create();
        app(ContentClusterMembershipService::class)->sync($cluster, [['article_id' => $article->id, 'is_primary' => false]], $article->id);

        $article->delete();

        $this->assertDatabaseMissing('article_content_cluster', ['article_id' => $article->id]);
        $this->assertNull($cluster->fresh()->pillar_article_id);
    }

    public function test_slug_is_unique_but_current_slug_is_valid_on_update(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create(['name' => 'IA', 'slug' => 'ia']);
        ContentCluster::factory()->create(['name' => 'Spazio', 'slug' => 'spazio']);

        $this->actingAs($editor)->put(route('admin.content-clusters.update', $cluster), ['name' => 'IA aggiornata', 'slug' => 'ia'])->assertSessionHasNoErrors();
        $this->actingAs($editor)->from(route('admin.content-clusters.edit', $cluster))->put(route('admin.content-clusters.update', $cluster), ['name' => 'Collisione', 'slug' => 'spazio'])->assertSessionHasErrors('slug');
    }

    public function test_active_and_inactive_scopes_are_distinct_from_article_publication_state(): void
    {
        ContentCluster::factory()->create(['is_active' => true]);
        ContentCluster::factory()->create(['is_active' => false]);

        $this->assertSame(1, ContentCluster::active()->count());
        $this->assertSame(1, ContentCluster::inactive()->count());
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    private function article(User $author, string $title, string $status = Article::STATUS_PUBLISHED): Article
    {
        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => str($title)->slug(),
            'body' => 'Corpo articolo di test.',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => $status,
            'read_minutes' => 1,
            'published_at' => $status === Article::STATUS_SCHEDULED ? now()->addDay() : now()->subDay(),
        ]);
    }
}
