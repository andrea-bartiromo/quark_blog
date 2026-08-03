<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre il fix del bug sulla pagina pubblica autore: la vista leggeva
 * $author->avatar, un attributo inesistente sul modello User (la colonna
 * reale è "photo"), quindi la foto autore non veniva mai mostrata anche
 * quando presente. Verificato anche il percorso corretto (assets/img/,
 * dove Admin\ProfileController e Redazione\ProfileController salvano
 * davvero il file), non storage/ (mai scritto da questi flussi).
 */
class AuthorPagePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_photo_is_rendered_when_present(): void
    {
        $author = User::factory()->create([
            'role' => 'author',
            'photo' => 'author-1.jpg',
        ]);

        $response = $this->get(route('autore', $author))->assertOk();

        $response->assertSee('<img src="'.asset('assets/img/author-1.jpg').'"', false);
        $response->assertDontSee('storage/author-1.jpg', false);
        $response->assertSee('aria-hidden="false"', false);
    }

    public function test_initial_placeholder_is_used_when_no_photo_is_set(): void
    {
        $author = User::factory()->create([
            'role' => 'author',
            'name' => 'Autrice Senza Foto',
            'photo' => null,
        ]);

        $response = $this->get(route('autore', $author))->assertOk();

        $response->assertDontSee('<img src=', false);
        $response->assertSee('aria-hidden="true"', false);
        $response->assertSee('<span>A</span>', false);
    }
}
