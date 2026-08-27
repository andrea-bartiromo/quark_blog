<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Models\User;
use App\Services\ContentGraph\ConceptHealthService;
use App\Services\ContentGraph\ContentGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConceptHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ConceptHealthService
    {
        return app(ConceptHealthService::class);
    }

    private function publishedArticle(string $slug): Article
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
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 2,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_complete_active_concept_is_ready(): void
    {
        $concept = Concept::create([
            'name' => 'Entropia',
            'slug' => 'entropia',
            'status' => Concept::STATUS_ACTIVE,
        ]);
        $article = $this->publishedArticle('entropia-articolo');
        app(ContentGraphService::class)->linkArticle($article, $concept);
        $concept->questions()->create([
            'question' => 'Che cos\'è l\'entropia?',
            'answer_summary' => 'Una misura termodinamica.',
            'target_article_id' => $article->id,
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $result = $this->service()->classify($concept);

        $this->assertSame(ConceptHealthService::READY, $result['health']);
        $this->assertSame('Pronto', $result['label']);
        $this->assertSame([], $result['codes']);
    }

    public function test_active_concept_missing_coverage_is_incomplete_with_explainable_codes(): void
    {
        $concept = Concept::create([
            'name' => 'Vuoto quantistico',
            'slug' => 'vuoto-quantistico',
            'status' => Concept::STATUS_ACTIVE,
        ]);

        $result = $this->service()->classify($concept);

        $this->assertSame(ConceptHealthService::INCOMPLETE, $result['health']);
        $this->assertSame([
            ConceptHealthService::ACTIVE_WITHOUT_ARTICLE_LINK,
            ConceptHealthService::ACTIVE_WITHOUT_QUESTIONS,
            ConceptHealthService::NO_PUBLIC_ANSWERABLE_QUESTION,
        ], $result['codes']);
    }

    public function test_draft_questions_do_not_count_as_public_answerable(): void
    {
        $concept = Concept::create([
            'name' => 'Materia oscura',
            'slug' => 'materia-oscura',
            'status' => Concept::STATUS_ACTIVE,
        ]);
        $article = $this->publishedArticle('materia-oscura-articolo');
        app(ContentGraphService::class)->linkArticle($article, $concept);
        $concept->questions()->create([
            'question' => 'Cos\'è la materia oscura?',
            'answer_summary' => 'Una componente non luminosa.',
            'target_article_id' => $article->id,
            'status' => ConceptQuestion::STATUS_DRAFT,
        ]);

        $result = $this->service()->classify($concept);

        $this->assertSame(ConceptHealthService::INCOMPLETE, $result['health']);
        $this->assertSame(
            [ConceptHealthService::NO_PUBLIC_ANSWERABLE_QUESTION],
            $result['codes'],
        );
    }

    public function test_inactive_concept_linked_to_a_published_article_requires_attention(): void
    {
        $concept = Concept::create([
            'name' => 'Concetto archiviato',
            'slug' => 'concetto-archiviato',
            'status' => Concept::STATUS_INACTIVE,
        ]);
        $article = $this->publishedArticle('articolo-pubblico');
        app(ContentGraphService::class)->linkArticle($article, $concept);

        $result = $this->service()->classify($concept);

        $this->assertSame(ConceptHealthService::ATTENTION, $result['health']);
        $this->assertSame(
            [ConceptHealthService::INACTIVE_WITH_PUBLIC_RELATIONS],
            $result['codes'],
        );
    }

    public function test_inactive_unrelated_concept_has_no_actionable_finding(): void
    {
        $concept = Concept::create([
            'name' => 'Concetto inattivo',
            'slug' => 'concetto-inattivo',
            'status' => Concept::STATUS_INACTIVE,
        ]);

        $result = $this->service()->classify($concept);

        $this->assertSame(ConceptHealthService::READY, $result['health']);
        $this->assertSame([], $result['codes']);
    }

    public function test_all_uses_a_bounded_query_without_n_plus_one(): void
    {
        Concept::create(['name' => 'Uno', 'slug' => 'uno', 'status' => Concept::STATUS_ACTIVE]);
        Concept::create(['name' => 'Due', 'slug' => 'due', 'status' => Concept::STATUS_ACTIVE]);
        Concept::create(['name' => 'Tre', 'slug' => 'tre', 'status' => Concept::STATUS_INACTIVE]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $results = $this->service()->all();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(3, $results);
        $this->assertCount(1, $queries);
    }

    public function test_classification_never_mutates_domain_records(): void
    {
        $concept = Concept::create([
            'name' => 'Immutabile',
            'slug' => 'immutabile',
            'status' => Concept::STATUS_ACTIVE,
        ]);
        $before = $concept->fresh()->getAttributes();

        $this->service()->classify($concept);

        $this->assertSame($before, $concept->fresh()->getAttributes());
    }
}
