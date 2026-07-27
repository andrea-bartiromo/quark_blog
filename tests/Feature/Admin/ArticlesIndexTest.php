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

    // La ricerca "q" filtra realmente l'elenco invece di essere ignorata.
    public function test_search_filters_articles_by_title(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Come proteggere le api']);
        $this->article($editor, ['title' => 'Fotovoltaico organico']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['q' => 'proteggere le api']));

        $response->assertOk();
        $response->assertSee('Come proteggere le api');
        $response->assertDontSee('Fotovoltaico organico');
    }

    public function test_search_matches_excerpt_and_body_too(): void
    {
        $editor = $this->editor();
        $this->article($editor, [
            'title' => 'Articolo con parola nel sommario',
            'excerpt' => 'Contiene la parola criptovaluta nel sommario',
        ]);
        $this->article($editor, [
            'title' => 'Articolo con parola nel corpo',
            'body' => 'Il testo parla di criptovaluta nel corpo articolo.',
        ]);
        $this->article($editor, ['title' => 'Articolo non pertinente']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['q' => 'criptovaluta']));

        $response->assertOk();
        $response->assertSee('Articolo con parola nel sommario');
        $response->assertSee('Articolo con parola nel corpo');
        $response->assertDontSee('Articolo non pertinente');
    }

    public function test_search_with_no_matches_returns_an_empty_list_without_error(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Articolo esistente']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['q' => 'termine-inesistente-xyz']));

        $response->assertOk();
        $response->assertDontSee('Articolo esistente');
    }

    // Senza parametro "q" il comportamento resta quello di sempre: elenco completo.
    public function test_empty_search_shows_all_articles_as_before(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Primo articolo']);
        $this->article($editor, ['title' => 'Secondo articolo']);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('Primo articolo');
        $response->assertSee('Secondo articolo');
    }
}
