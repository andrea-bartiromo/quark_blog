<?php

namespace Tests\Feature\Admin\EditorialSources;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDITORIAL TRUST (Missione 25) — readout di sola lettura dello stato di
 * verifica editoriale nella pagina di modifica articolo, così un editor
 * non deve aprire la schermata "Verifica" separata solo per sapere se
 * l'articolo è già stato controllato.
 */
class ArticleFormEditorialReviewReadoutTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo esistente',
            'slug' => 'articolo-esistente-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    public function test_the_edit_form_shows_the_current_verification_status(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor, ['verification_status' => 'in_progress']);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('In verifica');
    }

    public function test_the_edit_form_names_the_reviewer_when_distinct_from_the_author(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor, [
            'verification_status' => 'verified',
            'verified_by' => 'Altro Redattore',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('a cura di Altro Redattore');
    }

    public function test_the_edit_form_links_to_the_full_verification_screen_instead_of_duplicating_it(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee(route('admin.verification'), false);
    }
}
