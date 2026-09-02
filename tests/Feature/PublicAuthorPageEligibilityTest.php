<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trust Layer V1 — eleggibilità pubblica di /autore/{user}. Prima di
 * questa modifica AuthorController::show() non filtrava affatto: ogni
 * User esistente era raggiungibile, confermandone l'esistenza anche senza
 * alcun contenuto pubblico. La regola qui verificata è puramente
 * data-driven: eleggibile solo chi ha almeno un articolo pubblicato,
 * nessun campo nuovo, nessuna migration.
 */
class PublicAuthorPageEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function articleFor(User $user, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_a_missing_user_returns_404(): void
    {
        $response = $this->get('/autore/999999');

        $response->assertNotFound();
    }

    public function test_a_user_with_zero_published_articles_returns_404(): void
    {
        $user = User::factory()->create(['role' => 'author']);

        $response = $this->get(route('autore', $user));

        $response->assertNotFound();
    }

    public function test_a_user_with_only_draft_or_scheduled_articles_returns_404(): void
    {
        $user = User::factory()->create(['role' => 'author']);
        $this->articleFor($user, ['status' => 'draft', 'published_at' => null]);
        $this->articleFor($user, ['status' => 'scheduled', 'published_at' => now()->addDay()]);

        $response = $this->get(route('autore', $user));

        $response->assertNotFound();
    }

    public function test_a_user_with_at_least_one_published_article_is_publicly_visible(): void
    {
        $user = User::factory()->create(['role' => 'author', 'bio' => 'Biografia di prova.']);
        $this->articleFor($user);

        $response = $this->get(route('autore', $user));

        $response->assertOk();
        $response->assertSee($user->name);
    }

    public function test_email_is_never_shown_for_a_non_editor_author_even_when_eligible(): void
    {
        $user = User::factory()->create(['role' => 'author', 'email' => 'autore-privato@example.test']);
        $this->articleFor($user);

        $response = $this->get(route('autore', $user));

        $response->assertOk();
        $response->assertDontSee('autore-privato@example.test');
    }

    public function test_an_inactive_admin_account_with_no_publications_is_not_exposed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->get(route('autore', $admin));

        $response->assertNotFound();
    }

    /**
     * L'autore di un articolo pubblicato è per definizione eleggibile
     * (l'articolo stesso è quell'articolo pubblicato): il link "Profilo
     * autore" nella pagina articolo (articles/partials/author-card.blade.php)
     * non ha bisogno di un ramo di fallback "autore non pubblico" — questo
     * test documenta e verifica l'invariante che rende quel ramo
     * strutturalmente irraggiungibile.
     */
    /**
     * resources/views/redazione.blade.php linka staticamente
     * User::where('role','editor')->first() (con fallback hardcoded
     * '?? 1', preesistente, non toccato qui). Se quell'editor avesse zero
     * articoli pubblicati, il link "Tutti gli articoli" romperebbe ora
     * che la pagina autore richiede eleggibilità — questo test documenta
     * il rischio verificando il percorso quando l'editor È eleggibile
     * (il caso di produzione atteso), non modifica redazione.blade.php.
     */
    public function test_la_redazione_editor_link_resolves_when_the_editor_has_published_articles(): void
    {
        $editor = User::factory()->create(['role' => 'editor', 'name' => 'Direttore']);
        $this->articleFor($editor);

        $page = $this->get(route('redazione'));
        $page->assertOk();
        $page->assertSee(route('autore', $editor), false);

        $authorPage = $this->get(route('autore', $editor));
        $authorPage->assertOk();
    }

    public function test_the_author_link_on_a_published_article_page_never_404s(): void
    {
        $user = User::factory()->create(['role' => 'author']);
        $article = $this->articleFor($user);

        $articleResponse = $this->get(route('articolo', $article->slug));
        $articleResponse->assertOk();
        $articleResponse->assertSee(route('autore', $article->author), false);

        $authorResponse = $this->get(route('autore', $article->author));
        $authorResponse->assertOk();
    }
}
