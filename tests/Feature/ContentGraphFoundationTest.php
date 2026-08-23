<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleConcept;
use App\Models\Concept;
use App\Services\ContentGraph\ContentGraphService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ContentGraphFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_concept_slug_is_generated_and_unique(): void
    {
        $concept = Concept::create(['name' => 'Relativita generale']);

        $this->assertSame('relativita-generale', $concept->slug);

        $this->expectException(QueryException::class);
        Concept::create(['name' => 'Altro', 'slug' => 'relativita-generale']);
    }

    public function test_aliases_questions_and_article_links_are_queryable_from_concept(): void
    {
        $concept = Concept::create(['name' => 'Buchi neri']);
        $concept->aliases()->create(['alias' => 'black holes']);
        $concept->questions()->create([
            'question' => 'Come si forma un buco nero?',
            'answer_summary' => 'Una domanda editoriale strutturata.',
        ]);

        $article = Article::factory()->create();
        $concept->articleLinks()->create([
            'article_id' => $article->id,
            'relation_type' => ArticleConcept::RELATION_PRIMARY,
            'weight' => 100,
        ]);

        $this->assertSame('black holes', $concept->aliases()->firstOrFail()->alias);
        $this->assertSame('Come si forma un buco nero?', $concept->questions()->firstOrFail()->question);
        $this->assertSame($article->id, $concept->articleLinks()->firstOrFail()->article_id);
    }

    public function test_same_article_cannot_link_to_same_concept_twice(): void
    {
        $concept = Concept::create(['name' => 'Materia oscura']);
        $article = Article::factory()->create();

        ArticleConcept::create([
            'article_id' => $article->id,
            'concept_id' => $concept->id,
        ]);

        $this->expectException(QueryException::class);
        ArticleConcept::create([
            'article_id' => $article->id,
            'concept_id' => $concept->id,
        ]);
    }

    public function test_deleting_concept_cascades_graph_edges_but_not_article(): void
    {
        $concept = Concept::create(['name' => 'Entropia']);
        $article = Article::factory()->create();

        $concept->aliases()->create(['alias' => 'secondo principio']);
        $concept->questions()->create(['question' => 'Perche aumenta entropia?']);
        $concept->articleLinks()->create(['article_id' => $article->id]);

        $concept->delete();

        $this->assertDatabaseHas('articles', ['id' => $article->id]);
        $this->assertDatabaseMissing('concept_aliases', ['concept_id' => $concept->id]);
        $this->assertDatabaseMissing('concept_questions', ['concept_id' => $concept->id]);
        $this->assertDatabaseMissing('article_concepts', ['concept_id' => $concept->id]);
    }

    public function test_weight_defaults_to_fifty(): void
    {
        $concept = Concept::create(['name' => 'Meccanica quantistica']);
        $article = Article::factory()->create();

        $link = ArticleConcept::create([
            'article_id' => $article->id,
            'concept_id' => $concept->id,
        ]);

        $this->assertSame(50, $link->fresh()->weight);
        $this->assertSame(1, DB::table('article_concepts')->count());
    }

    public function test_domain_service_links_idempotently_and_updates_editorial_metadata(): void
    {
        $service = app(ContentGraphService::class);
        $concept = Concept::create(['name' => 'Relativita speciale']);
        $article = Article::factory()->create();

        $first = $service->linkArticle($article, $concept);
        $second = $service->linkArticle($article, $concept, ArticleConcept::RELATION_PRIMARY, 90);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ArticleConcept::query()->count());
        $this->assertSame(ArticleConcept::RELATION_PRIMARY, $second->relation_type);
        $this->assertSame(90, $second->weight);
    }

    public function test_domain_service_rejects_unknown_relation_type(): void
    {
        $service = app(ContentGraphService::class);
        $concept = Concept::create(['name' => 'Gravita']);
        $article = Article::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $service->linkArticle($article, $concept, 'automatic');
    }

    public function test_domain_service_rejects_out_of_range_weight(): void
    {
        $service = app(ContentGraphService::class);
        $concept = Concept::create(['name' => 'Spaziotempo']);
        $article = Article::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $service->linkArticle($article, $concept, ArticleConcept::RELATION_SUPPORTING, 256);
    }

    public function test_domain_service_reads_concepts_by_weight_and_only_active_questions(): void
    {
        $service = app(ContentGraphService::class);
        $article = Article::factory()->create();
        $low = Concept::create(['name' => 'Fotoni']);
        $high = Concept::create(['name' => 'Elettromagnetismo']);

        $low->aliases()->create(['alias' => 'quanti di luce']);
        $service->linkArticle($article, $low, ArticleConcept::RELATION_SUPPORTING, 20);
        $service->linkArticle($article, $high, ArticleConcept::RELATION_PRIMARY, 100);

        $high->questions()->create([
            'question' => 'Come nasce un campo elettromagnetico?',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $high->questions()->create([
            'question' => 'Domanda disattivata',
            'sort_order' => 1,
            'is_active' => false,
        ]);
        $high->questions()->create([
            'question' => 'Che cosa trasporta la luce?',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $links = $service->conceptsForArticle($article);
        $questions = $service->questionsForConcept($high);

        $this->assertSame([$high->id, $low->id], $links->pluck('concept_id')->all());
        $this->assertTrue($links->last()->relationLoaded('concept'));
        $this->assertTrue($links->last()->concept->relationLoaded('aliases'));
        $this->assertSame([
            'Che cosa trasporta la luce?',
            'Come nasce un campo elettromagnetico?',
        ], $questions->pluck('question')->all());
    }
}
