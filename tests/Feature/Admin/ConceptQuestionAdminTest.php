<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Models\User;
use App\Services\ContentGraph\ContentGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Content Graph Admin V1 — ConceptQuestion CRUD. L'admin puo' salvare una
 * domanda incompleta (senza risposta/target) anche con stato "approved": il
 * form-level validation qui verifica solo la FORMA dei dati, mai la
 * raggiungibilita' pubblica — quel contratto resta interamente in
 * ContentGraphService::answerableQuestionsForConcept(), verificato qui in
 * chiusura per dimostrare che i due livelli restano coerenti e separati.
 */
class ConceptQuestionAdminTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    private function author(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'author'])->save();

        return $user;
    }

    private function scheduledArticle(string $title): Article
    {
        return Article::create([
            'user_id' => $this->editor()->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => Article::STATUS_SCHEDULED,
            'read_minutes' => 2,
            'published_at' => now()->addDay(),
        ]);
    }

    private function publishedArticle(string $title): Article
    {
        return Article::create([
            'user_id' => $this->editor()->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 2,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_editor_can_create_a_question(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);

        $response = $this->actingAs($editor)->post(route('admin.concepts.questions.store', $concept), [
            'question' => 'Perche\' l\'entropia aumenta sempre?',
            'answer_summary' => 'Per la seconda legge della termodinamica.',
            'sort_order' => 1,
            'status' => 'draft',
        ]);

        $response->assertRedirect(route('admin.concepts.edit', $concept));
        $this->assertDatabaseHas('concept_questions', [
            'concept_id' => $concept->id,
            'question' => 'Perche\' l\'entropia aumenta sempre?',
            'status' => 'draft',
        ]);
    }

    public function test_question_slug_must_be_unique(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        ConceptQuestion::create([
            'concept_id' => $concept->id,
            'question' => 'Domanda esistente',
            'slug' => 'domanda-esistente',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($editor)->post(route('admin.concepts.questions.store', $concept), [
            'question' => 'Domanda esistente',
            'slug' => '',
            'status' => 'draft',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_editor_can_update_a_question_and_set_a_target_article(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $target = $this->publishedArticle('Termodinamica base');
        $question = ConceptQuestion::create([
            'concept_id' => $concept->id,
            'question' => 'Domanda originale',
            'slug' => 'domanda-originale',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($editor)->put(route('admin.concepts.questions.update', [$concept, $question]), [
            'question' => 'Domanda aggiornata',
            'slug' => $question->slug,
            'answer_summary' => 'Risposta sintetica.',
            'target_article_id' => $target->id,
            'sort_order' => 0,
            'status' => 'approved',
        ]);

        $response->assertRedirect(route('admin.concepts.edit', $concept));
        $question->refresh();
        $this->assertSame('Domanda aggiornata', $question->question);
        $this->assertSame($target->id, $question->target_article_id);
        $this->assertSame('approved', $question->status);
    }

    public function test_a_question_belonging_to_a_different_concept_is_not_updatable_via_this_concept(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $otherConcept = Concept::create(['name' => 'Quantistica', 'slug' => 'quantistica', 'status' => 'active']);
        $question = ConceptQuestion::create([
            'concept_id' => $otherConcept->id,
            'question' => 'Domanda di un altro concetto',
            'slug' => 'domanda-altro-concetto',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($editor)->put(route('admin.concepts.questions.update', [$concept, $question]), [
            'question' => 'Tentativo di modifica',
            'slug' => $question->slug,
            'status' => 'draft',
        ]);

        $response->assertNotFound();
    }

    /**
     * Verifica che il contratto di pubblicazione reale resti quello di
     * ContentGraphService, non una regola separata scritta qui: una domanda
     * approvata SENZA risposta non e' answerable anche se salvata cosi'
     * dall'admin.
     */
    public function test_admin_can_save_an_incomplete_approved_question_but_it_stays_unpublishable(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);

        $this->actingAs($editor)->post(route('admin.concepts.questions.store', $concept), [
            'question' => 'Domanda incompleta approvata',
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('concept_questions', ['question' => 'Domanda incompleta approvata', 'status' => 'approved']);

        $answerable = app(ContentGraphService::class)->answerableQuestionsForConcept($concept->fresh());
        $this->assertTrue($answerable->isEmpty());
    }

    // ── Mission 08: Content Graph Questions V1 Editorial Workflow ────

    public function test_guest_cannot_create_or_update_a_question(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $question = ConceptQuestion::create([
            'concept_id' => $concept->id,
            'question' => 'Domanda esistente',
            'slug' => 'domanda-esistente-guest',
            'status' => 'draft',
        ]);

        $this->post(route('admin.concepts.questions.store', $concept), ['question' => 'Nuova', 'status' => 'draft'])
            ->assertRedirect(route('login'));

        $this->put(route('admin.concepts.questions.update', [$concept, $question]), ['question' => 'Modificata', 'slug' => $question->slug, 'status' => 'draft'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('concept_questions', ['question' => 'Nuova']);
    }

    public function test_author_role_cannot_create_or_update_a_question(): void
    {
        $author = $this->author();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $question = ConceptQuestion::create([
            'concept_id' => $concept->id,
            'question' => 'Domanda esistente',
            'slug' => 'domanda-esistente-author',
            'status' => 'draft',
        ]);

        $this->actingAs($author)
            ->post(route('admin.concepts.questions.store', $concept), ['question' => 'Nuova', 'status' => 'draft'])
            ->assertRedirect(route('redazione.dashboard'));

        $this->actingAs($author)
            ->put(route('admin.concepts.questions.update', [$concept, $question]), ['question' => 'Modificata', 'slug' => $question->slug, 'status' => 'draft'])
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertDatabaseMissing('concept_questions', ['question' => 'Nuova']);
        $this->assertSame('Domanda esistente', $question->fresh()->question);
    }

    public function test_a_nonexistent_concept_in_the_route_returns_not_found(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->post(route('admin.concepts.questions.store', ['concept' => 999999]), ['question' => 'Domanda', 'status' => 'draft'])
            ->assertNotFound();
    }

    public function test_a_target_article_id_that_does_not_exist_is_rejected(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);

        $response = $this->actingAs($editor)->post(route('admin.concepts.questions.store', $concept), [
            'question' => 'Domanda con target inesistente',
            'status' => 'draft',
            'target_article_id' => 999999,
        ]);

        $response->assertSessionHasErrors('target_article_id');
        $this->assertDatabaseMissing('concept_questions', ['question' => 'Domanda con target inesistente']);
    }

    /**
     * Distingue esplicitamente "nessun target" (già coperto da
     * test_admin_can_save_an_incomplete_approved_question_but_it_stays_unpublishable)
     * da "target impostato ma non ancora pubblico": in entrambi i casi la
     * domanda resta non-answerable, ma qui il target ESISTE ed è valido —
     * solo non è Article::published() finché non scatta la pubblicazione
     * programmata.
     */
    public function test_an_approved_question_targeting_a_scheduled_article_stays_unanswerable(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $scheduled = $this->scheduledArticle('Articolo programmato');

        $this->actingAs($editor)->post(route('admin.concepts.questions.store', $concept), [
            'question' => 'Domanda verso target programmato',
            'answer_summary' => 'Sommario presente.',
            'status' => 'approved',
            'target_article_id' => $scheduled->id,
        ]);

        $this->assertDatabaseHas('concept_questions', ['question' => 'Domanda verso target programmato', 'target_article_id' => $scheduled->id]);

        $answerable = app(ContentGraphService::class)->answerableQuestionsForConcept($concept->fresh());
        $this->assertTrue($answerable->isEmpty());
    }

    public function test_status_can_transition_from_draft_to_approved_to_inactive(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $target = $this->publishedArticle('Target stato');
        $question = ConceptQuestion::create([
            'concept_id' => $concept->id,
            'question' => 'Domanda transizione',
            'slug' => 'domanda-transizione',
            'answer_summary' => 'Sommario presente.',
            'target_article_id' => $target->id,
            'status' => ConceptQuestion::STATUS_DRAFT,
        ]);
        $service = app(ContentGraphService::class);

        $this->assertTrue($service->answerableQuestionsForConcept($concept->fresh())->isEmpty());

        $this->actingAs($editor)->put(route('admin.concepts.questions.update', [$concept, $question]), [
            'question' => $question->question,
            'slug' => $question->slug,
            'answer_summary' => $question->answer_summary,
            'target_article_id' => $target->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);
        $this->assertTrue($service->answerableQuestionsForConcept($concept->fresh())->pluck('id')->contains($question->id));

        $this->actingAs($editor)->put(route('admin.concepts.questions.update', [$concept, $question]), [
            'question' => $question->question,
            'slug' => $question->slug,
            'answer_summary' => $question->answer_summary,
            'target_article_id' => $target->id,
            'status' => ConceptQuestion::STATUS_INACTIVE,
        ]);
        $this->assertTrue($service->answerableQuestionsForConcept($concept->fresh())->isEmpty());
    }

    public function test_concept_edit_page_orders_questions_by_sort_order_not_insertion_order(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);

        // Inserite in ordine deliberatamente inverso rispetto a sort_order:
        // se la pagina mostrasse l'ordine di inserimento grezzo (il bug
        // corretto da questa missione), "Terza domanda" comparirebbe prima
        // di "Prima domanda" nell'HTML.
        ConceptQuestion::create(['concept_id' => $concept->id, 'question' => 'Terza domanda', 'slug' => 'terza-domanda', 'sort_order' => 30]);
        ConceptQuestion::create(['concept_id' => $concept->id, 'question' => 'Prima domanda', 'slug' => 'prima-domanda', 'sort_order' => 10]);
        ConceptQuestion::create(['concept_id' => $concept->id, 'question' => 'Seconda domanda', 'slug' => 'seconda-domanda', 'sort_order' => 20]);

        $response = $this->actingAs($editor)->get(route('admin.concepts.edit', $concept));

        $response->assertOk();
        $content = $response->getContent();
        $firstPos = strpos($content, 'Prima domanda');
        $secondPos = strpos($content, 'Seconda domanda');
        $thirdPos = strpos($content, 'Terza domanda');

        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertNotFalse($thirdPos);
        $this->assertTrue($firstPos < $secondPos && $secondPos < $thirdPos, 'Le domande devono comparire nell\'ordine di sort_order, non di inserimento.');
    }

    public function test_concept_edit_page_shows_a_public_reachability_badge_matching_the_real_contract(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $target = $this->publishedArticle('Target raggiungibile');

        ConceptQuestion::create([
            'concept_id' => $concept->id,
            'question' => 'Domanda pubblicamente raggiungibile',
            'slug' => 'domanda-raggiungibile',
            'answer_summary' => 'Sommario presente.',
            'target_article_id' => $target->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);
        ConceptQuestion::create([
            'concept_id' => $concept->id,
            'question' => 'Domanda ancora bozza',
            'slug' => 'domanda-bozza',
            'status' => ConceptQuestion::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($editor)->get(route('admin.concepts.edit', $concept));

        $response->assertOk();
        $response->assertSee('✓ Pubblica');
        $response->assertSee('— Non pubblica');
    }
}
