<?php

namespace Tests\Feature\EditorialSources;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDITORIAL TRUST (Missione 25) — distinzione autore / revisore
 * editoriale sulla pagina pubblica articolo.
 *
 * Nessuna copertura per un "revisore scientifico": Kairus non ha un
 * sistema di accreditamento scientifico, quindi quel ruolo non viene
 * dichiarato da nessuna parte del codice — non c'è nulla da testare qui
 * perché non c'è nulla da rendere pubblico.
 */
class ArticleEditorialReviewerTest extends TestCase
{
    use RefreshDatabase;

    private function publishedArticle(array $overrides = []): Article
    {
        $author = User::factory()->create(['role' => 'author', 'name' => 'Autrice Originale']);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo pubblicato',
            'slug' => 'articolo-pubblicato-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo articolo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    public function test_unverified_legacy_article_shows_no_review_note(): void
    {
        $article = $this->publishedArticle(['verification_status' => 'unverified', 'verified_by' => null]);

        $response = $this->get(route('articolo', $article->slug))->assertOk();

        $response->assertDontSee('Verifica editoriale');
    }

    public function test_verified_status_without_a_recorded_reviewer_name_shows_no_review_note(): void
    {
        // Stato incoerente in teoria irraggiungibile dal flusso admin, ma
        // il fallback deve restare silenzioso anche in questo caso — mai
        // un "Verificato da " con un nome vuoto.
        $article = $this->publishedArticle(['verification_status' => 'verified', 'verified_by' => null]);

        $response = $this->get(route('articolo', $article->slug))->assertOk();

        $response->assertDontSee('Verifica editoriale');
    }

    public function test_a_reviewer_distinct_from_the_author_is_named_publicly(): void
    {
        $article = $this->publishedArticle([
            'verification_status' => 'verified',
            'verified_by' => 'Redattore Capo',
            'verified_at' => now()->subHours(2),
        ]);

        $response = $this->get(route('articolo', $article->slug))->assertOk();

        $response->assertSee('Verifica editoriale a cura di');
        $response->assertSee('Redattore Capo');
    }

    public function test_a_self_verification_by_the_author_is_shown_without_repeating_the_name(): void
    {
        $article = $this->publishedArticle([
            'verification_status' => 'verified',
            'verified_by' => 'Autrice Originale',
            'verified_at' => now()->subHours(2),
        ]);

        $response = $this->get(route('articolo', $article->slug))->assertOk();

        $response->assertSee('Verifica editoriale confermata');
        $response->assertDontSee('Verifica editoriale a cura di');
    }

    public function test_no_scientific_reviewer_qualification_is_ever_printed(): void
    {
        $article = $this->publishedArticle([
            'verification_status' => 'verified',
            'verified_by' => 'Redattore Capo',
            'verified_at' => now()->subHours(2),
        ]);

        $response = $this->get(route('articolo', $article->slug))->assertOk();

        $response->assertDontSee('revisore scientifico', false);
        $response->assertDontSee('peer review', false);
        $response->assertDontSee('scientificamente verificato', false);
    }
}
