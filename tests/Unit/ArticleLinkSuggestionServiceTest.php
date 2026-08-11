<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use App\Services\ArticleLinkInsertionService;
use App\Services\ArticleLinkSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /**
     * 9. Self-link escluso a monte per identità (id/slug), non da una
     * regola temporale — resta escluso anche per una source 'scheduled',
     * il caso introdotto da questa missione (V2.1).
     */
    public function test_it_never_proposes_the_source_article_as_its_own_target_even_when_scheduled(): void
    {
        $source = $this->article([
            'title' => 'Fotovoltaico in Italia',
            'body' => '<p>Il fotovoltaico in Italia cresce ogni anno con nuovi impianti solari.</p>',
            'status' => 'scheduled',
            'published_at' => now()->addWeek(),
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

    // ── Internal Linking V2.1: eleggibilità temporale scheduled→scheduled ──

    // 1. source scheduled 19/08, target scheduled 12/08 => eleggibile
    public function test_a_scheduled_source_can_propose_an_earlier_scheduled_article(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari galleggianti sui bacini idroelettrici',
            'excerpt' => 'Una tecnologia emergente per il fotovoltaico',
            'status' => 'scheduled',
            'published_at' => '2026-08-12 15:30:00',
        ]);

        $source = $this->article([
            'title' => 'Il futuro del fotovoltaico in Italia',
            'body' => '<p>Tra le novità più interessanti ci sono i pannelli solari galleggianti sui bacini idroelettrici, una soluzione promettente.</p>',
            'status' => 'scheduled',
            'published_at' => '2026-08-19 15:30:00',
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertTrue($suggestions->contains('target_article_id', $target->id));
    }

    // 2. source scheduled 12/08, target scheduled 19/08 => escluso
    public function test_a_scheduled_source_never_proposes_a_later_scheduled_article(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari galleggianti sui bacini idroelettrici',
            'excerpt' => 'Una tecnologia emergente per il fotovoltaico',
            'status' => 'scheduled',
            'published_at' => '2026-08-19 15:30:00',
        ]);

        $source = $this->article([
            'title' => 'Il futuro del fotovoltaico in Italia',
            'body' => '<p>Tra le novità più interessanti ci sono i pannelli solari galleggianti sui bacini idroelettrici, una soluzione promettente.</p>',
            'status' => 'scheduled',
            'published_at' => '2026-08-12 15:30:00',
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertFalse($suggestions->contains('target_article_id', $target->id));
    }

    // 3. stesso istante esatto => escluso
    public function test_a_scheduled_source_never_proposes_a_target_scheduled_for_the_exact_same_instant(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari galleggianti sui bacini idroelettrici',
            'excerpt' => 'Una tecnologia emergente per il fotovoltaico',
            'status' => 'scheduled',
            'published_at' => '2026-08-19 15:30:00',
        ]);

        $source = $this->article([
            'title' => 'Il futuro del fotovoltaico in Italia',
            'body' => '<p>Tra le novità più interessanti ci sono i pannelli solari galleggianti sui bacini idroelettrici, una soluzione promettente.</p>',
            'status' => 'scheduled',
            'published_at' => '2026-08-19 15:30:00',
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertFalse($suggestions->contains('target_article_id', $target->id));
    }

    // 4. stesso giorno, orario realmente precedente => eleggibile
    public function test_a_scheduled_source_can_propose_a_target_scheduled_earlier_the_same_day(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari galleggianti sui bacini idroelettrici',
            'excerpt' => 'Una tecnologia emergente per il fotovoltaico',
            'status' => 'scheduled',
            'published_at' => '2026-08-19 14:00:00',
        ]);

        $source = $this->article([
            'title' => 'Il futuro del fotovoltaico in Italia',
            'body' => '<p>Tra le novità più interessanti ci sono i pannelli solari galleggianti sui bacini idroelettrici, una soluzione promettente.</p>',
            'status' => 'scheduled',
            'published_at' => '2026-08-19 15:30:00',
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertTrue($suggestions->contains('target_article_id', $target->id));
    }

    // 5. source published, target scheduled => escluso
    public function test_a_published_source_never_proposes_a_scheduled_article(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari galleggianti sui bacini idroelettrici',
            'excerpt' => 'Una tecnologia emergente per il fotovoltaico',
            'status' => 'scheduled',
            'published_at' => now()->addWeek(),
        ]);

        $source = $this->article([
            'title' => 'Il futuro del fotovoltaico in Italia',
            'body' => '<p>Tra le novità più interessanti ci sono i pannelli solari galleggianti sui bacini idroelettrici, una soluzione promettente.</p>',
            'status' => 'published',
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertFalse($suggestions->contains('target_article_id', $target->id));
    }

    // 6. source draft, target scheduled precedente => escluso
    public function test_a_draft_source_never_proposes_a_scheduled_article(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari galleggianti sui bacini idroelettrici',
            'excerpt' => 'Una tecnologia emergente per il fotovoltaico',
            'status' => 'scheduled',
            'published_at' => now()->addDay(),
        ]);

        $source = $this->article([
            'title' => 'Il futuro del fotovoltaico in Italia',
            'body' => '<p>Tra le novità più interessanti ci sono i pannelli solari galleggianti sui bacini idroelettrici, una soluzione promettente.</p>',
            'status' => 'draft',
            'published_at' => null,
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertFalse($suggestions->contains('target_article_id', $target->id));
    }

    // 7. target published => comportamento V2 invariato anche da una source scheduled
    public function test_a_scheduled_source_still_proposes_an_already_published_article(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari galleggianti sui bacini idroelettrici',
            'excerpt' => 'Una tecnologia emergente per il fotovoltaico',
            'status' => 'published',
        ]);

        $source = $this->article([
            'title' => 'Il futuro del fotovoltaico in Italia',
            'body' => '<p>Tra le novità più interessanti ci sono i pannelli solari galleggianti sui bacini idroelettrici, una soluzione promettente.</p>',
            'status' => 'scheduled',
            'published_at' => now()->addWeek(),
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertTrue($suggestions->contains('target_article_id', $target->id));
    }

    /**
     * Caso reale della missione (#13/#14 "Test di Turing"), a livello di
     * motore dei suggerimenti: #14 (fonte, 19/08) deve poter proporre #13
     * (target, 12/08) come collegamento — usando factory/testo generico,
     * non ID hardcoded.
     */
    public function test_the_real_world_turing_articles_scenario_is_proposed_as_a_suggestion(): void
    {
        $earlierArticle = $this->article([
            'title' => 'Il Test di Turing spiegato davvero: può una macchina pensare?',
            'excerpt' => 'Cos\'è il test di Turing e come funziona davvero',
            'category' => 'intelligenza-artificiale',
            'status' => 'scheduled',
            'published_at' => '2026-08-12 15:30:00',
        ]);

        $laterArticle = $this->article([
            'title' => 'Il Test di Turing ha ancora senso nel 2026? Limiti, critiche e nuove prospettive.',
            'body' => '<p>Abbiamo già raccontato il Test di Turing spiegato davvero: può una macchina pensare?, ma oggi vale la pena chiedersi se abbia ancora senso.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'scheduled',
            'published_at' => '2026-08-19 15:30:00',
        ]);

        $suggestions = $this->service->analyzeForSource($laterArticle);

        $this->assertTrue($suggestions->contains('target_article_id', $earlierArticle->id));
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
            'body' => '<p>Resoconto degli eventi principali avvenuti in città.</p>',
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

    /**
     * Codex (PR #165, P1): un target temporalmente sicuro al momento della
     * prima "Analizza" ma poi riprogrammato DOPO la source esce dal pool
     * di Article::eligibleAsLinkTargetFor() — quindi il loop principale
     * non lo incontra più affatto, a differenza del caso "punteggio non
     * più sufficiente" sopra. Deve comunque essere marcato superato dalla
     * ri-analisi, non lasciato "proposed" indefinitamente.
     */
    public function test_a_proposed_suggestion_is_superseded_once_its_scheduled_target_is_rescheduled_after_the_source(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli solari più efficienti sul mercato',
            'category' => 'energia',
            'status' => 'scheduled',
            'published_at' => '2026-08-12 15:30:00',
        ]);

        $source = $this->article([
            'title' => 'Guida alla transizione energetica',
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>',
            'category' => 'energia',
            'status' => 'scheduled',
            'published_at' => '2026-08-19 15:30:00',
        ]);

        $first = $this->service->analyzeForSource($source);
        $this->assertNotNull($first->firstWhere('target_article_id', $target->id));

        // Il target viene riprogrammato DOPO la source: non più temporalmente sicuro.
        $target->update(['published_at' => '2026-08-25 15:30:00']);

        $second = $this->service->analyzeForSource($source->fresh());

        $this->assertFalse($second->contains('target_article_id', $target->id));
        $this->assertSame(
            ArticleLinkSuggestion::STATUS_SUPERSEDED,
            ArticleLinkSuggestion::where('target_article_id', $target->id)->first()->status
        );
    }

    public function test_a_proposed_suggestion_is_superseded_once_its_scheduled_target_is_demoted_to_draft(): void
    {
        $target = $this->article([
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli solari più efficienti sul mercato',
            'category' => 'energia',
            'status' => 'scheduled',
            'published_at' => '2026-08-12 15:30:00',
        ]);

        $source = $this->article([
            'title' => 'Guida alla transizione energetica',
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>',
            'category' => 'energia',
            'status' => 'scheduled',
            'published_at' => '2026-08-19 15:30:00',
        ]);

        $first = $this->service->analyzeForSource($source);
        $this->assertNotNull($first->firstWhere('target_article_id', $target->id));

        $target->update(['status' => 'draft', 'published_at' => null]);

        $second = $this->service->analyzeForSource($source->fresh());

        $this->assertFalse($second->contains('target_article_id', $target->id));
        $this->assertSame(
            ArticleLinkSuggestion::STATUS_SUPERSEDED,
            ArticleLinkSuggestion::where('target_article_id', $target->id)->first()->status
        );
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

    // ══════════════════════════════════════════════════════════════════
    // Audit qualità suggerimenti (Ago 2026): anchor grammaticalmente
    // deboli e soglia troppo permissiva a match generici — vedi report.
    // ══════════════════════════════════════════════════════════════════

    // 12. Una parola generica condivisa ("potrebbe") non diventa mai
    // l'anchor, anche se letteralmente presente in entrambi i testi:
    // resta disponibile un termine realmente distintivo condiviso.
    public function test_a_generic_shared_word_like_potrebbe_is_never_chosen_as_anchor(): void
    {
        // "potrebbe" è il termine condiviso più lungo (8 caratteri) e
        // comparirebbe per primo tra i candidati anchor ordinati per
        // lunghezza decrescente — esattamente il caso che innescava il bug.
        $target = $this->article([
            'title' => 'Il litio non basta più: cosa cambia',
            'excerpt' => 'Il prezzo del litio potrebbe salire ancora nei prossimi mesi.',
            'category' => 'energia',
        ]);

        $source = $this->article([
            'title' => 'Materiali critici per le batterie del futuro',
            'body' => '<p>Gli analisti dicono che il prezzo potrebbe salire: il litio resta comunque centrale per le batterie di nuova generazione.</p>',
            'category' => 'energia',
        ]);

        $suggestions = $this->service->analyzeForSource($source);
        $suggestion = $suggestions->firstWhere('target_article_id', $target->id);

        $this->assertNotNull($suggestion);
        $this->assertNotSame('potrebbe', mb_strtolower($suggestion->anchor_text));
    }

    // 13. Un avverbio di modo in "-mente" ("profondamente") non diventa
    // mai l'anchor, anche se condiviso letteralmente tra i due testi.
    public function test_a_generic_mente_adverb_like_profondamente_is_never_chosen_as_anchor(): void
    {
        $target = $this->article([
            'title' => 'Chirurgia robotica: il futuro degli interventi',
            'excerpt' => 'La chirurgia robotica cambia profondamente la sala operatoria.',
            'category' => 'salute',
        ]);

        $source = $this->article([
            'title' => 'Innovazione in ospedale',
            'body' => '<p>La tecnologia sta cambiando profondamente il modo in cui operiamo: sempre più spesso si parla di chirurgia robotica negli ospedali italiani.</p>',
            'category' => 'salute',
        ]);

        $suggestions = $this->service->analyzeForSource($source);
        $suggestion = $suggestions->firstWhere('target_article_id', $target->id);

        $this->assertNotNull($suggestion);
        $this->assertNotSame('profondamente', mb_strtolower($suggestion->anchor_text));
    }

    // 14. Una forma verbale generica condivisa ("significa") non diventa
    // mai l'anchor.
    public function test_a_generic_verb_form_like_significa_is_never_chosen_as_anchor(): void
    {
        // "significa" (9 caratteri) è il termine condiviso più lungo:
        // stesso meccanismo del test precedente, verbo diverso.
        $target = $this->article([
            'title' => 'Il bonus ristrutturazioni cambia ancora',
            'excerpt' => 'Una modifica sull\'importo dello sconto che significa meno vantaggi per i proprietari.',
            'category' => 'economia',
        ]);

        $source = $this->article([
            'title' => 'Novità fiscali per la casa',
            'body' => '<p>Il governo conferma che il nuovo importo dello sconto sul bonus significa un taglio per molte famiglie proprietarie di casa.</p>',
            'category' => 'economia',
        ]);

        $suggestions = $this->service->analyzeForSource($source);
        $suggestion = $suggestions->firstWhere('target_article_id', $target->id);

        $this->assertNotNull($suggestion);
        $this->assertNotSame('significa', mb_strtolower($suggestion->anchor_text));
    }

    // 15. Un match basato solo su due parole generiche condivise (nessun
    // titolo, nessuna categoria in comune) non supera più la soglia.
    public function test_a_match_based_only_on_generic_shared_words_does_not_pass_the_threshold(): void
    {
        $target = $this->article([
            'title' => 'Il sistema idrico di Milano',
            'excerpt' => 'Un progetto per il livello delle falde acquifere.',
            'category' => 'ambiente',
        ]);

        $source = $this->article([
            'title' => 'Nuove tecnologie per le auto elettriche',
            'body' => '<p>Il sistema di ricarica raggiunge un livello di efficienza mai visto prima nel settore automotive.</p>',
            'category' => 'energia',
        ]);

        $suggestions = $this->service->analyzeForSource($source);

        $this->assertFalse($suggestions->contains('target_article_id', $target->id));
    }

    // 16. Stesso tema, stessa categoria e termini specifici insieme
    // producono un punteggio più alto di un semplice match testuale.
    public function test_shared_topic_category_and_specific_terms_together_increase_the_score(): void
    {
        $target = $this->article([
            'title' => 'Idrogeno verde: la scommessa industriale italiana',
            'excerpt' => 'Elettrolizzatori e stoccaggio per la filiera dell\'idrogeno verde.',
            'category' => 'energia',
        ]);

        $weakSource = $this->article([
            'title' => 'Rassegna settimanale energia',
            'body' => '<p>Tra gli argomenti della settimana, un cenno rapido su elettrolizzatori e stoccaggio.</p>',
            'category' => 'energia',
        ]);

        $strongSource = $this->article([
            'title' => 'Decarbonizzazione dell\'industria pesante',
            'body' => '<p>La filiera dell\'idrogeno verde, con i suoi elettrolizzatori e lo stoccaggio dedicato, è centrale per la decarbonizzazione.</p>',
            'category' => 'energia',
        ]);

        $weakSuggestions = $this->service->analyzeForSource($weakSource);
        $strongSuggestions = $this->service->analyzeForSource($strongSource);

        $weak = $weakSuggestions->firstWhere('target_article_id', $target->id);
        $strong = $strongSuggestions->firstWhere('target_article_id', $target->id);

        $this->assertNotNull($weak);
        $this->assertNotNull($strong);
        $this->assertGreaterThan($weak->confidence_score, $strong->confidence_score);
    }

    // 6. Un candidato semanticamente debole (zero segnali testuali) non
    // viene mai proposto solo perché condivide la categoria.
    public function test_a_weak_candidate_is_never_proposed_on_category_match_alone(): void
    {
        $target = $this->article([
            'title' => 'Cronaca cittadina di ieri',
            'excerpt' => 'Fatti locali della settimana.',
            'body' => '<p>Resoconto degli eventi principali avvenuti in città.</p>',
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

    // 19. Limite noto e documentato: il suggeritore è puramente lessicale
    // (nessun NLP/embedding semantico). Se il testo sorgente non usa
    // ALCUNA parola in comune con titolo/sommario del target — anche
    // quando la connessione concettuale è reale (relatività generale e
    // correzione dell'orologio dei satelliti GPS) — nessun suggerimento
    // può nascere: non esiste alcuna frase nel testo su cui costruire
    // un'anchor "sempre presente nel testo sorgente" (invariante di
    // design). Colmare questo gap richiederebbe similarità semantica
    // (embedding) o una knowledge base di concetti collegati: fuori
    // perimetro per questa PR (nessuna nuova infrastruttura/costo
    // runtime, nessuna dipendenza esterna).
    public function test_documented_limitation_no_suggestion_across_purely_conceptual_links_without_shared_vocabulary(): void
    {
        $gps = $this->article([
            'title' => 'Come funziona il posizionamento satellitare',
            'excerpt' => 'La rete di satelliti che permette di calcolare la propria posizione sulla Terra.',
            'category' => 'scienza',
        ]);

        $relativity = $this->article([
            'title' => 'La curvatura dello spaziotempo spiegata semplice',
            'body' => '<p>Einstein descrisse la gravità non come una forza ma come la geometria dello spaziotempo, curvato dalla presenza di massa ed energia.</p>',
            'category' => 'scienza',
        ]);

        $suggestions = $this->service->analyzeForSource($relativity);

        $this->assertFalse($suggestions->contains('target_article_id', $gps->id));
    }

    // 19b. Controprova: quando la redazione scrive esplicitamente il
    // collegamento nel testo (anche solo nominando il concetto target),
    // il suggerimento nasce correttamente — il limite sopra riguarda solo
    // le connessioni MAI messe per iscritto nel corpo dell'articolo.
    public function test_the_same_conceptual_link_is_found_once_the_source_text_actually_names_it(): void
    {
        $gps = $this->article([
            'title' => 'Come funziona il posizionamento satellitare GPS',
            'excerpt' => 'La rete di satelliti che permette di calcolare la propria posizione sulla Terra.',
            'category' => 'scienza',
        ]);

        $relativity = $this->article([
            'title' => 'La curvatura dello spaziotempo spiegata semplice',
            'body' => '<p>Einstein descrisse la gravità come geometria dello spaziotempo. Un caso pratico: senza le correzioni relativistiche, il posizionamento satellitare GPS accumulerebbe chilometri di errore al giorno.</p>',
            'category' => 'scienza',
        ]);

        $suggestions = $this->service->analyzeForSource($relativity);
        $suggestion = $suggestions->firstWhere('target_article_id', $gps->id);

        $this->assertNotNull($suggestion);
    }

    // ── Concetti scientifici multi-parola (Internal Linking V2) ──

    // 20. Un concetto multi-parola noto (config/scientific_concepts.php),
    // citato per intero in entrambi i testi, contribuisce al punteggio e
    // viene preferito come anchor (più specifico di una singola parola).
    public function test_a_shared_scientific_concept_contributes_to_the_score_and_is_used_as_anchor(): void
    {
        $target = $this->article([
            'title' => 'Cosa sono i buchi neri supermassicci',
            'excerpt' => 'Un viaggio nei buchi neri al centro delle galassie.',
            'category' => 'spazio',
        ]);

        $source = $this->article([
            'title' => 'Osservazioni astronomiche recenti',
            'body' => '<p>Gli astronomi hanno individuato un nuovo buco nero al centro di una galassia lontana, con proprietà sorprendenti.</p>',
            'category' => 'spazio',
        ]);

        $suggestions = $this->service->analyzeForSource($source);
        $suggestion = $suggestions->firstWhere('target_article_id', $target->id);

        $this->assertNotNull($suggestion);
        $this->assertSame('buco nero', $suggestion->anchor_text);
        $this->assertStringContainsString('Concetto scientifico riconosciuto: buco nero', $suggestion->reason);
    }

    // 21. Il concetto multi-parola resta un segnale ADDITIVO: due parole
    // generiche comuni che compaiono per puro caso in entrambi i testi
    // (senza formare nessuna frase concettuale nota) non attivano il
    // bonus — verificato indirettamente, il caso 6 (categoria da sola,
    // nessun altro segnale) resta non suggerito anche dopo l'aggiunta del
    // livello concettuale.
    public function test_the_concept_bonus_never_creates_a_suggestion_when_no_known_concept_is_shared(): void
    {
        $target = $this->article([
            'title' => 'Cronaca cittadina di ieri',
            'excerpt' => 'Fatti locali della settimana.',
            'body' => '<p>Resoconto degli eventi principali avvenuti in città.</p>',
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

    /**
     * Internal Linking V2.1 — FASE 7: allargare la candidate selection agli
     * scheduled temporalmente sicuri non deve introdurre una query per
     * candidato (N+1) né far crescere il costo con la dimensione del
     * corpus: resta comunque una singola query (Article::
     * eligibleAsLinkTargetFor(), pre-filtro SQL) più le query fisse già
     * esistenti (suggerimenti già presenti per la source), indipendente dal
     * numero di articoli pubblicati/scheduled nel pool.
     */
    public function test_analyzing_a_scheduled_source_does_not_grow_query_count_with_corpus_size(): void
    {
        $author = User::factory()->create(['role' => 'editor']);

        $seed = function (int $publishedCount, int $scheduledCount) use ($author): Article {
            for ($i = 0; $i < $publishedCount; $i++) {
                Article::create([
                    'user_id' => $author->id,
                    'title' => 'Articolo pubblicato numero '.$i.'-'.uniqid('', true),
                    'slug' => 'perf-pub-'.$i.'-'.uniqid('', true),
                    'excerpt' => 'Sommario di prova.',
                    'body' => '<p>'.str_repeat('Testo scientifico generico. ', 20).'</p>',
                    'category' => 'energia',
                    'status' => 'published',
                    'published_at' => now()->subDays($publishedCount - $i),
                ]);
            }

            for ($i = 0; $i < $scheduledCount; $i++) {
                Article::create([
                    'user_id' => $author->id,
                    'title' => 'Articolo programmato numero '.$i.'-'.uniqid('', true),
                    'slug' => 'perf-sched-'.$i.'-'.uniqid('', true),
                    'excerpt' => 'Sommario di prova.',
                    'body' => '<p>'.str_repeat('Testo scientifico generico. ', 20).'</p>',
                    'category' => 'energia',
                    'status' => 'scheduled',
                    'published_at' => now()->addDays($i + 1),
                ]);
            }

            return Article::create([
                'user_id' => $author->id,
                'title' => 'Sorgente programmata',
                'slug' => 'perf-source-'.uniqid('', true),
                'excerpt' => 'Sommario di prova.',
                'body' => '<p>'.str_repeat('Testo scientifico generico. ', 20).'</p>',
                'category' => 'energia',
                'status' => 'scheduled',
                'published_at' => now()->addDays($publishedCount + $scheduledCount + 30),
            ]);
        };

        $smallSource = $seed(15, 5);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service->analyzeForSource($smallSource->fresh());
        $countSmall = count(DB::getQueryLog());
        DB::disableQueryLog();

        $largeSource = $seed(60, 20);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service->analyzeForSource($largeSource->fresh());
        $countLarge = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $countSmall,
            $countLarge,
            "Con un corpus piccolo: {$countSmall} query. Con uno grande: {$countLarge} query. Devono coincidere — nessuna query per candidato."
        );
    }

    /**
     * Codex (PR #165, P2 round 4): un candidato scheduled temporalmente
     * sicuro ha per costruzione un published_at nel FUTURO, sempre maggiore
     * di quello di un pubblicato (sempre nel passato) — un ORDER BY
     * published_at DESC "puro" piazzerebbe quindi ogni scheduled sicuro
     * prima di OGNI pubblicato, e con MAX_CANDIDATES scheduled sicuri
     * eleggibili nessun pubblicato entrerebbe mai nella finestra del LIMIT.
     * Qui il pool contiene esattamente MAX_CANDIDATES scheduled sicuri
     * "di riempimento" (irrilevanti per lo scoring) più UN pubblicato che
     * dovrebbe essere suggerito con margine ampio (titolo per intero nel
     * body della source, +50 da solo) — deve comunque comparire.
     */
    public function test_scheduled_safe_candidates_do_not_crowd_out_an_eligible_published_target(): void
    {
        $author = User::factory()->create(['role' => 'editor']);

        $publishedTarget = Article::create([
            'user_id' => $author->id,
            'title' => 'Superconduttori ad alta temperatura',
            'slug' => 'superconduttori-alta-temperatura-'.uniqid('', true),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>I superconduttori ad alta temperatura promettono applicazioni rivoluzionarie.</p>',
            'category' => 'fisica',
            'status' => 'published',
            'published_at' => now()->subDays(5),
        ]);

        for ($i = 0; $i < 300; $i++) {
            Article::create([
                'user_id' => $author->id,
                'title' => 'Articolo programmato di riempimento '.$i.'-'.uniqid('', true),
                'slug' => 'crowd-sched-'.$i.'-'.uniqid('', true),
                'excerpt' => 'Sommario di prova.',
                'body' => '<p>'.str_repeat('Testo generico senza alcuna relazione tematica. ', 15).'</p>',
                'category' => 'energia',
                'status' => 'scheduled',
                'published_at' => now()->addDays($i + 1),
            ]);
        }

        $source = Article::create([
            'user_id' => $author->id,
            'title' => 'Materiali del futuro per l\'elettronica',
            'slug' => 'materiali-futuro-elettronica-'.uniqid('', true),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Tra i materiali più promettenti ci sono i Superconduttori ad alta temperatura, oggetto di ricerca intensa.</p>',
            'category' => 'fisica',
            'status' => 'scheduled',
            'published_at' => now()->addDays(301),
        ]);

        $suggestions = $this->service->analyzeForSource($source->fresh());

        $this->assertTrue(
            $suggestions->contains('target_article_id', $publishedTarget->id),
            'Il target pubblicato, chiaramente pertinente, non deve essere scalzato da candidati scheduled irrilevanti che riempiono la finestra del LIMIT.'
        );
    }

    /**
     * Codex (PR #165, P2 round 5): il fix del round 4 (pubblicati sempre
     * prima nell'ordinamento) risolve il caso "troppi scheduled sicuri" ma
     * introduce il caso SIMMETRICO — un corpus maturo con >= MAX_CANDIDATES
     * pubblicati riempirebbe da solo l'intera finestra del LIMIT, e
     * analyzeForSource() non potrebbe MAI produrre un suggerimento
     * scheduled->scheduled, l'intero scopo di questa missione. Le due quote
     * (pubblicati e scheduled sicuri) sono ora riservate SEPARATAMENTE:
     * qui il pool contiene più pubblicati di quanti la sola quota pubblicati
     * ne possa contenere, più UNO scheduled sicuro chiaramente pertinente
     * che deve comunque comparire.
     */
    public function test_published_candidates_do_not_crowd_out_an_eligible_scheduled_safe_target(): void
    {
        $author = User::factory()->create(['role' => 'editor']);

        $scheduledSafeTarget = Article::create([
            'user_id' => $author->id,
            'title' => 'Superconduttori ad alta temperatura',
            'slug' => 'superconduttori-alta-temperatura-'.uniqid('', true),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>I superconduttori ad alta temperatura promettono applicazioni rivoluzionarie.</p>',
            'category' => 'fisica',
            'status' => 'scheduled',
            'published_at' => now()->addDays(5),
        ]);

        for ($i = 0; $i < 305; $i++) {
            Article::create([
                'user_id' => $author->id,
                'title' => 'Articolo pubblicato di riempimento '.$i.'-'.uniqid('', true),
                'slug' => 'crowd-pub-'.$i.'-'.uniqid('', true),
                'excerpt' => 'Sommario di prova.',
                'body' => '<p>'.str_repeat('Testo generico senza alcuna relazione tematica. ', 15).'</p>',
                'category' => 'energia',
                'status' => 'published',
                'published_at' => now()->subDays($i + 1),
            ]);
        }

        $source = Article::create([
            'user_id' => $author->id,
            'title' => 'Materiali del futuro per l\'elettronica',
            'slug' => 'materiali-futuro-elettronica-'.uniqid('', true),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Tra i materiali più promettenti ci sono i Superconduttori ad alta temperatura, oggetto di ricerca intensa.</p>',
            'category' => 'fisica',
            'status' => 'scheduled',
            'published_at' => now()->addDays(10),
        ]);

        $suggestions = $this->service->analyzeForSource($source->fresh());

        $this->assertTrue(
            $suggestions->contains('target_article_id', $scheduledSafeTarget->id),
            'Il target scheduled sicuro, chiaramente pertinente, non deve essere scalzato da un corpus maturo di pubblicati irrilevanti.'
        );
    }

    /**
     * Codex (PR #165, P2 round 6): il round 5 fissa la quota dei pubblicati
     * a MAX_CANDIDATES - MAX_SCHEDULED_SAFE_CANDIDATES SEMPRE, anche quando
     * la source non è affatto scheduled e la query degli scheduled sicuri
     * non gira nemmeno — sottraendo inutilmente capacità ai pubblicati nel
     * caso comune. Qui la source è pubblicata (non scheduled): un target
     * pubblicato chiaramente pertinente, ma più vecchio di 255 riempimenti
     * irrilevanti (posizione 256 nell'ordinamento per data), deve comunque
     * comparire — con la vecchia quota fissa (250) sarebbe stato escluso.
     */
    public function test_a_non_scheduled_source_gets_the_full_candidate_quota_for_published_targets(): void
    {
        $author = User::factory()->create(['role' => 'editor']);

        $publishedTarget = Article::create([
            'user_id' => $author->id,
            'title' => 'Superconduttori ad alta temperatura',
            'slug' => 'superconduttori-alta-temperatura-'.uniqid('', true),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>I superconduttori ad alta temperatura promettono applicazioni rivoluzionarie.</p>',
            'category' => 'fisica',
            'status' => 'published',
            'published_at' => now()->subDays(300),
        ]);

        for ($i = 0; $i < 255; $i++) {
            Article::create([
                'user_id' => $author->id,
                'title' => 'Articolo pubblicato di riempimento '.$i.'-'.uniqid('', true),
                'slug' => 'crowd-nonsched-'.$i.'-'.uniqid('', true),
                'excerpt' => 'Sommario di prova.',
                'body' => '<p>'.str_repeat('Testo generico senza alcuna relazione tematica. ', 15).'</p>',
                'category' => 'energia',
                'status' => 'published',
                'published_at' => now()->subDays($i + 1),
            ]);
        }

        $source = Article::create([
            'user_id' => $author->id,
            'title' => 'Materiali del futuro per l\'elettronica',
            'slug' => 'materiali-futuro-elettronica-'.uniqid('', true),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Tra i materiali più promettenti ci sono i Superconduttori ad alta temperatura, oggetto di ricerca intensa.</p>',
            'category' => 'fisica',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $suggestions = $this->service->analyzeForSource($source->fresh());

        $this->assertTrue(
            $suggestions->contains('target_article_id', $publishedTarget->id),
            'Una source non scheduled deve poter usare l\'intera quota MAX_CANDIDATES per i pubblicati, non una quota ridotta pensata per gli scheduled sicuri che qui non si applicano nemmeno.'
        );
    }
}
