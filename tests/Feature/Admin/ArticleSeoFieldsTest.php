<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSeoFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => 'Corpo articolo di prova.',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    private function seoPayload(): array
    {
        return [
            'seo_title' => 'SEO title di prova',
            'seo_description' => 'SEO description di prova',
            'canonical_url' => 'https://example.com/originale',
            'robots' => 'noindex,follow',
            'og_title' => 'OG title di prova',
            'og_description' => 'OG description di prova',
            'og_image' => 'og-image.jpg',
            'twitter_title' => 'Twitter title di prova',
            'twitter_description' => 'Twitter description di prova',
            'twitter_image' => 'twitter-image.jpg',
        ];
    }

    // 1. Salvataggio campi SEO — Admin, creazione
    public function test_admin_can_create_an_article_with_full_seo_fields(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), array_merge([
            'title' => 'Nuovo articolo con SEO',
            'body' => 'Corpo articolo di prova.',
            'category' => 'intelligenza-artificiale',
            'status' => 'draft',
        ], $this->seoPayload()));

        $response->assertRedirect(route('admin.articles'));

        $article = Article::where('title', 'Nuovo articolo con SEO')->firstOrFail();
        $this->assertSame('SEO title di prova', $article->seo_title);
        $this->assertSame('SEO description di prova', $article->seo_description);
        $this->assertSame('https://example.com/originale', $article->canonical_url);
        $this->assertSame('noindex,follow', $article->robots);
        $this->assertSame('OG title di prova', $article->og_title);
        $this->assertSame('OG description di prova', $article->og_description);
        $this->assertSame('og-image.jpg', $article->og_image);
        $this->assertSame('Twitter title di prova', $article->twitter_title);
        $this->assertSame('Twitter description di prova', $article->twitter_description);
        $this->assertSame('twitter-image.jpg', $article->twitter_image);
    }

    // 1b. Salvataggio campi SEO — Admin, aggiornamento
    public function test_admin_can_update_seo_fields(): void
    {
        $editor = $this->editor();
        $article = $this->publishedArticle($editor);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $article), array_merge([
            'title' => $article->title,
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
        ], $this->seoPayload()));

        $response->assertRedirect(route('admin.articles'));

        $article->refresh();
        $this->assertSame('SEO title di prova', $article->seo_title);
        $this->assertSame('noindex,follow', $article->robots);
    }

    // 1c. Salvataggio campi SEO — Redazione (stesso set di campi, stesso comportamento)
    public function test_redazione_author_can_save_seo_fields_too(): void
    {
        $author = $this->author();

        $response = $this->actingAs($author)->post(route('redazione.articles.store'), array_merge([
            'title' => 'Articolo redazione con SEO',
            'body' => 'Corpo articolo di prova.',
            'category' => 'intelligenza-artificiale',
        ], $this->seoPayload()));

        $response->assertRedirect(route('redazione.articles'));

        $article = Article::where('title', 'Articolo redazione con SEO')->firstOrFail();
        $this->assertSame('SEO title di prova', $article->seo_title);
        $this->assertSame('noindex,follow', $article->robots);
        $this->assertSame('twitter-image.jpg', $article->twitter_image);
    }

    // 2. I valori esistenti sono precompilati nel form di modifica
    public function test_seo_fields_are_prefilled_in_the_admin_edit_form(): void
    {
        $editor = $this->editor();
        $article = $this->publishedArticle($editor, [
            'seo_title' => 'Titolo SEO esistente',
            'canonical_url' => 'https://example.com/esistente',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('value="Titolo SEO esistente"', false);
        $response->assertSee('value="https://example.com/esistente"', false);
    }

    // 3. Nessun duplicato: l'aggiornamento modifica lo stesso record
    public function test_updating_seo_fields_does_not_create_a_duplicate_article(): void
    {
        $editor = $this->editor();
        $article = $this->publishedArticle($editor);

        $this->assertSame(1, Article::count());

        $this->actingAs($editor)->put(route('admin.articles.update', $article), array_merge([
            'title' => $article->title,
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
        ], $this->seoPayload()));

        $this->assertSame(1, Article::count());
    }

    // 4. Validazione: lunghezza SEO title
    public function test_seo_title_over_the_allowed_length_is_rejected(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Articolo con SEO title troppo lungo',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'draft',
            'seo_title' => str_repeat('a', 71),
        ]);

        $response->assertSessionHasErrors('seo_title');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo con SEO title troppo lungo']);
    }

    // 5. Validazione: lunghezza SEO description
    public function test_seo_description_over_the_allowed_length_is_rejected(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Articolo con SEO description troppo lunga',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'draft',
            'seo_description' => str_repeat('a', 201),
        ]);

        $response->assertSessionHasErrors('seo_description');
    }

    // 6. Validazione: URL canonico non valido
    public function test_invalid_canonical_url_is_rejected(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Articolo con canonical non valido',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'draft',
            'canonical_url' => 'non-e-un-url',
        ]);

        $response->assertSessionHasErrors('canonical_url');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo con canonical non valido']);
    }

    public function test_empty_canonical_url_is_accepted(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Articolo senza canonical',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'draft',
            'canonical_url' => '',
        ]);

        $response->assertSessionDoesntHaveErrors();
    }

    // 7. Validazione: robots consentiti
    public function test_invalid_robots_value_is_rejected(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Articolo con robots non valido',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'draft',
            'robots' => 'qualcosa,a-caso',
        ]);

        $response->assertSessionHasErrors('robots');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo con robots non valido']);
    }

    public function test_each_allowed_robots_combination_is_accepted(): void
    {
        $editor = $this->editor();

        foreach (Article::robotsOptions() as $index => $combination) {
            $title = 'Articolo robots valido '.$index;

            $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
                'title' => $title,
                'body' => 'Corpo articolo di prova.',
                'category' => 'energia',
                'status' => 'draft',
                'robots' => $combination,
            ]);

            $response->assertSessionDoesntHaveErrors();
            $this->assertSame($combination, Article::where('title', $title)->value('robots'));
        }
    }

    // 8. Tutti i campi SEO sono facoltativi
    public function test_seo_fields_are_all_nullable(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Articolo senza campi SEO',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'draft',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $article = Article::where('title', 'Articolo senza campi SEO')->firstOrFail();
        $this->assertNull($article->seo_title);
        $this->assertNull($article->seo_description);
        $this->assertNull($article->canonical_url);
        $this->assertNull($article->robots);
        $this->assertNull($article->og_title);
        $this->assertNull($article->og_description);
        $this->assertNull($article->og_image);
        $this->assertNull($article->twitter_title);
        $this->assertNull($article->twitter_description);
        $this->assertNull($article->twitter_image);
    }

    // 9. Presenza della sezione SEO nel form (regressione UI)
    public function test_seo_section_is_present_in_the_admin_form(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get(route('admin.articles.create'))
            ->assertOk()
            ->assertSee('SEO title')
            ->assertSee('SEO description')
            ->assertSee('Open Graph')
            ->assertSee('Twitter Card')
            ->assertSee('js-char-counter', false);
    }

    // 10. Invariante: aggiornare il titolo non deve mai modificare un SEO
    //     title già personalizzato — il controller scrive solo il valore
    //     inviato nel campo dedicato, mai un fallback calcolato
    public function test_updating_the_title_does_not_change_an_existing_custom_seo_title(): void
    {
        $editor = $this->editor();
        $article = $this->publishedArticle($editor, ['seo_title' => 'SEO title personalizzato']);

        $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => 'Titolo completamente nuovo',
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
            'seo_title' => $article->seo_title,
        ]);

        $article->refresh();
        $this->assertSame('Titolo completamente nuovo', $article->title);
        $this->assertSame('SEO title personalizzato', $article->seo_title);
    }

    // 11. Stessa invariante per il sommario e la SEO description
    public function test_updating_the_excerpt_does_not_change_an_existing_custom_seo_description(): void
    {
        $editor = $this->editor();
        $article = $this->publishedArticle($editor, [
            'excerpt' => 'Sommario originale',
            'seo_description' => 'SEO description personalizzata',
        ]);

        $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'excerpt' => 'Sommario completamente nuovo',
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
            'seo_description' => $article->seo_description,
        ]);

        $article->refresh();
        $this->assertSame('Sommario completamente nuovo', $article->excerpt);
        $this->assertSame('SEO description personalizzata', $article->seo_description);
    }

    // 12. UX (FASE 4): pulsanti "Ripristina automatico" presenti per i campi
    //     con catena di fallback, in entrambe le aree (Admin e Redazione)
    public function test_admin_form_shows_restore_automatic_controls_for_fallback_fields(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.articles.create'));

        $response->assertOk();
        foreach (['seo_title', 'seo_description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'] as $field) {
            $response->assertSee('data-reset-for="'.$field.'"', false);
        }
    }

    public function test_redazione_form_shows_restore_automatic_controls_for_fallback_fields(): void
    {
        $response = $this->actingAs($this->author())->get(route('redazione.articles.create'));

        $response->assertOk();
        foreach (['seo_title', 'seo_description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'] as $field) {
            $response->assertSee('data-reset-for="'.$field.'"', false);
        }
    }

    // 13. Canonical: l'anteprima (placeholder) mostra l'URL naturale
    //     dell'articolo solo in modifica — mai scritto nel DB (FASE 2G)
    public function test_canonical_url_placeholder_shows_the_articles_natural_url_when_editing(): void
    {
        $editor = $this->editor();
        $article = $this->publishedArticle($editor, ['canonical_url' => null]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('placeholder="'.$article->metaCanonicalUrl().'"', false);
        $this->assertNull($article->fresh()->canonical_url);
    }

    public function test_canonical_url_has_no_placeholder_on_the_create_form(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.articles.create'));

        $response->assertOk();
        // Nessun articolo ancora esistente -> nessun URL naturale calcolabile:
        // solo il campo canonical potrebbe mai avere un placeholder che è un URL.
        $response->assertDontSee('placeholder="http', false);
    }

    // 14. FASE 5: cambiare la cover in aggiornamento deve aggiornare il
    //     fallback OG/Twitter image, non solo alla creazione (già coperto)
    public function test_changing_the_cover_on_update_updates_the_og_and_twitter_image_fallback(): void
    {
        $editor = $this->editor();
        $article = $this->publishedArticle($editor, [
            'cover_image' => 'copertina-vecchia.jpg',
            'og_image' => null,
            'twitter_image' => null,
        ]);

        $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
            'cover_image' => 'copertina-nuova.jpg',
        ]);

        $article->refresh();
        $this->assertSame('copertina-nuova.jpg', $article->cover_image);
        $this->assertStringContainsString('copertina-nuova.jpg', $article->metaOgImage());
        $this->assertStringContainsString('copertina-nuova.jpg', $article->metaTwitterImage());
    }
}
