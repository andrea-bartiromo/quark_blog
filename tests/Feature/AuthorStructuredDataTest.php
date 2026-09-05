<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Trust Layer V1 — AuthorController::show() ora richiede almeno un
     * articolo pubblicato (vedi PublicAuthorPageEligibilityTest): senza
     * questa fixture, ogni test qui sotto riceverebbe 404 invece del
     * markup Person da verificare.
     */
    private function publishArticleFor(User $author): void
    {
        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_author_page_exposes_person_structured_data_from_existing_profile_fields(): void
    {
        $author = User::factory()->create([
            'name' => 'Ada Rossi',
            'bio' => 'Divulgatrice scientifica.',
            'twitter' => '@adarossi',
        ]);
        $this->publishArticleFor($author);

        $html = $this->get(route('autore', $author))->assertOk()->getContent();
        $data = $this->personSchemaFrom($html);

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('Person', $data['@type']);
        $this->assertSame('Ada Rossi', $data['name']);
        $this->assertSame(route('autore', $author), $data['url']);
        $this->assertSame('Divulgatrice scientifica.', $data['description']);
        $this->assertSame(['https://twitter.com/adarossi'], $data['sameAs']);
    }

    public function test_author_person_schema_omits_unavailable_optional_profile_fields(): void
    {
        $author = User::factory()->create([
            'name' => 'Luca Verdi',
            'bio' => null,
            'twitter' => null,
        ]);
        $this->publishArticleFor($author);

        $html = $this->get(route('autore', $author))->assertOk()->getContent();
        $data = $this->personSchemaFrom($html);

        $this->assertArrayNotHasKey('description', $data);
        $this->assertArrayNotHasKey('sameAs', $data);
    }

    public function test_author_person_schema_hex_encodes_script_terminators_without_changing_profile_value(): void
    {
        $bio = 'Profilo </script><script>alert("x")</script> originale';
        $author = User::factory()->create([
            'name' => 'Ada Rossi',
            'bio' => $bio,
            'twitter' => '@adarossi',
        ]);
        $this->publishArticleFor($author);

        $html = $this->get(route('autore', $author))->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(\\"x\\")</script>', $html);

        $data = $this->personSchemaFrom($html);

        $this->assertSame($bio, $data['description']);
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
