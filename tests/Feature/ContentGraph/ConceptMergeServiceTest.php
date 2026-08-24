<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Models\User;
use App\Services\ContentGraph\ConceptMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Mission 18 — Merge Workflow Foundation: la fusione esplicita di un
 * concetto duplicato (individuato da ConceptDuplicateAuditService,
 * Mission 17) in un concetto canonico scelto dall'editor. Ogni test qui
 * verifica che nessuna riga figlia venga persa alla cancellazione del
 * duplicato (concept_aliases/article_concepts/concept_questions hanno
 * tutte cascadeOnDelete() su concept_id).
 */
class ConceptMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ConceptMergeService
    {
        return app(ConceptMergeService::class);
    }

    private function article(string $title): Article
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
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 2,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_a_concept_cannot_be_merged_into_itself(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->merge($concept, $concept);
    }

    public function test_merge_moves_aliases_and_preserves_the_duplicates_name_as_an_alias(): void
    {
        $target = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $duplicate = Concept::create(['name' => 'Entropia termodinamica', 'slug' => 'entropia-termodinamica', 'status' => 'draft']);
        $duplicate->aliases()->create(['alias' => 'Shannon entropy']);

        $report = $this->service()->merge($target, $duplicate);

        $this->assertSame(1, $report['aliases_moved']);
        $this->assertTrue($report['name_preserved_as_alias']);
        $target->refresh();
        $aliasTexts = $target->aliases()->pluck('alias')->all();
        $this->assertContains('Shannon entropy', $aliasTexts);
        $this->assertContains('Entropia termodinamica', $aliasTexts);
        $this->assertDatabaseMissing('concepts', ['id' => $duplicate->id]);
    }

    public function test_merge_skips_an_alias_that_already_exists_on_the_target_case_insensitively(): void
    {
        $target = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $target->aliases()->create(['alias' => 'Shannon entropy']);
        // Stesso nome del target (case-insensitive): il nome del duplicato
        // NON viene aggiunto come alias in più, isolando qui solo la
        // deduplicazione degli alias.
        $duplicate = Concept::create(['name' => 'entropia', 'slug' => 'entropia-bis', 'status' => 'draft']);
        $duplicate->aliases()->create(['alias' => 'SHANNON ENTROPY']);

        $report = $this->service()->merge($target, $duplicate);

        $this->assertSame(0, $report['aliases_moved']);
        $this->assertSame(1, $report['aliases_skipped_duplicate']);
        $this->assertFalse($report['name_preserved_as_alias']);
        $this->assertSame(1, $target->aliases()->count());
    }

    public function test_merge_does_not_duplicate_the_name_alias_when_it_already_matches_the_target(): void
    {
        $target = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $duplicate = Concept::create(['name' => 'entropia', 'slug' => 'entropia-bis', 'status' => 'draft']);

        $report = $this->service()->merge($target, $duplicate);

        $this->assertFalse($report['name_preserved_as_alias']);
        $this->assertSame(0, $target->aliases()->count());
    }

    public function test_merge_reassigns_a_non_conflicting_article_link(): void
    {
        $target = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $duplicate = Concept::create(['name' => 'Entropia bis', 'slug' => 'entropia-bis', 'status' => 'draft']);
        $article = $this->article('Termodinamica base');
        $duplicate->articleLinks()->create(['article_id' => $article->id, 'relation_type' => 'supporting', 'weight' => 40]);

        $report = $this->service()->merge($target, $duplicate);

        $this->assertSame(1, $report['article_links_moved']);
        $this->assertSame(0, $report['article_links_conflicts_resolved']);
        $this->assertDatabaseHas('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $target->id,
            'relation_type' => 'supporting',
            'weight' => 40,
        ]);
    }

    public function test_merge_resolves_an_article_link_conflict_in_favor_of_primary_over_supporting(): void
    {
        $target = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $duplicate = Concept::create(['name' => 'Entropia bis', 'slug' => 'entropia-bis', 'status' => 'draft']);
        $article = $this->article('Termodinamica base');
        $target->articleLinks()->create(['article_id' => $article->id, 'relation_type' => 'supporting', 'weight' => 30]);
        $duplicate->articleLinks()->create(['article_id' => $article->id, 'relation_type' => 'primary', 'weight' => 10]);

        $report = $this->service()->merge($target, $duplicate);

        $this->assertSame(0, $report['article_links_moved']);
        $this->assertSame(1, $report['article_links_conflicts_resolved']);
        $this->assertDatabaseCount('article_concepts', 1);
        $this->assertDatabaseHas('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $target->id,
            'relation_type' => 'primary',
            'weight' => 10,
        ]);
    }

    public function test_merge_resolves_an_article_link_conflict_by_higher_weight_when_relation_type_ties(): void
    {
        $target = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $duplicate = Concept::create(['name' => 'Entropia bis', 'slug' => 'entropia-bis', 'status' => 'draft']);
        $article = $this->article('Termodinamica base');
        $target->articleLinks()->create(['article_id' => $article->id, 'relation_type' => 'supporting', 'weight' => 30]);
        $duplicate->articleLinks()->create(['article_id' => $article->id, 'relation_type' => 'supporting', 'weight' => 80]);

        $this->service()->merge($target, $duplicate);

        $this->assertDatabaseCount('article_concepts', 1);
        $this->assertDatabaseHas('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $target->id,
            'weight' => 80,
        ]);
    }

    public function test_merge_keeps_the_targets_link_when_it_already_wins_the_conflict(): void
    {
        $target = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $duplicate = Concept::create(['name' => 'Entropia bis', 'slug' => 'entropia-bis', 'status' => 'draft']);
        $article = $this->article('Termodinamica base');
        $target->articleLinks()->create(['article_id' => $article->id, 'relation_type' => 'primary', 'weight' => 90]);
        $duplicate->articleLinks()->create(['article_id' => $article->id, 'relation_type' => 'supporting', 'weight' => 10]);

        $this->service()->merge($target, $duplicate);

        $this->assertDatabaseHas('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $target->id,
            'relation_type' => 'primary',
            'weight' => 90,
        ]);
    }

    public function test_merge_moves_questions_to_the_target_concept(): void
    {
        $target = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $duplicate = Concept::create(['name' => 'Entropia bis', 'slug' => 'entropia-bis', 'status' => 'draft']);
        $question = $duplicate->questions()->create([
            'question' => 'Cosa misura l\'entropia?',
            'answer_summary' => 'Il disordine di un sistema.',
            'status' => ConceptQuestion::STATUS_APPROVED,
        ]);

        $report = $this->service()->merge($target, $duplicate);

        $this->assertSame(1, $report['questions_moved']);
        $this->assertSame($target->id, $question->fresh()->concept_id);
    }

    public function test_merge_never_loses_children_when_duplicate_has_aliases_links_and_questions_together(): void
    {
        $target = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $duplicate = Concept::create(['name' => 'Entropia bis', 'slug' => 'entropia-bis', 'status' => 'draft']);
        $duplicate->aliases()->create(['alias' => 'Disordine termodinamico']);
        $article = $this->article('Termodinamica base');
        $duplicate->articleLinks()->create(['article_id' => $article->id, 'relation_type' => 'supporting', 'weight' => 50]);
        $duplicate->questions()->create(['question' => 'Domanda test', 'status' => ConceptQuestion::STATUS_DRAFT]);

        $this->service()->merge($target, $duplicate);

        $this->assertDatabaseMissing('concepts', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('concept_aliases', ['concept_id' => $target->id, 'alias' => 'Disordine termodinamico']);
        $this->assertDatabaseHas('article_concepts', ['concept_id' => $target->id, 'article_id' => $article->id]);
        $this->assertDatabaseHas('concept_questions', ['concept_id' => $target->id, 'question' => 'Domanda test']);
    }
}
