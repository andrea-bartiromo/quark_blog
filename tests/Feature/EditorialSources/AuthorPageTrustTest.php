<?php

namespace Tests\Feature\EditorialSources;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDITORIAL TRUST (Missione 24) — pagina autore pubblica non "thin",
 * ruolo veritiero, e il gap linkedin (raccolto ovunque, mostrato da
 * nessuna parte) corretto.
 */
class AuthorPageTrustTest extends TestCase
{
    use RefreshDatabase;

    private function publishedArticleFor(User $author): Article
    {
        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo pubblicato',
            'slug' => 'articolo-pubblicato-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);
    }

    public function test_an_author_with_no_articles_and_no_bio_is_noindexed(): void
    {
        $author = User::factory()->create(['role' => 'author', 'bio' => null]);

        $response = $this->get(route('autore', $author))->assertOk();

        $response->assertSee('name="robots" content="noindex,follow"', false);
    }

    public function test_an_author_with_a_real_bio_but_no_articles_yet_stays_indexable(): void
    {
        $author = User::factory()->create([
            'role' => 'editor',
            'bio' => 'Editor scientifico con quindici anni di esperienza in divulgazione.',
        ]);

        $response = $this->get(route('autore', $author))->assertOk();

        $response->assertDontSee('name="robots" content="noindex,follow"', false);
    }

    public function test_an_author_with_published_articles_stays_indexable_even_without_a_bio(): void
    {
        $author = User::factory()->create(['role' => 'author', 'bio' => null]);
        $this->publishedArticleFor($author);

        $response = $this->get(route('autore', $author))->assertOk();

        $response->assertDontSee('name="robots" content="noindex,follow"', false);
    }

    public function test_linkedin_is_shown_publicly_when_present(): void
    {
        $author = User::factory()->create([
            'role' => 'author',
            'linkedin' => 'https://www.linkedin.com/in/esempio',
        ]);

        $response = $this->get(route('autore', $author))->assertOk();

        $response->assertSee('href="https://www.linkedin.com/in/esempio"', false);
    }

    public function test_linkedin_and_twitter_both_appear_in_the_person_schema_same_as_array(): void
    {
        $author = User::factory()->create([
            'role' => 'author',
            'twitter' => '@esempio',
            'linkedin' => 'https://www.linkedin.com/in/esempio',
        ]);

        $html = $this->get(route('autore', $author))->assertOk()->getContent();
        $data = $this->personSchemaFrom($html);

        $this->assertSame(
            ['https://twitter.com/esempio', 'https://www.linkedin.com/in/esempio'],
            $data['sameAs']
        );
    }

    public function test_role_label_distinguishes_a_collaborator_from_editorial_staff(): void
    {
        $collaborator = User::factory()->create(['role' => 'author', 'name' => 'Autrice Collaboratrice']);
        $this->publishedArticleFor($collaborator);

        $editor = User::factory()->create(['role' => 'editor', 'name' => 'Redattrice Capo']);
        $this->publishedArticleFor($editor);

        $collaboratorHtml = $this->get(route('autore', $collaborator))->assertOk()->getContent();
        $editorHtml = $this->get(route('autore', $editor))->assertOk()->getContent();

        $this->assertStringContainsString('Collaboratore Kairus', $collaboratorHtml);
        $this->assertStringContainsString('Redazione Kairus', $editorHtml);
    }

    public function test_no_fabricated_expertise_or_scientific_qualification_is_ever_printed(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $this->publishedArticleFor($author);

        $response = $this->get(route('autore', $author))->assertOk();

        $response->assertDontSee('esperto certificato', false);
        $response->assertDontSee('peer review', false);
    }

    private function personSchemaFrom(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);

            if (is_array($data) && ($data['@type'] ?? null) === 'Person') {
                return $data;
            }
        }

        $this->fail('Nessun blocco Person JSON-LD valido trovato sulla pagina autore.');
    }
}
