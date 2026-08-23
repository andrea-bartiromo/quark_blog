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
}
