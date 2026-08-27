<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Models\User;
use App\Services\ContentGraph\PublicAnswerableQuestionCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicAnswerableQuestionCoverageServiceTest extends TestCase
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
            'published_at' => match ($status) {
                Article::STATUS_PUBLISHED => now()->subDay(),
                Article::STATUS_SCHEDULED => now()->addDay(),
                default => null,
            },
        ]);
    }

    private function concept(string $name): Concept
    {
        return Concept::create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'status' => Concept::STATUS_ACTIVE,
        ]);
    }

    private function approvedQuestion(Concept $concept, Article $target, string $question): void
    {
        $concept->questions()->create([
            'question' => $question,
            'answer_summary' => 'Risposta completa.',
            'target_article_id' => $target->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);
    }

    public function test_summary_distinguishes_zero_draft_approved_not_public_and_answerable(): void
    {
        $zero = $this->concept('Zero domande');

        $draftOnly = $this->concept('Solo draft');
        $draftOnly->questions()->create([
            'question' => 'Domanda draft',
            'status' => ConceptQuestion::STATUS_DRAFT,
        ]);

        $approvedDraftTarget = $this->concept('Target draft');
        $this->approvedQuestion(
            $approvedDraftTarget,
            $this->article(Article::STATUS_DRAFT, 'target-draft'),
            'Domanda target draft',
        );

        $answerable = $this->concept('Answerable');
        $this->approvedQuestion(
            $answerable,
            $this->article(Article::STATUS_PUBLISHED, 'target-published'),
            'Domanda pubblica',
        );

        $summary = app(PublicAnswerableQuestionCoverageService::class)->summary();
        $rows = collect($summary['detail'])->keyBy('concept_id');

        $this->assertSame(4, $summary['active_concepts_total']);
        $this->assertSame(1, $summary['with_answerable_question']);
        $this->assertSame(3, $summary['without_answerable_question']);
        $this->assertSame(PublicAnswerableQuestionCoverageService::NO_QUESTIONS, $rows[$zero->id]['coverage']);
        $this->assertSame(PublicAnswerableQuestionCoverageService::DRAFT_ONLY, $rows[$draftOnly->id]['coverage']);
        $this->assertSame(PublicAnswerableQuestionCoverageService::APPROVED_NOT_PUBLIC, $rows[$approvedDraftTarget->id]['coverage']);
        $this->assertSame(PublicAnswerableQuestionCoverageService::ANSWERABLE, $rows[$answerable->id]['coverage']);
    }

    public function test_review_scheduled_and_draft_targets_are_not_publicly_answerable(): void
    {
        foreach ([
            Article::STATUS_DRAFT,
            Article::STATUS_REVIEW,
            Article::STATUS_SCHEDULED,
        ] as $status) {
            $concept = $this->concept('Concept '.$status);
            $this->approvedQuestion(
                $concept,
                $this->article($status, 'target-'.$status),
                'Domanda '.$status,
            );
        }

        $summary = app(PublicAnswerableQuestionCoverageService::class)->summary();

        $this->assertSame(3, $summary['active_concepts_total']);
        $this->assertSame(0, $summary['with_answerable_question']);
        $this->assertSame(3, $summary['without_answerable_question']);
        $this->assertSame(
            [PublicAnswerableQuestionCoverageService::APPROVED_NOT_PUBLIC],
            collect($summary['detail'])->pluck('coverage')->unique()->values()->all(),
        );
    }

    public function test_published_target_is_answerable_only_when_the_question_is_complete(): void
    {
        $article = $this->article(Article::STATUS_PUBLISHED, 'target-completeness');

        $missingAnswer = $this->concept('Risposta mancante');
        $missingAnswer->questions()->create([
            'question' => 'Domanda senza risposta',
            'target_article_id' => $article->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $missingTarget = $this->concept('Target mancante');
        $missingTarget->questions()->create([
            'question' => 'Domanda senza target',
            'answer_summary' => 'Risposta.',
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $summary = app(PublicAnswerableQuestionCoverageService::class)->summary();

        $this->assertSame(0, $summary['with_answerable_question']);
        $this->assertSame(
            [PublicAnswerableQuestionCoverageService::APPROVED_NOT_PUBLIC],
            collect($summary['detail'])->pluck('coverage')->unique()->values()->all(),
        );
    }

    public function test_inactive_concepts_are_excluded(): void
    {
        Concept::create([
            'name' => 'Inattivo',
            'slug' => 'inattivo',
            'status' => Concept::STATUS_INACTIVE,
        ]);

        $summary = app(PublicAnswerableQuestionCoverageService::class)->summary();

        $this->assertSame(0, $summary['active_concepts_total']);
        $this->assertSame([], $summary['detail']);
    }

    public function test_summary_query_count_is_bounded(): void
    {
        $this->concept('Uno');
        $this->concept('Due');
        $this->concept('Tre');

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(PublicAnswerableQuestionCoverageService::class)->summary();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
    }
}
