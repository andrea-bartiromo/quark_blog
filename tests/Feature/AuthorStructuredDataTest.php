<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_page_exposes_person_structured_data_from_existing_profile_fields(): void
    {
        $author = User::factory()->create([
            'name' => 'Ada Rossi',
            'bio' => 'Divulgatrice scientifica.',
            'twitter' => '@adarossi',
        ]);

        $html = $this->get(route('autore', $author))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $html,
            'Nessun blocco Person JSON-LD trovato sulla pagina autore.'
        );

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $data = json_decode($matches[1], true);

        $this->assertIsArray($data, 'Il blocco JSON-LD non è JSON valido: '.json_last_error_msg());
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

        $html = $this->get(route('autore', $author))->assertOk()->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $data = json_decode($matches[1], true);

        $this->assertArrayNotHasKey('description', $data);
        $this->assertArrayNotHasKey('sameAs', $data);
    }
}
