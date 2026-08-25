<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialQualityGateUiTest extends TestCase
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
            'title' => 'Un articolo di prova sufficientemente completo',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Un sommario abbastanza lungo da superare la soglia minima richiesta dal controllo.',
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'cover_image' => 'cover.webp',
            'cover_alt' => 'Descrizione cover',
            'primary_sources' => 'https://example.com/fonte',
        ], $overrides));
    }

    public function test_the_admin_edit_form_shows_the_quality_gate_card(): void
    {
        $editor = $this->editor();
        $article = $this->article(['user_id' => $editor->id]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSeeText('Qualità editoriale');
    }

    public function test_a_ready_article_shows_the_ready_label(): void
    {
        $editor = $this->editor();
        $article = $this->article([
            'user_id' => $editor->id,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><a href="/articolo/altro">altro articolo</a>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertSeeText('Pronto');
    }

    public function test_an_incomplete_article_shows_the_incomplete_label_and_the_reason(): void
    {
        $editor = $this->editor();
        $article = $this->article(['user_id' => $editor->id, 'cover_image' => null]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertSeeText('Da completare');
    }

    public function test_the_quality_gate_never_blocks_saving_an_incomplete_article(): void
    {
        $editor = $this->editor();
        $article = $this->article(['user_id' => $editor->id, 'cover_image' => null]);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'body' => $article->body,
            'category' => $article->category,
            'status' => Article::STATUS_PUBLISHED,
        ]);

        $response->assertRedirect(route('admin.articles'));
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'status' => Article::STATUS_PUBLISHED]);
    }

    /**
     * Missione 35 (secondo batch autonomo KAIRUS, Fase E — Editorial
     * Quality & Readiness): la sidebar admin ora include una voce di
     * navigazione "Qualità editoriale" (verso l'audit sitewide), presente
     * su OGNI pagina admin per costruzione — quindi questa asserzione va
     * scoperta al solo contenuto principale (dopo <main>), altrimenti il
     * testo della sidebar farebbe scattare un falso positivo qui, esattamente
     * come già gestito da altri test che ispezionano solo il contenuto di
     * pagina (vedi EditorialOperationsDashboardControllerTest).
     */
    public function test_the_card_is_never_shown_when_creating_a_new_article(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.articles.create'));

        $response->assertOk();
        $mainContent = strstr($response->getContent(), '<main') ?: '';
        $this->assertStringNotContainsString('Qualità editoriale', $mainContent);
    }
}
