<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalLinkSuggestionLowInformationQualityTest extends TestCase
{
    use RefreshDatabase;

    private function article(User $author, string $title, string $slug): Article
    {
        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => $slug,
            'body' => '<p>Testo editoriale.</p>',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);
    }

    private function suggestion(Article $source, Article $target, string $reason): ArticleLinkSuggestion
    {
        return ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => $target->slug,
            'anchor_text' => 'testo',
            'context_excerpt' => '... testo ...',
            'reason' => $reason,
            'confidence_score' => 45,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
        ])->fresh();
    }

    public function test_lexical_suggestion_made_only_of_low_information_terms_is_superseded(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $source = $this->article($author, 'Sorgente', 'sorgente');
        $target = $this->article($author, 'Target', 'target');

        $suggestion = $this->suggestion(
            $source,
            $target,
            'Termini in comune: straordinarie, sorprendente, attraversare'
        );

        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->status);
    }

    public function test_one_informative_term_is_enough_to_keep_the_suggestion(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $source = $this->article($author, 'Sorgente', 'sorgente');
        $target = $this->article($author, 'Target', 'target');

        $suggestion = $this->suggestion(
            $source,
            $target,
            'Termini in comune: sorprendente, gravitazione, attraversare'
        );

        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestion->status);
    }

    public function test_scientific_concept_remains_a_strong_signal_even_with_generic_terms(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $source = $this->article($author, 'Sorgente', 'sorgente');
        $target = $this->article($author, 'Target', 'target');

        $suggestion = $this->suggestion(
            $source,
            $target,
            'Concetto scientifico riconosciuto: relatività generale; termini in comune: sorprendente, attraversare'
        );

        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestion->status);
    }

    public function test_category_bonus_does_not_rescue_a_low_information_lexical_match(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $source = $this->article($author, 'Sorgente', 'sorgente');
        $target = $this->article($author, 'Target', 'target');

        $suggestion = $this->suggestion(
            $source,
            $target,
            'Termini in comune: immaginiamo, profonde, sembrare; stessa categoria: Spazio'
        );

        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->status);
    }
}
