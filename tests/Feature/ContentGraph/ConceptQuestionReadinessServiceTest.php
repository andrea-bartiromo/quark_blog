<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Models\User;
use App\Services\ContentGraph\ConceptQuestionReadinessService;
use App\Services\ContentGraph\ContentGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Mission 21 — Question Status Workflow V2: verifica che evaluate() sia
 * l'itemizzazione ESATTA delle stesse condizioni di
 * ContentGraphService::answerableQuestionsForConcept() — mai una seconda
 * versione (potenzialmente disallineata) della regola. Ogni test qui
 * cross-verifica anche il verdetto reale del contratto pubblico.
 */
class ConceptQuestionReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ConceptQuestionReadinessService
    {
        return app(ConceptQuestionReadinessService::class);
    }

    private function contentGraph(): ContentGraphService
    {
        return app(ContentGraphService::class);
    }

    private function article(string $status = Article::STATUS_PUBLISHED, ?Carbon $publishedAt = null): Article
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return Article::create([
            'user_id' => $user->id,
            'title' => 'Articolo di test',
            'slug' => 'articolo-test-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $publishedAt ?? ($status === Article::STATUS_PUBLISHED ? now()->subDay() : null),
        ]);
    }

    private function assertMatchesRealGate(Concept $concept, ConceptQuestion $question, bool $expectedAnswerable): void
    {
        $reallyAnswerable = $this->contentGraph()->answerableQuestionsForConcept($concept)->contains('id', $question->id);
        $this->assertSame($expectedAnswerable, $reallyAnswerable, 'Il test fixture non riflette il verdetto reale del contratto pubblico.');
    }

    public function test_a_fully_complete_approved_question_has_no_findings(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $article = $this->article();
        $question = $concept->questions()->create([
            'question' => 'Cosa misura l\'entropia?',
            'answer_summary' => 'Il disordine di un sistema.',
            'target_article_id' => $article->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $result = $this->service()->evaluate($question);

        $this->assertTrue($result['answerable']);
        $this->assertSame([], $result['findings']);
        $this->assertMatchesRealGate($concept, $question, true);
    }

    public function test_a_draft_question_is_flagged_status_not_approved(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $question = $concept->questions()->create(['question' => 'Domanda bozza', 'status' => ConceptQuestion::STATUS_DRAFT]);

        $result = $this->service()->evaluate($question);

        $this->assertFalse($result['answerable']);
        $codes = array_column($result['findings'], 'code');
        $this->assertContains(ConceptQuestionReadinessService::STATUS_NOT_APPROVED, $codes);
        $this->assertMatchesRealGate($concept, $question, false);
    }

    public function test_an_approved_question_with_no_answer_is_flagged_answer_missing(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $article = $this->article();
        $question = $concept->questions()->create([
            'question' => 'Domanda senza risposta',
            'target_article_id' => $article->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $result = $this->service()->evaluate($question);

        $this->assertFalse($result['answerable']);
        $this->assertSame([ConceptQuestionReadinessService::ANSWER_MISSING], array_column($result['findings'], 'code'));
        $this->assertMatchesRealGate($concept, $question, false);
    }

    public function test_a_whitespace_only_answer_still_counts_as_missing(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $article = $this->article();
        $question = $concept->questions()->create([
            'question' => 'Domanda',
            'answer_summary' => "   \n  ",
            'target_article_id' => $article->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $result = $this->service()->evaluate($question);

        $this->assertContains(ConceptQuestionReadinessService::ANSWER_MISSING, array_column($result['findings'], 'code'));
    }

    public function test_an_approved_question_with_no_target_is_flagged_target_missing(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $question = $concept->questions()->create([
            'question' => 'Domanda senza target',
            'answer_summary' => 'Risposta.',
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $result = $this->service()->evaluate($question);

        $this->assertFalse($result['answerable']);
        $this->assertSame([ConceptQuestionReadinessService::TARGET_MISSING], array_column($result['findings'], 'code'));
        $this->assertMatchesRealGate($concept, $question, false);
    }

    public function test_an_approved_question_targeting_a_draft_article_is_flagged_target_not_published(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $draftArticle = $this->article(Article::STATUS_DRAFT);
        $question = $concept->questions()->create([
            'question' => 'Domanda con target bozza',
            'answer_summary' => 'Risposta.',
            'target_article_id' => $draftArticle->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $result = $this->service()->evaluate($question);

        $this->assertFalse($result['answerable']);
        $this->assertSame([ConceptQuestionReadinessService::TARGET_NOT_PUBLISHED], array_column($result['findings'], 'code'));
        $this->assertMatchesRealGate($concept, $question, false);
    }

    public function test_a_published_target_with_a_future_published_at_is_still_flagged_target_not_published(): void
    {
        // Article::isPublished() guarda solo lo status; Article::published()
        // (il gate canonico) richiede ANCHE published_at <= now(). Un
        // articolo status=published ma con published_at futuro non è
        // ancora davvero pubblico — questo test prova che il servizio usa
        // il gate canonico, non isPublished().
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $futureArticle = $this->article(Article::STATUS_PUBLISHED, now()->addDay());
        $question = $concept->questions()->create([
            'question' => 'Domanda con target futuro',
            'answer_summary' => 'Risposta.',
            'target_article_id' => $futureArticle->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $result = $this->service()->evaluate($question);

        $this->assertFalse($result['answerable']);
        $this->assertSame([ConceptQuestionReadinessService::TARGET_NOT_PUBLISHED], array_column($result['findings'], 'code'));
        $this->assertMatchesRealGate($concept, $question, false);
    }

    public function test_an_otherwise_complete_question_on_an_inactive_concept_is_flagged_concept_not_active(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'draft']);
        $article = $this->article();
        $question = $concept->questions()->create([
            'question' => 'Domanda completa',
            'answer_summary' => 'Risposta.',
            'target_article_id' => $article->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $result = $this->service()->evaluate($question);

        $this->assertFalse($result['answerable']);
        $this->assertSame([ConceptQuestionReadinessService::CONCEPT_NOT_ACTIVE], array_column($result['findings'], 'code'));
        $this->assertMatchesRealGate($concept, $question, false);
    }

    public function test_multiple_missing_conditions_are_all_reported_together(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'draft']);
        $question = $concept->questions()->create(['question' => 'Domanda vuota', 'status' => ConceptQuestion::STATUS_DRAFT]);

        $result = $this->service()->evaluate($question);

        $codes = array_column($result['findings'], 'code');
        $this->assertContains(ConceptQuestionReadinessService::STATUS_NOT_APPROVED, $codes);
        $this->assertContains(ConceptQuestionReadinessService::CONCEPT_NOT_ACTIVE, $codes);
        $this->assertContains(ConceptQuestionReadinessService::ANSWER_MISSING, $codes);
        $this->assertContains(ConceptQuestionReadinessService::TARGET_MISSING, $codes);
    }

    public function test_evaluate_never_mutates_the_question(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $question = $concept->questions()->create(['question' => 'Domanda', 'status' => ConceptQuestion::STATUS_DRAFT]);

        $this->service()->evaluate($question);

        $this->assertSame(ConceptQuestion::STATUS_DRAFT, $question->fresh()->status);
    }
}
