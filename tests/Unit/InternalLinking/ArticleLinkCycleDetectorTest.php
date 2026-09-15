<?php

namespace Tests\Unit\InternalLinking;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use App\Services\InternalLinking\ArticleLinkCycleDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cantiere 82 (programma "100 cantieri Kairus"). Copertura dedicata di
 * ArticleLinkCycleDetector::detect() — logica pura di grafo su un solo
 * segnale (ArticleLinkSuggestion::STATUS_ACCEPTED), indipendente dal
 * motore di scoring dei suggerimenti (già coperto da
 * ArticleLinkSuggestionServiceTest) e dalla serializzazione nel pannello
 * (coperta da ArticleLinkSuggestionControllerTest).
 */
class ArticleLinkCycleDetectorTest extends TestCase
{
    use RefreshDatabase;

    private function detector(): ArticleLinkCycleDetector
    {
        return new ArticleLinkCycleDetector;
    }

    private function article(string $title): Article
    {
        $author = User::factory()->create();

        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.uniqid(),
            'excerpt' => 'Estratto.',
            'body' => '<p>Corpo.</p>',
            'category' => 'scienza',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 2,
            'published_at' => now()->subDay(),
        ]);
    }

    private function acceptedLink(Article $source, Article $target): void
    {
        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => $target->slug,
            'anchor_text' => 'testo',
            'context_excerpt' => 'contesto',
            'reason' => 'test',
            'confidence_score' => 50,
            'status' => ArticleLinkSuggestion::STATUS_ACCEPTED,
        ]);
    }

    public function test_no_cycle_when_there_are_no_accepted_links_at_all(): void
    {
        $a = $this->article('A');
        $b = $this->article('B');

        $result = $this->detector()->detect($a->id, $b->id);

        $this->assertFalse($result['creates_cycle']);
        $this->assertSame([], $result['path']);
    }

    public function test_detects_a_direct_reciprocal_two_cycle(): void
    {
        // B già linka ad A (accettato). Se A propone di linkare a B, si
        // chiuderebbe un ciclo diretto A->B->A.
        $a = $this->article('A');
        $b = $this->article('B');
        $this->acceptedLink($b, $a);

        $result = $this->detector()->detect($a->id, $b->id);

        $this->assertTrue($result['creates_cycle']);
        $this->assertSame([$a->id, $b->id, $a->id], $result['path']);
    }

    public function test_detects_a_longer_three_node_cycle(): void
    {
        // B->C e C->A già accettati. Se A propone di linkare a B, si
        // chiuderebbe il ciclo A->B->C->A.
        $a = $this->article('A');
        $b = $this->article('B');
        $c = $this->article('C');
        $this->acceptedLink($b, $c);
        $this->acceptedLink($c, $a);

        $result = $this->detector()->detect($a->id, $b->id);

        $this->assertTrue($result['creates_cycle']);
        $this->assertSame([$a->id, $b->id, $c->id, $a->id], $result['path']);
    }

    public function test_no_cycle_when_the_path_does_not_lead_back_to_the_source(): void
    {
        // B->C accettato, ma nulla riporta ad A: nessun ciclo.
        $a = $this->article('A');
        $b = $this->article('B');
        $c = $this->article('C');
        $this->acceptedLink($b, $c);

        $result = $this->detector()->detect($a->id, $b->id);

        $this->assertFalse($result['creates_cycle']);
        $this->assertSame([], $result['path']);
    }

    public function test_only_accepted_links_are_considered_never_merely_proposed_ones(): void
    {
        // B->A esiste ma è solo 'proposed', mai accettato: non deve
        // contare come un ciclo reale ancora.
        $a = $this->article('A');
        $b = $this->article('B');
        ArticleLinkSuggestion::create([
            'source_article_id' => $b->id,
            'target_article_id' => $a->id,
            'target_slug' => $a->slug,
            'anchor_text' => 'testo',
            'context_excerpt' => 'contesto',
            'reason' => 'test',
            'confidence_score' => 50,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
        ]);

        $result = $this->detector()->detect($a->id, $b->id);

        $this->assertFalse($result['creates_cycle']);
    }

    public function test_a_self_referencing_pair_is_never_reported_as_a_cycle(): void
    {
        $a = $this->article('A');

        $result = $this->detector()->detect($a->id, $a->id);

        $this->assertFalse($result['creates_cycle']);
        $this->assertSame([], $result['path']);
    }
}
