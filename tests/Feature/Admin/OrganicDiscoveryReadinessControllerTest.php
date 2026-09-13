<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Cantiere 3 (programma "Kairus Organic Discovery"). Verifica soprattutto
 * il requisito non negoziabile: nessun contenuto non pubblico (bozza,
 * programmato) deve mai comparire nella vista aggregata o essere
 * raggiungibile dalla vista di dettaglio.
 */
class OrganicDiscoveryReadinessControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_the_aggregate_page(): void
    {
        $this->get(route('admin.organic-discovery-readiness'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_view_the_detail_page(): void
    {
        $article = $this->publishedArticle();

        $this->get(route('admin.organic-discovery-readiness.show', $article))->assertRedirect(route('login'));
    }

    public function test_editor_sees_the_aggregate_page_with_state_counts_and_article_link(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $article = $this->publishedArticle();

        $response = $this->actingAs($editor)->get(route('admin.organic-discovery-readiness'));

        $response->assertOk();
        $response->assertSee('Ricerca organica');
        $response->assertSee($article->title);
    }

    public function test_the_aggregate_page_executes_the_expensive_audit_exactly_once(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->mock(OrganicDiscoveryReadinessService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('auditAll')->once()->andReturn(collect());
        });

        $this->actingAs($editor)
            ->get(route('admin.organic-discovery-readiness'))
            ->assertOk()
            ->assertSee('Nessun articolo pubblico da analizzare.');
    }

    public function test_editor_sees_the_detail_page_with_findings_and_actions(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $article = $this->publishedArticle();

        $response = $this->actingAs($editor)->get(route('admin.organic-discovery-readiness.show', $article));

        $response->assertOk();
        $response->assertSee($article->title);
        $response->assertSee('SEARCH_PROFILE_MISSING');
        $response->assertSee('Compilare almeno la query primaria', false);
    }

    public function test_a_draft_article_detail_page_is_not_found(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $draft = $this->publishedArticle(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $this->actingAs($editor)
            ->get(route('admin.organic-discovery-readiness.show', $draft))
            ->assertNotFound();
    }

    public function test_a_scheduled_article_detail_page_is_not_found(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $scheduled = $this->publishedArticle(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);

        $this->actingAs($editor)
            ->get(route('admin.organic-discovery-readiness.show', $scheduled))
            ->assertNotFound();
    }

    public function test_a_draft_article_never_appears_on_the_aggregate_page(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $draft = $this->publishedArticle(['slug' => 'organic-controller-draft', 'status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $this->actingAs($editor)
            ->get(route('admin.organic-discovery-readiness'))
            ->assertOk()
            ->assertDontSee($draft->title);
    }

    private function publishedArticle(array $overrides = []): Article
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'organic-controller-category'],
            ['name' => 'Organic Controller Category'],
        );

        // Nessun profilo di ricerca creato di proposito: SEARCH_PROFILE_MISSING
        // deve comparire nella pagina di dettaglio senza alcun setup aggiuntivo.
        return Article::withoutEvents(fn () => Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo ricerca organica admin',
            'slug' => 'articolo-ricerca-organica-admin',
            'excerpt' => 'Excerpt',
            'body' => '<p>Body</p>',
            'category' => $category->slug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 1,
        ], $overrides)));
    }
}
