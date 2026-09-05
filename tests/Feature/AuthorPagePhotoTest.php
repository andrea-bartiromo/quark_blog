<?php

namespace Tests\Feature;

use App\Models\Article;
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

    /**
     * Trust Layer V1 — AuthorController::show() ora richiede almeno un
     * articolo pubblicato (vedi PublicAuthorPageEligibilityTest): senza
     * questa fixture, entrambi i test qui sotto riceverebbero 404 invece
     * del markup foto da verificare.
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

    public function test_author_photo_is_rendered_when_present(): void
    {
        $author = User::factory()->create([
            'role' => 'author',
            'photo' => 'author-1.jpg',
        ]);
        $this->publishArticleFor($author);

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
        $this->publishArticleFor($author);

        $response = $this->get(route('autore', $author))->assertOk();

        // Scoped al blocco avatar, non all'intera pagina: la fixture ora
        // richiede un articolo pubblicato, che porta con sé una propria
        // <img> di copertina nell'elenco — irrilevante per questo test.
        preg_match('/author-premium-hero__avatar.*?<\/div>/s', $response->getContent(), $avatarBlock);
        $this->assertNotEmpty($avatarBlock, 'Blocco avatar non trovato in pagina.');
        $this->assertStringNotContainsString('<img', $avatarBlock[0]);

        $response->assertSee('aria-hidden="true"', false);
        $response->assertSee('<span>A</span>', false);
    }
}
