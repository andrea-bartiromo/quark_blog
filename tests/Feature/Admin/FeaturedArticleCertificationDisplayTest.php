<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 36 (programma 100-cantieri Kairus), dipende dai Cantieri 30-35.
 * Copre solo la superficie admin.articles.edit — la logica di
 * certificazione ha una suite dedicata
 * (tests/Unit/EditorialQuality/FeaturedArticleCertificationServiceTest.php).
 * Usa il servizio reale (nessun fake): l'obiettivo qui è solo verificare
 * che il riquadro compaia/non compaia nella pagina giusta.
 */
class FeaturedArticleCertificationDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->editor()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => '<p>Corpo di prova.</p>',
            'category' => 'scienza',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ], $overrides));
    }

    public function test_certification_box_is_absent_when_article_is_not_featured(): void
    {
        $article = $this->article(['featured' => false]);

        $response = $this->actingAs($this->editor())->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertDontSee('Certificazione primo piano');
    }

    public function test_certification_box_shows_when_article_is_featured(): void
    {
        $article = $this->article(['featured' => true]);

        $response = $this->actingAs($this->editor())->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('Certificazione primo piano');
    }

    public function test_certification_box_flags_a_non_published_featured_article(): void
    {
        $article = $this->article(['featured' => true, 'status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $response = $this->actingAs($this->editor())->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('Certificazione primo piano');
        $response->assertSee('Non ancora pubblicato');
    }

    public function test_certification_box_flags_a_second_published_featured_article(): void
    {
        $article = $this->article(['featured' => true]);
        $this->article(['featured' => true, 'title' => 'Un altro in evidenza']);

        $response = $this->actingAs($this->editor())->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('già segnato &quot;in evidenza&quot;', false);
    }

    public function test_new_article_form_does_not_render_the_certification_box(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.articles.create'));

        $response->assertOk();
        $response->assertDontSee('Certificazione primo piano');
    }
}
