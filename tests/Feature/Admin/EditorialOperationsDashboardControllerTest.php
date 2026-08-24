<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Mission 09 — Editorial Operations Dashboard V1 Convergence.
 * HTTP-level: autorizzazione, rendering, e assenza di qualunque esposizione
 * pubblica della pagina (stessa disciplina "no accidental public route"
 * già applicata al Content Graph nella Mission 06).
 */
class EditorialOperationsDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    private function author(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'author'])->save();

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.editorial-operations'))->assertRedirect(route('login'));
    }

    public function test_author_role_cannot_reach_the_dashboard(): void
    {
        $author = $this->author();

        $this->actingAs($author)
            ->get(route('admin.editorial-operations'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_can_view_the_empty_state(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.editorial-operations'));

        $response->assertOk();
        $response->assertSee('Operazioni editoriali');
        $response->assertSee('Nessun articolo programmato in attesa.');
    }

    public function test_editor_sees_a_scheduled_article_in_the_da_pubblicare_section(): void
    {
        $editor = $this->editor();
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo programmato dashboard',
            'slug' => 'articolo-programmato-dashboard',
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => Article::STATUS_SCHEDULED,
            'read_minutes' => 2,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.editorial-operations'));

        $response->assertOk();
        $response->assertSee('Articolo programmato dashboard');
        $response->assertSee(route('admin.articles.edit', $article));
    }

    public function test_the_dashboard_route_is_not_reachable_without_authentication_and_is_not_registered_outside_the_editor_gate(): void
    {
        $route = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === 'admin.editorial-operations');

        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('editor', $route->gatherMiddleware());
        $this->assertStringStartsWith('admin/', $route->uri());
    }

    public function test_the_page_performs_no_mutation_no_matter_how_many_times_it_is_viewed(): void
    {
        $editor = $this->editor();
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo invariato dalla dashboard',
            'slug' => 'articolo-invariato-dashboard',
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 2,
            'published_at' => now()->subDay(),
        ]);
        $before = $article->refresh()->getAttributes();

        $this->actingAs($editor)->get(route('admin.editorial-operations'));
        $this->actingAs($editor)->get(route('admin.editorial-operations'));

        $this->assertSame($before, Article::find($article->id)->getAttributes());
    }
}
