<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use App\Services\ArticleLinkInsertionService;
use App\Services\ArticleLinkSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleLinkSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ArticleLinkSuggestionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ArticleLinkSuggestionService(new ArticleLinkInsertionService);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Un breve sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    // 1. Non propone mai se stesso come target
    public function test_it_never_proposes_the_source_article_as_its_own_target(): void
    {
        $source = $this->article([
            'title' => 'Fotovoltaico in Italia',
            'body' => '<p>Il fotovoltaico in Italia cresce ogni anno con nuovi impianti solari.</p>',
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertFalse($suggestions->contains('target_article_id', $source->id));
    }

    // 2. Non propone articoli draft o review come target
    public function test_it_does_not_propose_draft_or_review_articles_as_targets(): void
    {
        $source = $this->article([
            'title' => 'Energia eolica offshore',
            'body' => '<p>L\'energia eolica offshore rappresenta una grande opportunità per la transizione energetica del paese.</p>',
        ]);

        $draft = $this->article([
            'title' => 'Energia eolica offshore avanzata',
            'excerpt' => 'Impianti eolici offshore di nuova generazione',
            'status' => 'draft',
            'published_at' => null,
        ]);

        $review = $this->article([
            'title' => 'Energia eolica offshore in revisione',
            'excerpt' => 'Impianti eolici offshore in fase di revisione editoriale',
            'status' => 'review',
            'published_at' => null,
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertFalse($suggestions->contains('target_article_id', $draft->id));
        $this->assertFalse($suggestions->contains('target_article_id', $review->id));
    }

    // 3. Propone articoli davvero pertinenti (titolo del target presente nel testo sorgente)
    public function test_it_proposes_pertinent_articles_based_on_title_match(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli solari più efficienti sul mercato',
            'category' => 'energia',
        ]);

        $source = $this->article([
            'title' => 'Guida alla transizione energetica',
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, capaci di garantire un rendimento superiore.</p>',
            'category' => 'energia',
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $suggestion = $suggestions->firstWhere('target_article_id', $target->id);

        $this->assertNotNull($suggestion);
        $this->assertSame('pannelli solari di nuova generazione', mb_strtolower($suggestion->anchor_text));
        $this->assertGreaterThanOrEqual(30, $suggestion->confidence_score);
        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestion->status);
    }

    // 4. Ignora corrispondenze troppo generiche sotto soglia (stessa categoria + zero segnali testuali)
    public function test_it_ignores_matches_below_the_confidence_threshold(): void
    {
        $target = $this->article([
            'title' => 'Cronaca cittadina di ieri',
            'excerpt' => 'Fatti locali della settimana',
            'category' => 'energia',
        ]);

        $source = $this->article([
            'title' => 'Ricette di cucina regionale',
            'body' => '<p>Un articolo che parla esclusivamente di ricette, ingredienti e tradizioni gastronomiche locali.</p>',
            'category' => 'energia',
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertFalse($suggestions->contains('target_article_id', $target->id));
    }

    // 5. Non duplica un target già collegato manualmente nel body
    public function test_it_does_not_propose_a_target_already_linked_in_the_body(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli solari più efficienti sul mercato',
            'category' => 'energia',
        ]);

        $source = $this->article([
            'title' => 'Guida alla transizione energetica',
            'body' => '<p>Tra le soluzioni più diffuse ci sono i <a href="/articolo/'.$target->slug.'">pannelli solari di nuova generazione</a>, molto richiesti.</p>',
            'category' => 'energia',
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertFalse($suggestions->contains('target_article_id', $target->id));
    }

    // 6. Non ripropone un suggerimento già ignorato/accettato dalla redazione
    public function test_it_does_not_re_propose_a_suggestion_already_reviewed(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli solari più efficienti sul mercato',
            'category' => 'energia',
        ]);

        $source = $this->article([
            'title' => 'Guida alla transizione energetica',
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>',
            'category' => 'energia',
        ]);

        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo precedente',
            'confidence_score' => 65,
            'status' => ArticleLinkSuggestion::STATUS_IGNORED,
            'reviewed_at' => now(),
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertFalse($suggestions->contains('target_article_id', $target->id));
        $this->assertSame(1, ArticleLinkSuggestion::where('target_article_id', $target->id)->count());
        $this->assertSame(
            ArticleLinkSuggestion::STATUS_IGNORED,
            ArticleLinkSuggestion::where('target_article_id', $target->id)->first()->status
        );
    }

    // 7. Modalità retroattiva (FASE 6): un nuovo articolo pubblicato genera suggerimenti verso articoli vecchi già pubblicati
    public function test_it_proposes_retroactive_links_from_old_articles_to_a_new_target(): void
    {
        $oldArticle = $this->article([
            'title' => 'Guida alla transizione energetica',
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>',
            'category' => 'energia',
            'published_at' => now()->subDays(30),
        ]);

        $newTarget = $this->article([
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli solari più efficienti sul mercato',
            'category' => 'energia',
            'published_at' => now(),
        ]);

        $suggestions = $this->service->analyzeForNewTarget($newTarget);

        $suggestion = $suggestions->firstWhere('source_article_id', $oldArticle->id);

        $this->assertNotNull($suggestion);
        $this->assertSame($newTarget->id, $suggestion->target_article_id);
        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestion->status);
    }

    // 8. L'anchor text è sempre testo realmente presente nel corpo sorgente (mai generato)
    public function test_anchor_text_is_always_a_phrase_actually_present_in_the_source_body(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli solari più efficienti sul mercato',
            'category' => 'energia',
        ]);

        $sourceBody = 'Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.';

        $source = $this->article([
            'title' => 'Guida alla transizione energetica',
            'body' => '<p>'.$sourceBody.'</p>',
            'category' => 'energia',
        ]);

        $suggestions = $this->service->analyzeForSource($source);
        $suggestion = $suggestions->firstWhere('target_article_id', $target->id);

        $this->assertNotNull($suggestion);
        $this->assertStringContainsStringIgnoringCase($suggestion->anchor_text, $sourceBody);
    }

    // 9. Un suggerimento "proposed" non più valido (testo modificato) viene marcato superseded, non lasciato attivo
    public function test_a_stale_proposed_suggestion_is_marked_superseded_when_it_no_longer_qualifies(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli solari più efficienti sul mercato',
            'category' => 'energia',
        ]);

        $source = $this->article([
            'title' => 'Guida alla transizione energetica',
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>',
            'category' => 'energia',
        ]);

        $first = $this->service->analyzeForSource($source);
        $this->assertNotNull($first->firstWhere('target_article_id', $target->id));

        $source->update(['body' => '<p>Testo completamente riscritto, senza più alcuna corrispondenza pertinente.</p>']);

        $second = $this->service->analyzeForSource($source->fresh());

        $this->assertFalse($second->contains('target_article_id', $target->id));
        $this->assertSame(
            ArticleLinkSuggestion::STATUS_SUPERSEDED,
            ArticleLinkSuggestion::where('target_article_id', $target->id)->first()->status
        );
        $this->assertSame(1, ArticleLinkSuggestion::where('target_article_id', $target->id)->count());
    }

    // 10. Se l'anchor migliore (titolo) attraversa un tag inline e non è inseribile, si ripiega su un termine condiviso che lo è davvero
    public function test_it_falls_back_to_an_insertable_shared_term_when_the_title_anchor_is_not_insertable(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli più efficienti',
            'category' => 'energia',
        ]);

        // Il titolo del target compare per intero nel testo appiattito, ma
        // "solari" è dentro <strong>: la frase completa non vive in un solo
        // nodo di testo del DOM e quindi non è realmente inseribile.
        $source = $this->article([
            'title' => 'Guida alla transizione energetica',
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli <strong>solari</strong> di nuova generazione, molto richiesti nel settore energia.</p>',
            'category' => 'energia',
        ]);

        $suggestions = $this->service->analyzeForSource($source);
        $suggestion = $suggestions->firstWhere('target_article_id', $target->id);

        $this->assertNotNull($suggestion);
        $this->assertNotSame('pannelli solari di nuova generazione', mb_strtolower($suggestion->anchor_text));
        $this->assertTrue(
            (new ArticleLinkInsertionService)->canInsert($source->body, $suggestion->anchor_text)
        );
    }

    // 11. Se NESSUna anchor candidata è inseribile (tutto dentro un titolo), non propone affatto il collegamento
    public function test_it_proposes_nothing_when_no_candidate_anchor_is_insertable(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli più efficienti',
            'category' => 'energia',
        ]);

        // Titolo e tutti i termini condivisi compaiono SOLO dentro un h2:
        // un punteggio che passerebbe la soglia, ma nessuna anchor
        // realmente inseribile — il suggerimento non deve essere generato.
        $source = $this->article([
            'title' => 'Guida alla transizione energetica',
            'body' => '<h2>Pannelli solari di nuova generazione: uno sguardo</h2><p>Testo del paragrafo senza altre corrispondenze pertinenti qui.</p>',
            'category' => 'energia',
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertFalse($suggestions->contains('target_article_id', $target->id));
        $this->assertSame(0, ArticleLinkSuggestion::where('target_article_id', $target->id)->count());
    }
}
