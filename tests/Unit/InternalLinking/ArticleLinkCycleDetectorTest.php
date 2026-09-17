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
 * ArticleLinkCycleDetector::detect() — logica pura di grafo, indipendente
 * dal motore di scoring dei suggerimenti (già coperto da
 * ArticleLinkSuggestionServiceTest) e dalla serializzazione nel pannello
 * (coperta da ArticleLinkSuggestionControllerTest).
 *
 * Codex (PR #618): il grafo è costruito dai VERI tag <a href="/articolo/...">
 * nel body corrente di ogni articolo, mai da una tabella di stato separata
 * (ArticleLinkSuggestion) che può disallinearsi dal contenuto reale — vedi
 * il commento sulla classe. I test qui riflettono questo: acceptedLink()
 * scrive un link reale nel body, non una riga 'accepted'.
 */
class ArticleLinkCycleDetectorTest extends TestCase
{
    use RefreshDatabase;

    private function detector(): ArticleLinkCycleDetector
    {
        return new ArticleLinkCycleDetector;
    }

    private function article(string $title, string $body = '<p>Corpo.</p>'): Article
    {
        $author = User::factory()->create();

        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.uniqid(),
            'excerpt' => 'Estratto.',
            'body' => $body,
            'category' => 'scienza',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 2,
            'published_at' => now()->subDay(),
        ]);
    }

    /**
     * Scrive un vero link <a href="/articolo/{slug}"> nel body di $source
     * verso $target — questo, e SOLO questo, è ciò che
     * ArticleLinkCycleDetector riconosce come arco reale.
     */
    private function realLink(Article $source, Article $target): void
    {
        $source->update([
            'body' => $source->body.'<p>Vedi anche <a href="/articolo/'.$target->slug.'">'.$target->title.'</a>.</p>',
        ]);
    }

    public function test_no_cycle_when_there_are_no_real_links_at_all(): void
    {
        $a = $this->article('A');
        $b = $this->article('B');

        $result = $this->detector()->detect($a->id, $b->id);

        $this->assertFalse($result['creates_cycle']);
        $this->assertSame([], $result['path']);
    }

    public function test_detects_a_direct_reciprocal_two_cycle(): void
    {
        // B già linka realmente ad A. Se A proponesse di linkare a B, si
        // chiuderebbe un ciclo diretto A->B->A.
        $a = $this->article('A');
        $b = $this->article('B');
        $this->realLink($b, $a);

        $result = $this->detector()->detect($a->id, $b->id);

        $this->assertTrue($result['creates_cycle']);
        $this->assertSame([$a->id, $b->id, $a->id], $result['path']);
    }

    public function test_detects_a_longer_three_node_cycle(): void
    {
        // B->C e C->A sono link reali già presenti. Se A proponesse di
        // linkare a B, si chiuderebbe il ciclo A->B->C->A.
        $a = $this->article('A');
        $b = $this->article('B');
        $c = $this->article('C');
        $this->realLink($b, $c);
        $this->realLink($c, $a);

        $result = $this->detector()->detect($a->id, $b->id);

        $this->assertTrue($result['creates_cycle']);
        $this->assertSame([$a->id, $b->id, $c->id, $a->id], $result['path']);
    }

    public function test_no_cycle_when_the_path_does_not_lead_back_to_the_source(): void
    {
        // B->C è un link reale, ma nulla riporta ad A: nessun ciclo.
        $a = $this->article('A');
        $b = $this->article('B');
        $c = $this->article('C');
        $this->realLink($b, $c);

        $result = $this->detector()->detect($a->id, $b->id);

        $this->assertFalse($result['creates_cycle']);
        $this->assertSame([], $result['path']);
    }

    public function test_a_pending_suggestion_row_without_a_real_body_link_is_never_a_cycle(): void
    {
        // Una riga ArticleLinkSuggestion 'proposed' (o anche 'accepted')
        // che punta B->A esiste, ma il body di B non contiene DAVVERO
        // quel link: non deve mai contare come un ciclo reale, perché il
        // grafo è costruito dal contenuto, non dalla tabella di stato
        // (vedi nota Codex sulla classe).
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
            'status' => ArticleLinkSuggestion::STATUS_ACCEPTED,
        ]);

        $result = $this->detector()->detect($a->id, $b->id);

        $this->assertFalse($result['creates_cycle']);
    }

    public function test_a_manually_inserted_link_that_never_went_through_a_suggestion_is_still_detected(): void
    {
        // Cantiere 82, correzione Codex: un link scritto a mano in TinyMCE
        // (mai passato da "Analizza"/"Inserisci", quindi nessuna riga
        // ArticleLinkSuggestion esiste per esso) deve comunque essere
        // riconosciuto come arco reale, perché il grafo legge il body, non
        // la tabella dei suggerimenti.
        $a = $this->article('A');
        $b = $this->article('B');
        $this->realLink($b, $a); // link reale, nessuna riga di suggerimento creata

        $this->assertSame(0, ArticleLinkSuggestion::count());

        $result = $this->detector()->detect($a->id, $b->id);

        $this->assertTrue($result['creates_cycle']);
        $this->assertSame([$a->id, $b->id, $a->id], $result['path']);
    }

    public function test_a_removed_link_no_longer_counts_even_if_a_stale_accepted_row_remains(): void
    {
        // Cantiere 82, correzione Codex: la redazione aveva accettato un
        // suggerimento B->A (link davvero inserito), ma lo ha poi rimosso
        // a mano dal body senza mai più toccare il suggerimento — la riga
        // resta 'accepted' per sempre. Il grafo non deve fidarsi di quella
        // riga stantia: se il link non è più nel body, non è più un arco.
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
            'status' => ArticleLinkSuggestion::STATUS_ACCEPTED,
        ]);
        // Nota: nessun realLink() qui — il link non è (più) davvero nel body.

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
