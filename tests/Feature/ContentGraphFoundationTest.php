<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleConcept;
use App\Models\Concept;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentGraphFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_concept_slug_is_generated_and_unique(): void
    {
        $concept = Concept::create(['name' => 'Relativita generale']);

        $this->assertSame('relativita-generale', $concept->slug);

        $this->expectException(\Illuminate\Database\QueryException::class);
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

        $this->expectException(\Illuminate\Database\QueryException::class);
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
}
