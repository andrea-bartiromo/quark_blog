<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trust Layer V1 — presentazione pubblica di "Aggiornato il" e di
 * dateModified nel JSON-LD, guidata da ArticleRevisionTransparencyService
 * (vedi tests/Unit per la logica). Qui si verifica solo l'integrazione
 * end-to-end: fallback conservativo, nessuna data UTC visibile in pagina,
 * coerenza col markup strutturato.
 */
class ArticlePublicRevisionTransparencyTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'cover_image' => 'copertina.jpg',
            'status' => 'published',
            'published_at' => now()->subDays(10),
        ], $overrides));
    }

    public function test_never_revised_article_shows_only_the_publication_date(): void
    {
        $article = $this->publishedArticle();

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertDontSee('Aggiornato il');
    }

    public function test_article_with_a_genuine_post_publication_correction_shows_updated_date(): void
    {
        $article = $this->publishedArticle();

        ArticleRevision::create([
            'article_id' => $article->id,
            'title' => 'Titolo prima della correzione',
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => 'published',
            'created_at' => $article->published_at->clone()->addDays(2),
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('Aggiornato il');
    }

    public function test_a_pure_status_transition_revision_does_not_trigger_updated_date(): void
    {
        $article = $this->publishedArticle();

        ArticleRevision::create([
            'article_id' => $article->id,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => 'review',
            'created_at' => $article->published_at->clone()->addHour(),
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertDontSee('Aggiornato il');
    }

    public function test_json_ld_omits_datemodified_when_no_reliable_revision_exists(): void
    {
        $article = $this->publishedArticle();

        $response = $this->get(route('articolo', $article->slug));
        $json = $this->extractStructuredData($response->getContent());

        $this->assertArrayNotHasKey('dateModified', $json['@graph'][0]);
    }

    public function test_json_ld_includes_datemodified_when_a_reliable_revision_exists(): void
    {
        $article = $this->publishedArticle();

        $revisionTime = $article->published_at->clone()->addDays(4);

        ArticleRevision::create([
            'article_id' => $article->id,
            'title' => 'Titolo prima della correzione',
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => 'published',
            'created_at' => $revisionTime,
        ]);

        $response = $this->get(route('articolo', $article->slug));
        $json = $this->extractStructuredData($response->getContent());

        $this->assertSame(
            $revisionTime->toIso8601String(),
            $json['@graph'][0]['dateModified']
        );
    }

    public function test_updated_date_is_shown_in_italian_locale_not_as_a_raw_utc_timestamp(): void
    {
        $article = $this->publishedArticle();

        ArticleRevision::create([
            'article_id' => $article->id,
            'title' => 'Titolo prima della correzione',
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => 'published',
            'created_at' => $article->published_at->clone()->addDays(2),
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();

        // Il blocco "Aggiornato il" usa lo stesso formato leggibile in
        // italiano già in uso per la data di pubblicazione (es. "12 gen
        // 2026", via x-kairus.article-meta::translatedFormat('d M Y')),
        // mai un timestamp tecnico ISO/UTC in chiaro. Testo ed etichetta
        // sono entrambi dentro lo stesso <time>, per costruzione del
        // componente Kairus (Missione 06) — non un <time> che avvolge
        // solo la data nuda come nel markup legacy pre-Kairus.
        preg_match('/<time datetime="[^"]+">Aggiornato il ([^<]+)<\/time>/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'Blocco "Aggiornato il" non trovato in pagina.');
        $this->assertMatchesRegularExpression('/^\d{1,2} [a-zàéìòù]+\.? \d{4}$/u', trim($matches[1]));
    }

    private function extractStructuredData(string $html): array
    {
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

        return json_decode($matches[1], true);
    }
}
