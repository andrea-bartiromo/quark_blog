<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticlesIndexTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(User $user, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => 'Testo di prova.',
            'category' => 'scienza',
            'cover_image' => null,
            'status' => 'draft',
            'read_minutes' => 2,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    // La riga compatta conserva titolo, copertina, stato e tutte le azioni esistenti.
    public function test_compact_row_preserves_title_thumbnail_status_and_actions(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor, ['title' => 'Titolo visibile nella riga compatta']);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('Titolo visibile nella riga compatta');
        $response->assertSee('article-thumb', false);
        $response->assertSee('articles-table', false);
        $response->assertSee('draft');
        $response->assertSee(route('admin.articles.edit', $article), false);
        $response->assertSee(route('articolo', $article->slug), false);
        $response->assertSee(route('admin.articles.destroy', $article), false);
        $response->assertSee('Modifica');
        $response->assertSee('Vedi');
        $response->assertSee('Elimina');
    }

    // Nessuna regressione sul fallback di copertina quando l'articolo non ne ha una.
    public function test_missing_cover_image_falls_back_to_the_default_placeholder(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['cover_image' => null]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('placeholder-1.jpg', false);
    }

    public function test_author_cannot_access_the_admin_articles_list(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $response = $this->actingAs($author)->get(route('admin.articles'));

        $response->assertRedirect(route('redazione.dashboard'));
    }
}
