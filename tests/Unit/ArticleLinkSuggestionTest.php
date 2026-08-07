<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleLinkSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    // 1. Un suggerimento appartiene ai due articoli coinvolti
    public function test_a_suggestion_belongs_to_its_source_and_target_article(): void
    {
        $source = $this->article(['title' => 'Sorgente']);
        $target = $this->article(['title' => 'Destinazione']);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari',
            'reason' => 'Categoria condivisa: Energia & Clima',
            'confidence_score' => 70,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
        ]);

        $this->assertSame('Sorgente', $suggestion->sourceArticle->title);
        $this->assertSame('Destinazione', $suggestion->targetArticle->title);
        $this->assertTrue($suggestion->isActionable());
    }

    // 2. Vincolo unique (source, target): un solo suggerimento per coppia
    public function test_unique_constraint_prevents_duplicate_suggestions_for_the_same_pair(): void
    {
        $source = $this->article();
        $target = $this->article();

        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'termine',
            'reason' => 'motivo',
            'confidence_score' => 50,
        ]);

        $this->expectException(QueryException::class);
        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'altro termine',
            'reason' => 'altro motivo',
            'confidence_score' => 60,
        ]);
    }

    // 3. Cancellare l'articolo sorgente o target cancella i suggerimenti collegati
    public function test_deleting_either_article_cascades_to_the_suggestion(): void
    {
        $source = $this->article();
        $target = $this->article();
        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'termine',
            'reason' => 'motivo',
            'confidence_score' => 50,
        ]);

        $source->delete();

        $this->assertSame(0, ArticleLinkSuggestion::count());
    }

    // 4. Scope "proposed" isola solo i suggerimenti ancora da rivedere
    public function test_proposed_scope_excludes_reviewed_suggestions(): void
    {
        $source = $this->article();
        $accepted = $this->article();
        $ignored = $this->article();
        $pending = $this->article();

        ArticleLinkSuggestion::create(['source_article_id' => $source->id, 'target_article_id' => $accepted->id, 'anchor_text' => 'a', 'reason' => 'r', 'confidence_score' => 50, 'status' => ArticleLinkSuggestion::STATUS_ACCEPTED]);
        ArticleLinkSuggestion::create(['source_article_id' => $source->id, 'target_article_id' => $ignored->id, 'anchor_text' => 'b', 'reason' => 'r', 'confidence_score' => 50, 'status' => ArticleLinkSuggestion::STATUS_IGNORED]);
        ArticleLinkSuggestion::create(['source_article_id' => $source->id, 'target_article_id' => $pending->id, 'anchor_text' => 'c', 'reason' => 'r', 'confidence_score' => 50, 'status' => ArticleLinkSuggestion::STATUS_PROPOSED]);

        $proposed = ArticleLinkSuggestion::proposed()->forSource($source->id)->get();

        $this->assertCount(1, $proposed);
        $this->assertSame($pending->id, $proposed->first()->target_article_id);
    }
}
