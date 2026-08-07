<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleLinkSuggestionUiTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->editor()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    // 1. Il form Admin (modifica) mostra il pannello con il pulsante "Analizza" (mai auto-submit: type="button")
    public function test_admin_edit_form_shows_the_analyze_button(): void
    {
        $editor = $this->editor();
        $article = $this->article(['user_id' => $editor->id]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('Collegamenti interni suggeriti');
        $response->assertSee('id="link-suggestions-analyze-btn"', false);
        $response->assertSee('<button type="button" id="link-suggestions-analyze-btn"', false);
    }

    // 2. Il form Admin (nuovo articolo) non mostra il pulsante: nessun articolo salvato da analizzare
    public function test_admin_create_form_does_not_show_the_analyze_button(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.articles.create'));

        $response->assertOk();
        $response->assertSee('Collegamenti interni suggeriti');
        $response->assertSee('Disponibile dopo il primo salvataggio');
        $response->assertDontSee('id="link-suggestions-analyze-btn"', false);
    }

    // 3. Il form Redazione (modifica, articolo proprio) mostra il pannello
    public function test_redazione_edit_form_shows_the_analyze_button_for_the_owner(): void
    {
        $author = $this->author();
        $article = $this->article(['user_id' => $author->id, 'status' => 'review', 'published_at' => null]);

        $response = $this->actingAs($author)->get(route('redazione.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('Collegamenti interni suggeriti');
        $response->assertSee('id="link-suggestions-analyze-btn"', false);
    }

    // 4. I suggerimenti già persistiti (proposed) vengono renderizzati lato server, senza richiedere un'analisi
    public function test_edit_form_renders_already_persisted_proposed_suggestions(): void
    {
        $editor = $this->editor();
        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article(['user_id' => $editor->id]);

        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari',
            'reason' => 'motivo di test',
            'confidence_score' => 55,
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $source));

        $response->assertOk();
        $response->assertSee('Pannelli solari di nuova generazione');
        $response->assertSee('pannelli solari');
    }

    // 5. Route corrette incorporate nel markup per l'analisi (Admin)
    public function test_admin_edit_form_embeds_the_correct_analyze_route(): void
    {
        $editor = $this->editor();
        $article = $this->article(['user_id' => $editor->id]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertSee(route('admin.articles.link-suggestions.analyze', $article), false);
    }
}
