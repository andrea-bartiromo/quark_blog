<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Models\User;
use App\Services\ContentGraph\ContentGraphCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission 19 — Content Graph Coverage Metrics: aggregati read-only, mai un
 * ricalcolo delle regole di pubblicazione già espresse da
 * Article::published() / ContentGraphService::answerableQuestionsForConcept().
 */
class ContentGraphCoverageServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ContentGraphCoverageService
    {
        return app(ContentGraphCoverageService::class);
    }

    private function article(string $title, string $status = Article::STATUS_PUBLISHED): Article
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return Article::create([
            'user_id' => $user->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $status === Article::STATUS_PUBLISHED ? now()->subDay() : null,
        ]);
    }

    public function test_summary_with_no_data_is_all_zero_and_never_divides_by_zero(): void
    {
        $summary = $this->service()->summary();

        $this->assertSame(0, $summary['articles']['published_total']);
        $this->assertSame(0.0, $summary['articles']['coverage_percent']);
        $this->assertSame(0, $summary['concepts']['total']);
        $this->assertSame(0, $summary['questions']['total']);
    }

    public function test_article_coverage_counts_only_published_articles_with_a_concept_link(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $linked = $this->article('Con concetto');
        $linked->contentConcepts()->create(['concept_id' => $concept->id, 'relation_type' => 'supporting', 'weight' => 50]);
        $this->article('Senza concetto');
        // Bozza collegata: non pubblicata, non deve contare nel totale.
        $draft = $this->article('Bozza collegata', Article::STATUS_DRAFT);
        $draft->contentConcepts()->create(['concept_id' => $concept->id, 'relation_type' => 'supporting', 'weight' => 50]);

        $summary = $this->service()->summary();

        $this->assertSame(2, $summary['articles']['published_total']);
        $this->assertSame(1, $summary['articles']['published_with_concept_link']);
        $this->assertSame(1, $summary['articles']['published_without_concept_link']);
        $this->assertSame(50.0, $summary['articles']['coverage_percent']);
    }

    public function test_concept_coverage_splits_by_status_and_flags_orphaned_active_concepts(): void
    {
        $article = $this->article('Termodinamica base');
        $linkedActive = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $linkedActive->articleLinks()->create(['article_id' => $article->id, 'relation_type' => 'primary', 'weight' => 90]);
        Concept::create(['name' => 'Orfano attivo', 'slug' => 'orfano-attivo', 'status' => 'active']);
        Concept::create(['name' => 'Bozza', 'slug' => 'bozza', 'status' => 'draft']);
        Concept::create(['name' => 'Inattivo', 'slug' => 'inattivo', 'status' => 'inactive']);

        $summary = $this->service()->summary();

        $this->assertSame(4, $summary['concepts']['total']);
        $this->assertSame(['draft' => 1, 'active' => 2, 'inactive' => 1], $summary['concepts']['by_status']);
        $this->assertSame(1, $summary['concepts']['active_with_article_link']);
        $this->assertSame(1, $summary['concepts']['active_without_article_link']);
    }

    public function test_question_coverage_counts_by_status_and_publicly_answerable_total(): void
    {
        $article = $this->article('Termodinamica base');
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $concept->questions()->create([
            'question' => 'Domanda pubblica',
            'answer_summary' => 'Risposta.',
            'target_article_id' => $article->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);
        $concept->questions()->create(['question' => 'Domanda bozza', 'status' => ConceptQuestion::STATUS_DRAFT]);
        // Concetto attivo senza alcuna domanda.
        Concept::create(['name' => 'Senza domande', 'slug' => 'senza-domande', 'status' => 'active']);

        $summary = $this->service()->summary();

        $this->assertSame(2, $summary['questions']['total']);
        $this->assertSame(1, $summary['questions']['by_status'][ConceptQuestion::STATUS_APPROVED]);
        $this->assertSame(1, $summary['questions']['by_status'][ConceptQuestion::STATUS_DRAFT]);
        $this->assertSame(1, $summary['questions']['publicly_answerable_total']);
        $this->assertSame(1, $summary['questions']['active_concepts_without_questions']);
    }

    public function test_an_approved_question_targeting_an_unpublished_article_is_not_publicly_answerable(): void
    {
        $draftArticle = $this->article('Bozza target', Article::STATUS_DRAFT);
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $concept->questions()->create([
            'question' => 'Domanda con target non pubblico',
            'answer_summary' => 'Risposta.',
            'target_article_id' => $draftArticle->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $summary = $this->service()->summary();

        $this->assertSame(0, $summary['questions']['publicly_answerable_total']);
    }
}
