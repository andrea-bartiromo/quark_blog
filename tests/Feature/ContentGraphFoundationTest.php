<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleConcept;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Models\User;
use App\Services\ContentGraph\ContentGraphService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

class ContentGraphFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create();
    }

    public function test_concept_slug_is_generated_unique_and_defaults_to_draft(): void
    {
        $concept = Concept::create(['name' => 'Relativita generale', 'short_definition' => 'Teoria geometrica della gravita.']);
        $this->assertSame('relativita-generale', $concept->slug);
        $this->assertSame(Concept::STATUS_DRAFT, $concept->status);
        $this->expectException(QueryException::class);
        Concept::create(['name' => 'Altro', 'slug' => 'relativita-generale']);
    }

    public function test_question_slug_is_generated_unique_and_defaults_to_draft(): void
    {
        $concept = Concept::create(['name' => 'Buchi neri']);
        $question = $concept->questions()->create(['question' => 'Come si forma un buco nero?', 'answer_summary' => 'Una domanda editoriale strutturata.']);
        $this->assertSame('come-si-forma-un-buco-nero', $question->slug);
        $this->assertSame(ConceptQuestion::STATUS_DRAFT, $question->status);
        $this->assertNull($question->target_article_id);
        $this->expectException(QueryException::class);
        $concept->questions()->create(['question' => 'Altra domanda', 'slug' => 'come-si-forma-un-buco-nero']);
    }

    public function test_aliases_and_article_links_are_queryable_and_unique(): void
    {
        $concept = Concept::create(['name' => 'Materia oscura']);
        $concept->aliases()->create(['alias' => 'dark matter']);
        $article = $this->publishedArticle('Materia oscura spiegata');
        $concept->articleLinks()->create(['article_id' => $article->id, 'relation_type' => ArticleConcept::RELATION_PRIMARY, 'weight' => 100]);
        $this->assertSame('dark matter', $concept->aliases()->firstOrFail()->alias);
        $this->assertSame($article->id, $concept->articleLinks()->firstOrFail()->article_id);
        $this->expectException(QueryException::class);
        $concept->aliases()->create(['alias' => 'dark matter']);
    }

    public function test_same_article_cannot_link_to_same_concept_twice(): void
    {
        $concept = Concept::create(['name' => 'Entropia']);
        $article = $this->publishedArticle('Entropia');
        ArticleConcept::create(['article_id' => $article->id, 'concept_id' => $concept->id]);
        $this->expectException(QueryException::class);
        ArticleConcept::create(['article_id' => $article->id, 'concept_id' => $concept->id]);
    }

    public function test_deleting_concept_cascades_graph_rows_but_never_article(): void
    {
        $concept = Concept::create(['name' => 'Termodinamica']);
        $article = $this->publishedArticle('Secondo principio');
        $concept->aliases()->create(['alias' => 'termo']);
        $concept->questions()->create(['question' => 'Perche aumenta entropia?']);
        $concept->articleLinks()->create(['article_id' => $article->id]);
        $concept->delete();
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
        $this->assertDatabaseMissing('concept_aliases', ['concept_id' => $concept->id]);
        $this->assertDatabaseMissing('concept_questions', ['concept_id' => $concept->id]);
        $this->assertDatabaseMissing('article_concepts', ['concept_id' => $concept->id]);
    }

    public function test_deleting_target_article_nulls_question_answer_reference(): void
    {
        $concept = Concept::create(['name' => 'Fotoni']);
        $article = $this->publishedArticle('Che cosa sono i fotoni');
        $question = $concept->questions()->create(['question' => 'Che cosa trasporta la luce?', 'target_article_id' => $article->id]);
        $article->delete();
        $this->assertNull($question->fresh()->target_article_id);
        $this->assertDatabaseHas('concept_questions', ['id' => $question->id]);
    }

    public function test_weight_defaults_to_fifty(): void
    {
        $concept = Concept::create(['name' => 'Meccanica quantistica']);
        $article = $this->publishedArticle('Quantistica');
        $link = ArticleConcept::create(['article_id' => $article->id, 'concept_id' => $concept->id]);
        $this->assertSame(50, $link->fresh()->weight);
        $this->assertSame(1, DB::table('article_concepts')->count());
    }

    public function test_domain_service_links_idempotently_and_updates_editorial_metadata(): void
    {
        $service = $this->service();
        $concept = Concept::create(['name' => 'Relativita speciale']);
        $article = $this->publishedArticle('Relativita speciale');
        $first = $service->linkArticle($article, $concept);
        $second = $service->linkArticle($article, $concept, ArticleConcept::RELATION_PRIMARY, 90);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ArticleConcept::query()->count());
        $this->assertSame(ArticleConcept::RELATION_PRIMARY, $second->relation_type);
        $this->assertSame(90, $second->weight);
    }

    public function test_domain_service_rejects_unknown_relation_type_and_out_of_range_weight(): void
    {
        $service = $this->service();
        $concept = Concept::create(['name' => 'Gravita']);
        $article = $this->publishedArticle('Gravita');
        try {
            $service->linkArticle($article, $concept, 'automatic');
            $this->fail('Unknown relation type must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('article_concepts', 0);
        }
        $this->expectException(InvalidArgumentException::class);
        $service->linkArticle($article, $concept, ArticleConcept::RELATION_SUPPORTING, 256);
    }

    public function test_discoverable_concepts_require_published_article_and_active_concept(): void
    {
        $service = $this->service();
        $active = Concept::create(['name' => 'Spaziotempo', 'status' => Concept::STATUS_ACTIVE]);
        $draftConcept = Concept::create(['name' => 'Concetto non approvato']);
        $published = $this->publishedArticle('Pubblico');
        $draft = $this->articleWithStatus('Bozza', Article::STATUS_DRAFT, null);
        $review = $this->articleWithStatus('Review', Article::STATUS_REVIEW, null);
        $scheduled = $this->articleWithStatus('Programmato', Article::STATUS_SCHEDULED, now()->addDay());
        foreach ([$published, $draft, $review, $scheduled] as $article) {
            $service->linkArticle($article, $active, ArticleConcept::RELATION_PRIMARY, 100);
        }
        $service->linkArticle($published, $draftConcept, ArticleConcept::RELATION_SUPPORTING, 80);
        $publishedLinks = $service->discoverableConceptsForArticle($published);
        $this->assertSame([$active->id], $publishedLinks->pluck('concept_id')->all());
        $this->assertCount(0, $service->discoverableConceptsForArticle($draft));
        $this->assertCount(0, $service->discoverableConceptsForArticle($review));
        $this->assertCount(0, $service->discoverableConceptsForArticle($scheduled));
    }

    public function test_answerable_questions_require_active_concept_approved_question_summary_and_published_target(): void
    {
        $service = $this->service();
        $active = Concept::create(['name' => 'Elettromagnetismo', 'status' => Concept::STATUS_ACTIVE]);
        $inactive = Concept::create(['name' => 'Concetto inattivo', 'status' => Concept::STATUS_INACTIVE]);
        $published = $this->publishedArticle('Luce');
        $draft = $this->articleWithStatus('Bozza risposta', Article::STATUS_DRAFT, null);
        $scheduled = $this->articleWithStatus('Risposta futura', Article::STATUS_SCHEDULED, now()->addDay());
        $answerable = $active->questions()->create([
            'question' => 'Che cosa trasporta la luce?',
            'answer_summary' => 'La luce trasporta energia e quantità di moto tramite i fotoni.',
            'status' => ConceptQuestion::STATUS_APPROVED,
            'target_article_id' => $published->id,
            'sort_order' => 1,
        ]);
        $active->questions()->create(['question' => 'Domanda approvata ma senza sommario', 'answer_summary' => '   ', 'status' => ConceptQuestion::STATUS_APPROVED, 'target_article_id' => $published->id, 'sort_order' => 2]);
        $active->questions()->create(['question' => 'Domanda ancora draft', 'answer_summary' => 'Sommario presente.', 'target_article_id' => $published->id, 'sort_order' => 3]);
        $active->questions()->create(['question' => 'Domanda con risposta draft', 'answer_summary' => 'Sommario presente.', 'status' => ConceptQuestion::STATUS_APPROVED, 'target_article_id' => $draft->id, 'sort_order' => 4]);
        $active->questions()->create(['question' => 'Domanda con risposta programmata', 'answer_summary' => 'Sommario presente.', 'status' => ConceptQuestion::STATUS_APPROVED, 'target_article_id' => $scheduled->id, 'sort_order' => 5]);
        $inactive->questions()->create(['question' => 'Domanda su concetto inattivo', 'answer_summary' => 'Sommario presente.', 'status' => ConceptQuestion::STATUS_APPROVED, 'target_article_id' => $published->id]);
        $questions = $service->answerableQuestionsForConcept($active);
        $this->assertSame([$answerable->id], $questions->pluck('id')->all());
        $this->assertCount(0, $service->answerableQuestionsForConcept($inactive));
    }

    public function test_foundation_does_not_publish_concept_or_question_routes(): void
    {
        $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri());
        $this->assertFalse($uris->contains(fn (string $uri) => str_starts_with($uri, 'concetti')));
        $this->assertFalse($uris->contains(fn (string $uri) => str_starts_with($uri, 'domande')));
    }

    private function service(): ContentGraphService
    {
        return app(ContentGraphService::class);
    }

    private function publishedArticle(string $title): Article
    {
        return $this->articleWithStatus($title, Article::STATUS_PUBLISHED, now()->subMinute());
    }

    private function articleWithStatus(string $title, string $status, $publishedAt): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo articolo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $publishedAt,
        ]);
    }
}
