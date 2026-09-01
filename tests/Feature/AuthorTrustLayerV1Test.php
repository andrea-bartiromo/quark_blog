<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorTrustLayerV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_thin_profile_is_noindexed_without_becoming_a_404(): void
    {
        $author = User::factory()->create(['role' => 'author', 'bio' => null]);

        $this->get(route('autore', $author))
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);
    }

    public function test_bio_or_published_work_keeps_profile_indexable(): void
    {
        $withBio = User::factory()->create(['role' => 'editor', 'bio' => 'Biografia editoriale verificata internamente.']);
        $withArticle = User::factory()->create(['role' => 'author', 'bio' => null]);
        $this->publishedArticleFor($withArticle);

        $this->get(route('autore', $withBio))->assertOk()->assertDontSee('noindex,follow');
        $this->get(route('autore', $withArticle))->assertOk()->assertDontSee('noindex,follow');
    }

    public function test_role_and_verified_profile_links_are_truthfully_rendered(): void
    {
        $author = User::factory()->create([
            'role' => 'author',
            'twitter' => '@esempio',
            'linkedin' => 'https://www.linkedin.com/in/esempio',
        ]);

        $html = $this->get(route('autore', $author))->assertOk()->getContent();

        $this->assertStringContainsString('Collaboratore Kairus', $html);
        $this->assertStringContainsString('https://twitter.com/esempio', $html);
        $this->assertStringContainsString('https://www.linkedin.com/in/esempio', $html);
        $this->assertStringNotContainsString('esperto certificato', $html);
    }

    public function test_email_and_invalid_external_profiles_are_never_exposed(): void
    {
        $editor = User::factory()->create([
            'role' => 'editor',
            'email' => 'privata@example.test',
            'linkedin' => 'javascript:alert(1)',
        ]);

        $response = $this->get(route('autore', $editor))->assertOk();

        $response->assertDontSee('privata@example.test');
        $response->assertDontSee('mailto:', false);
        $response->assertDontSee('javascript:', false);
        $response->assertSee('Redazione Kairus');
    }

    public function test_person_schema_uses_only_available_demonstrable_fields(): void
    {
        $author = User::factory()->create([
            'role' => 'author',
            'name' => 'Ada Rossi',
            'bio' => 'Divulgatrice scientifica.',
            'twitter' => '@adarossi',
            'linkedin' => 'https://www.linkedin.com/in/adarossi',
        ]);

        $html = $this->get(route('autore', $author))->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $person = collect($matches[1])
            ->map(fn (string $json): mixed => json_decode($json, true))
            ->firstWhere('@type', 'Person');

        $this->assertSame('Ada Rossi', $person['name']);
        $this->assertSame('Collaboratore Kairus', $person['jobTitle']);
        $this->assertSame([
            'https://twitter.com/adarossi',
            'https://www.linkedin.com/in/adarossi',
        ], $person['sameAs']);
        $this->assertArrayNotHasKey('email', $person);
        $this->assertArrayNotHasKey('knowsAbout', $person);
    }

    public function test_unknown_author_route_still_returns_404(): void
    {
        $this->get('/autore/999999')->assertNotFound();
    }

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
}
