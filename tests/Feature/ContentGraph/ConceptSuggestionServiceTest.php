<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Article;
use App\Models\ArticleConcept;
use App\Models\Concept;
use App\Models\User;
use App\Services\ContentGraph\ConceptSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission 20 — Article Editor Concept Suggestions V1: suggerimenti
 * read-only basati sul riconoscimento di nome/alias di Concept già attivi
 * nel testo dell'articolo — mai un collegamento automatico (verificato
 * qui non mutando mai article_concepts).
 */
class ConceptSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ConceptSuggestionService
    {
        return app(ConceptSuggestionService::class);
    }

    private function article(string $title, string $body = '<p>Corpo.</p>', string $excerpt = 'Estratto.'): Article
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return Article::create([
            'user_id' => $user->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => $body,
            'excerpt' => $excerpt,
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 2,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_a_concept_whose_name_appears_in_the_article_body_is_suggested(): void
    {
        $article = $this->article('Termodinamica base', '<p>L\'entropia di un sistema isolato non diminuisce mai.</p>');
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);

        $suggestions = $this->service()->suggestForArticle($article);

        $this->assertCount(1, $suggestions);
        $this->assertTrue($concept->is($suggestions[0]['concept']));
        $this->assertSame('entropia', mb_strtolower($suggestions[0]['matched_text'], 'UTF-8'));
    }

    public function test_a_concept_matched_via_an_alias_is_suggested_under_its_canonical_name(): void
    {
        $article = $this->article('Buchi neri', '<p>Il disordine termodinamico cresce sempre.</p>');
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $concept->aliases()->create(['alias' => 'disordine termodinamico']);

        $suggestions = $this->service()->suggestForArticle($article);

        $this->assertCount(1, $suggestions);
        $this->assertSame('Entropia', $suggestions[0]['concept']->name);
        $this->assertSame('disordine termodinamico', $suggestions[0]['matched_text']);
    }

    public function test_a_concept_already_linked_is_never_suggested_again(): void
    {
        $article = $this->article('Termodinamica base', '<p>L\'entropia di un sistema isolato non diminuisce mai.</p>');
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        ArticleConcept::create([
            'article_id' => $article->id,
            'concept_id' => $concept->id,
            'relation_type' => 'supporting',
            'weight' => 50,
        ]);

        $this->assertSame([], $this->service()->suggestForArticle($article));
    }

    public function test_a_draft_concept_is_never_suggested(): void
    {
        $article = $this->article('Termodinamica base', '<p>L\'entropia di un sistema isolato non diminuisce mai.</p>');
        Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'draft']);

        $this->assertSame([], $this->service()->suggestForArticle($article));
    }

    public function test_a_concept_not_mentioned_anywhere_in_the_article_is_never_suggested(): void
    {
        $article = $this->article('Buchi neri', '<p>La relativita generale descrive la gravita.</p>');
        Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);

        $this->assertSame([], $this->service()->suggestForArticle($article));
    }

    public function test_a_match_only_inside_a_longer_word_does_not_count(): void
    {
        $article = $this->article('Reti neurali', '<p>Le reti neurali artificiali sono un modello statistico.</p>');
        Concept::create(['name' => 'Rete', 'slug' => 'rete', 'status' => 'active']);

        // "rete" non compare mai come parola isolata nel testo (solo dentro
        // "reti", plurale — parola diversa) quindi non deve scattare un
        // match per confine di parola errato.
        $this->assertSame([], $this->service()->suggestForArticle($article));
    }

    public function test_a_match_found_only_in_the_title_is_still_suggested(): void
    {
        $article = $this->article('Entropia e disordine', '<p>Un testo che non la nomina di nuovo.</p>', 'Un breve estratto.');
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);

        $suggestions = $this->service()->suggestForArticle($article);

        $this->assertCount(1, $suggestions);
        $this->assertTrue($concept->is($suggestions[0]['concept']));
    }

    public function test_no_active_concepts_at_all_returns_an_empty_list_without_querying_further(): void
    {
        $article = $this->article('Entropia', '<p>Entropia entropia entropia.</p>');

        $this->assertSame([], $this->service()->suggestForArticle($article));
    }

    public function test_suggesting_never_mutates_article_concepts(): void
    {
        $article = $this->article('Termodinamica base', '<p>L\'entropia di un sistema isolato non diminuisce mai.</p>');
        Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);

        $this->service()->suggestForArticle($article);

        $this->assertDatabaseCount('article_concepts', 0);
    }
}
