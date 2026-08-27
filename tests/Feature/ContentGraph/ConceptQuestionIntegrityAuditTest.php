<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Models\User;
use App\Services\ContentGraph\ConceptQuestionReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConceptQuestionIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $status, string $slug): Article
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return Article::create([
            'user_id' => $user->id,
            'title' => 'Articolo '.$slug,
            'slug' => $slug,
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $status === Article::STATUS_PUBLISHED ? now()->subDay() : null,
        ]);
    }

    private function concept(string $name, string $status = Concept::STATUS_ACTIVE): Concept
    {
        return Concept::create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'status' => $status,
        ]);
    }

    private function approved(
        Concept $concept,
        string $question,
        ?Article $target = null,
        ?string $answer = 'Risposta.',
    ): ConceptQuestion {
        return $concept->questions()->create([
            'question' => $question,
            'answer_summary' => $answer,
            'target_article_id' => $target?->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);
    }

    public function test_audit_reports_every_real_approved_question_integrity_case(): void
    {
        $published = $this->article(Article::STATUS_PUBLISHED, 'pubblicato');
        $draft = $this->article(Article::STATUS_DRAFT, 'bozza');

        $ready = $this->approved($this->concept('Pronto'), 'Domanda pronta', $published);
        $noTarget = $this->approved($this->concept('Senza target'), 'Domanda senza target');
        $noAnswer = $this->approved($this->concept('Senza risposta'), 'Domanda senza risposta', $published, null);
        $draftTarget = $this->approved($this->concept('Target bozza'), 'Domanda target bozza', $draft);
        $inactive = $this->approved(
            $this->concept('Inattivo', Concept::STATUS_INACTIVE),
            'Domanda inattiva',
            $published,
        );

        $rows = collect(app(ConceptQuestionReadinessService::class)->auditApproved())
            ->keyBy('question_id');

        $this->assertTrue($rows[$ready->id]['answerable']);
        $this->assertSame([], $rows[$ready->id]['findings']);
        $this->assertSame(
            [ConceptQuestionReadinessService::TARGET_MISSING],
            array_column($rows[$noTarget->id]['findings'], 'code'),
        );
        $this->assertSame(
            [ConceptQuestionReadinessService::ANSWER_MISSING],
            array_column($rows[$noAnswer->id]['findings'], 'code'),
        );
        $this->assertSame(
            [ConceptQuestionReadinessService::TARGET_NOT_PUBLISHED],
            array_column($rows[$draftTarget->id]['findings'], 'code'),
        );
        $this->assertSame(
            [ConceptQuestionReadinessService::CONCEPT_NOT_ACTIVE],
            array_column($rows[$inactive->id]['findings'], 'code'),
        );
    }

    public function test_deleted_target_becomes_target_missing_via_null_on_delete(): void
    {
        $target = $this->article(Article::STATUS_PUBLISHED, 'da-eliminare');
        $question = $this->approved($this->concept('Target eliminato'), 'Domanda', $target);

        $target->delete();

        $question->refresh();
        $this->assertNull($question->target_article_id);

        $row = collect(app(ConceptQuestionReadinessService::class)->auditApproved())
            ->firstWhere('question_id', $question->id);

        $this->assertSame(
            [ConceptQuestionReadinessService::TARGET_MISSING],
            array_column($row['findings'], 'code'),
        );
    }

    public function test_draft_questions_are_outside_the_approved_integrity_audit(): void
    {
        $concept = $this->concept('Bozza');
        $concept->questions()->create([
            'question' => 'Domanda bozza',
            'status' => ConceptQuestion::STATUS_DRAFT,
        ]);

        $this->assertSame([], app(ConceptQuestionReadinessService::class)->auditApproved());
    }

    public function test_approved_audit_has_a_bounded_query_budget(): void
    {
        $this->approved($this->concept('Uno'), 'Domanda uno');
        $this->approved($this->concept('Due'), 'Domanda due');
        $this->approved($this->concept('Tre'), 'Domanda tre');

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(ConceptQuestionReadinessService::class)->auditApproved();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(2, $queries);
    }

    public function test_audit_never_mutates_questions(): void
    {
        $question = $this->approved($this->concept('Immutabile'), 'Domanda immutabile');
        $before = $question->fresh()->getAttributes();

        app(ConceptQuestionReadinessService::class)->auditApproved();

        $this->assertSame($before, $question->fresh()->getAttributes());
    }
}
