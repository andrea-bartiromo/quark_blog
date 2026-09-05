<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trust Layer V1 — sintesi con la PR parallela #509
 * (feat/author-trust-layer-v1): etichetta di ruolo veritiera, LinkedIn
 * validato, jobTitle nello schema Person, email non pubblicata per
 * nessun ruolo. L'eleggibilità pubblica (almeno un articolo pubblicato)
 * resta quella di questa PR — vedi PublicAuthorPageEligibilityTest.
 */
class AuthorPageRoleAndLinkedinTest extends TestCase
{
    use RefreshDatabase;

    private function articleFor(User $user): void
    {
        Article::create([
            'user_id' => $user->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_a_collaborator_is_labeled_as_such_not_as_editorial_staff(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $this->articleFor($author);

        $response = $this->get(route('autore', $author));

        $response->assertOk();
        $response->assertSee('Collaboratore Kairus');
        $response->assertDontSee('Redazione Kairus');
    }

    public function test_an_editor_is_labeled_as_editorial_staff(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $this->articleFor($editor);

        $response = $this->get(route('autore', $editor));

        $response->assertOk();
        $response->assertSee('Redazione Kairus');
        $response->assertDontSee('Collaboratore Kairus');
    }

    public function test_a_valid_linkedin_url_is_rendered_and_included_in_sameas(): void
    {
        $author = User::factory()->create([
            'role' => 'author',
            'linkedin' => 'https://www.linkedin.com/in/esempio',
        ]);
        $this->articleFor($author);

        $html = $this->get(route('autore', $author))->assertOk()->getContent();

        $this->assertStringContainsString('https://www.linkedin.com/in/esempio', $html);
        $this->assertStringContainsString('LinkedIn', $html);
    }

    public function test_an_unsafe_linkedin_scheme_is_never_rendered_as_a_link(): void
    {
        $author = User::factory()->create([
            'role' => 'author',
            'linkedin' => 'javascript:alert(1)',
        ]);
        $this->articleFor($author);

        $html = $this->get(route('autore', $author))->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_email_is_never_shown_even_for_an_editor(): void
    {
        $editor = User::factory()->create([
            'role' => 'editor',
            'email' => 'redazione-privata@example.test',
        ]);
        $this->articleFor($editor);

        $response = $this->get(route('autore', $editor));

        $response->assertOk();
        $response->assertDontSee('redazione-privata@example.test');
        $response->assertDontSee('mailto:', false);
    }

    public function test_person_schema_includes_jobtitle_and_validated_sameas(): void
    {
        $author = User::factory()->create([
            'role' => 'author',
            'name' => 'Ada Rossi',
            'twitter' => '@adarossi',
            'linkedin' => 'https://www.linkedin.com/in/adarossi',
        ]);
        $this->articleFor($author);

        $html = $this->get(route('autore', $author))->assertOk()->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $person = json_decode($matches[1], true);

        $this->assertSame('Collaboratore Kairus', $person['jobTitle']);
        $this->assertSame([
            'https://twitter.com/adarossi',
            'https://www.linkedin.com/in/adarossi',
        ], $person['sameAs']);
        $this->assertArrayNotHasKey('email', $person);
    }
}
