<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleLinkSuggestionControllerTest extends TestCase
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

    // 1. L'analisi (Admin) persiste e restituisce i suggerimenti pertinenti
    public function test_admin_analyze_returns_pertinent_suggestions(): void
    {
        $editor = $this->editor();

        $target = $this->article([
            'user_id' => $editor->id,
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli solari più efficienti',
        ]);

        $source = $this->article([
            'user_id' => $editor->id,
            'title' => 'Guida alla transizione energetica',
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>',
        ]);

        $response = $this->actingAs($editor)->postJson(route('admin.articles.link-suggestions.analyze', $source));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $target->id]);

        $this->assertSame(1, ArticleLinkSuggestion::where('source_article_id', $source->id)->count());
    }

    // 2. "Inserisci" modifica il body ricevuto e NON salva l'articolo (l'Article resta invariato)
    public function test_insert_returns_updated_body_without_saving_the_article(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $originalBody = '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id, 'body' => $originalBody]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $response = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.insert', [$source, $suggestion]),
            ['body' => $originalBody]
        );

        $response->assertOk();
        $response->assertJsonStructure(['body']);
        $this->assertStringContainsString('<a href=', $response->json('body'));

        // Il record Article non è stato toccato: il salvataggio resta un'azione umana esplicita.
        $this->assertSame($originalBody, $source->fresh()->body);

        // "Inserisci" non marca ancora accettato: lo fa solo il salvataggio
        // effettivo dell'articolo (vedi markAccepted). Se la redazione
        // abbandona la modifica senza salvare, il suggerimento resta
        // riproponibile invece di risultare "gestito" per sempre.
        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestion->fresh()->status);
        $this->assertNull($suggestion->fresh()->reviewed_at);
        $this->assertNull($suggestion->fresh()->reviewed_by);
    }

    // 2b. Il salvataggio effettivo dell'articolo (Admin) marca accettati i suggerimenti applicati nel form
    public function test_admin_update_marks_applied_suggestions_as_accepted_on_save(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $linkedBody = '<p>Tra le soluzioni più diffuse ci sono i <a href="'.route('articolo', $target->slug).'">pannelli solari di nuova generazione</a>, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'draft',
            'applied_link_suggestions' => [$suggestion->id],
        ]);

        $response->assertRedirect(route('admin.articles'));

        $this->assertSame(ArticleLinkSuggestion::STATUS_ACCEPTED, $suggestion->fresh()->status);
        $this->assertNotNull($suggestion->fresh()->reviewed_at);
        $this->assertSame($editor->id, $suggestion->fresh()->reviewed_by);
    }

    // 2c. Se l'articolo non viene mai salvato (o è salvato senza quel suggerimento tra gli applicati), resta "proposed"
    public function test_admin_update_without_applied_suggestions_leaves_them_proposed(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $source->body,
            'category' => $source->category,
            'status' => 'draft',
        ]);

        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestion->fresh()->status);
    }

    // 3. "Ignora" marca il suggerimento e una successiva analisi non lo ripropone
    public function test_ignore_marks_the_suggestion_and_it_is_not_re_proposed(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article([
            'user_id' => $editor->id,
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>',
        ]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $ignoreResponse = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.ignore', [$source, $suggestion])
        );

        $ignoreResponse->assertOk();
        $this->assertSame(ArticleLinkSuggestion::STATUS_IGNORED, $suggestion->fresh()->status);

        $analyzeResponse = $this->actingAs($editor)->postJson(route('admin.articles.link-suggestions.analyze', $source));
        $analyzeResponse->assertOk();
        $analyzeResponse->assertJsonMissing(['id' => $suggestion->id]);
        $this->assertSame(1, ArticleLinkSuggestion::where('source_article_id', $source->id)->count());
    }

    // 4. Un collaboratore (author) non può analizzare/gestire i suggerimenti di un articolo altrui
    public function test_an_author_cannot_manage_suggestions_of_another_authors_article(): void
    {
        $owner = $this->author();
        $intruder = $this->author();

        $source = $this->article(['user_id' => $owner->id, 'status' => 'review', 'published_at' => null]);

        $response = $this->actingAs($intruder)->postJson(route('redazione.articles.link-suggestions.analyze', $source));

        $response->assertForbidden();
    }

    // 5. Un collaboratore può analizzare i propri articoli
    public function test_an_author_can_analyze_suggestions_for_their_own_article(): void
    {
        $owner = $this->author();

        $target = $this->article(['title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article([
            'user_id' => $owner->id,
            'status' => 'review',
            'published_at' => null,
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>',
        ]);

        $response = $this->actingAs($owner)->postJson(route('redazione.articles.link-suggestions.analyze', $source));

        $response->assertOk();
    }

    // 6. Se l'anchor non è più presente nel body inviato, l'inserimento fallisce e il suggerimento resta "proposed"
    public function test_insert_fails_gracefully_when_anchor_text_is_no_longer_present(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $response = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.insert', [$source, $suggestion]),
            ['body' => '<p>Testo completamente diverso, senza la frase suggerita.</p>']
        );

        $response->assertStatus(422);
        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestion->fresh()->status);
    }

    // 7. Un suggerimento già gestito non può essere inserito/ignorato di nuovo
    public function test_an_already_reviewed_suggestion_cannot_be_actioned_again(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
            'status' => ArticleLinkSuggestion::STATUS_ACCEPTED,
        ]);

        $response = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.ignore', [$source, $suggestion])
        );

        $response->assertStatus(409);
    }
}
