<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    // ── Indicatore collegamenti interni (solo articoli programmati) ─────

    private function scheduledArticle(User $user, array $overrides = []): Article
    {
        return $this->article($user, array_merge([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ], $overrides));
    }

    public function test_scheduled_article_with_no_links_shows_a_zero_badge(): void
    {
        $editor = $this->editor();
        $this->scheduledArticle($editor, ['body' => '<p>Nessun link qui.</p>']);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('🔗 0', false);
    }

    public function test_scheduled_article_with_one_internal_link_shows_the_count(): void
    {
        $editor = $this->editor();
        $target = $this->article($editor, ['status' => 'published', 'published_at' => now()->subDay()]);
        $this->scheduledArticle($editor, [
            'body' => '<p>Vedi <a href="'.route('articolo', $target->slug).'">questo</a>.</p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('🔗 1', false);
    }

    public function test_scheduled_article_with_several_internal_links_shows_the_correct_count(): void
    {
        $editor = $this->editor();
        $this->scheduledArticle($editor, [
            'body' => '<p><a href="/articolo/a">A</a> <a href="/articolo/b">B</a> <a href="/articolo/c">C</a></p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('🔗 3', false);
    }

    public function test_scheduled_article_with_only_external_links_shows_a_zero_badge(): void
    {
        $editor = $this->editor();
        $this->scheduledArticle($editor, [
            'body' => '<p>Vedi <a href="https://www.wikipedia.org">Wikipedia</a>.</p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('🔗 0', false);
    }

    public function test_scheduled_article_with_malformed_html_does_not_break_the_admin_page(): void
    {
        $editor = $this->editor();
        $this->scheduledArticle($editor, [
            'body' => '<p>Testo <a href="/articolo/rotto">link non chiuso <strong>tag annidati sbagliati</p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('🔗 1', false);
    }

    public function test_published_non_scheduled_article_shows_no_links_badge(): void
    {
        $editor = $this->editor();
        $this->article($editor, [
            'status' => 'published',
            'published_at' => now()->subDay(),
            'body' => '<p>Vedi <a href="/articolo/qualcosa">qualcosa</a>.</p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertDontSee('🔗', false);
    }

    public function test_draft_article_shows_no_links_badge(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['status' => 'draft']);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertDontSee('🔗', false);
    }

    // ── Decisione prodotto B: "Collegamenti ad articoli" (non link interni generici) ──

    public function test_a_link_to_a_category_page_is_not_counted(): void
    {
        $editor = $this->editor();
        $this->scheduledArticle($editor, [
            'body' => '<p>Vedi la <a href="/categoria/spazio">categoria Spazio</a>.</p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('🔗 0', false);
    }

    public function test_a_link_to_the_homepage_is_not_counted(): void
    {
        $editor = $this->editor();
        $this->scheduledArticle($editor, [
            'body' => '<p>Torna alla <a href="/">home</a>.</p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('🔗 0', false);
    }

    public function test_an_absolute_same_domain_article_url_is_counted(): void
    {
        $editor = $this->editor();
        $target = $this->article($editor, ['status' => 'published', 'published_at' => now()->subDay()]);
        $this->scheduledArticle($editor, [
            'body' => '<p>Vedi <a href="'.route('articolo', $target->slug).'">questo</a> (URL assoluto).</p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('🔗 1', false);
    }

    public function test_a_hyphenated_slug_is_counted_correctly(): void
    {
        $editor = $this->editor();
        $this->scheduledArticle($editor, [
            'body' => '<p><a href="/articolo/come-funziona-il-sistema-solare-2026">Link</a></p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('🔗 1', false);
    }

    public function test_the_same_article_linked_twice_counts_as_one_distinct_article(): void
    {
        // Decisione documentata: il badge conta ARTICOLI DISTINTI
        // raggiunti, non il numero grezzo di tag <a> — due link allo
        // stesso articolo non sono "due collegamenti" ai fini della
        // domanda editoriale "ho già collegato altri articoli Kairus?".
        $editor = $this->editor();
        $this->scheduledArticle($editor, [
            'body' => '<p><a href="/articolo/stesso-pezzo">Prima menzione</a> e più avanti <a href="/articolo/stesso-pezzo">di nuovo lo stesso</a>.</p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('🔗 1', false);
    }

    public function test_an_article_with_no_body_shows_a_zero_badge_without_error(): void
    {
        $editor = $this->editor();
        $this->scheduledArticle($editor, ['body' => '']);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('🔗 0', false);
    }

    public function test_link_text_containing_html_is_escaped_in_the_badge_output(): void
    {
        // Il badge stampa solo il conteggio numerico (mai testo derivato
        // dal body): verifica che il rendering Blade resti escapato come
        // sempre, anche quando il testo del link contiene markup.
        $editor = $this->editor();
        $this->scheduledArticle($editor, [
            'body' => '<p><a href="/articolo/xss-test">&lt;script&gt;alert(1)&lt;/script&gt;</a></p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('🔗 1', false);
    }

    public function test_the_articles_list_query_count_does_not_grow_with_the_number_of_scheduled_articles(): void
    {
        $editor = $this->editor();
        $this->scheduledArticle($editor, ['body' => '<p><a href="/articolo/a">A</a></p>']);

        // Un solo listener registrato per l'intero test (mai uno per fase,
        // che si accumulerebbe e farebbe contare ogni query più volte).
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->actingAs($editor)->get(route('admin.articles'));
        $queriesForOne = $count;

        for ($i = 0; $i < 4; $i++) {
            $this->scheduledArticle($editor, ['body' => '<p><a href="/articolo/x'.$i.'">X</a></p>']);
        }

        $count = 0;
        $this->actingAs($editor)->get(route('admin.articles'));
        $queriesForFive = $count;

        $this->assertSame($queriesForOne, $queriesForFive, 'Il conteggio "collegamenti ad articoli" non deve aggiungere query per riga: nessuna query in più con 5 articoli programmati invece di 1.');
    }
}
