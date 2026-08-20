<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleLinkSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contextual specificity — caso reale osservato in admin: per la stessa
 * anchor/concetto "intelligenza artificiale", il motore proponeva 5
 * articoli con punteggio quasi identico, basandosi soprattutto su concetto
 * scientifico riconosciuto + termini in comune + stessa categoria.
 * Condividere un concetto multi-parola molto ampio (l'intera categoria) o
 * la sola categoria non deve produrre punteggi indistinguibili da un
 * overlap lessicale contestuale realmente specifico.
 *
 * Replica il caso su 3 domini indipendenti (Intelligenza Artificiale,
 * Spazio, Energia) con lo stesso schema: un paragrafo sorgente
 * genuinamente specifico su un sotto-argomento, un target altrettanto
 * specifico che deve emergere, e diversi candidati "solo categoria +
 * concetto ampio" che devono restare indietro.
 */
class InternalLinkingContextualSpecificityTest extends TestCase
{
    use RefreshDatabase;

    private ArticleLinkSuggestionService $service;

    /** @var array<string, Article> */
    private array $corpus = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ArticleLinkSuggestionService::class);

        $author = User::factory()->create(['role' => 'editor']);

        $mk = function (string $key, string $title, string $category, string $excerpt, string $body) use ($author) {
            $this->corpus[$key] = Article::create([
                'user_id' => $author->id,
                'title' => $title,
                'slug' => Str::slug($title).'-'.uniqid('', true),
                'excerpt' => $excerpt,
                'body' => $body,
                'category' => $category,
                'status' => 'published',
                'published_at' => now()->subDays(random_int(1, 60)),
                'read_minutes' => 4,
                'verification_status' => 'unverified',
            ]);
        };

        // ══════════════════════════════════════════════════════════════
        // DOMINIO A — Intelligenza Artificiale (il caso osservato)
        // ══════════════════════════════════════════════════════════════

        $mk('ia_source', 'Guida pratica ai modelli linguistici moderni', 'intelligenza-artificiale',
            'Come funzionano token, Transformer e generazione del testo nei modelli linguistici.',
            '<p>Un modello linguistico di grandi dimensioni scompone il testo in token e li elabora '.
            'con un\'architettura Transformer basata su meccanismi di attenzione. Durante il '.
            'pretraining, i parametri del modello vengono aggiustati su enormi quantità di testo, '.
            'per poi generare nuovo testo in modo autoregressivo, un token alla volta. Questa è una '.
            'delle applicazioni più discusse di intelligenza artificiale degli ultimi anni.</p>');

        $mk('ia_llm', 'Che cos\'è un LLM e come funziona davvero?', 'intelligenza-artificiale',
            'Token, Transformer, pretraining: la guida completa ai large language model.',
            '<p>Un LLM è un modello linguistico basato sull\'architettura Transformer, addestrato '.
            'durante il pretraining su miliardi di token. I parametri appresi permettono una '.
            'generazione del testo autoregressiva, parola dopo parola, ed è oggi tra le forme più '.
            'note di intelligenza artificiale.</p>');

        $mk('ia_turing', 'Il Test di Turing spiegato davvero: può una macchina pensare?', 'intelligenza-artificiale',
            'Alan Turing e il celebre "gioco dell\'imitazione": può una macchina pensare?',
            '<p>Il test di Turing propone un gioco dell\'imitazione: un giudice umano conversa alla '.
            'cieca con una macchina e con una persona, e deve capire chi sia chi. Se non riesce a '.
            'distinguerli, la macchina supera il test. È uno dei fondamenti filosofici della '.
            'intelligenza artificiale come disciplina.</p>');

        $mk('ia_fisica', 'L\'intelligenza artificiale fisica: quando l\'AI smette di parlare e inizia ad agire', 'intelligenza-artificiale',
            'Robot, sensori e attuatori: quando l\'intelligenza artificiale lascia lo schermo.',
            '<p>I robot moderni combinano sensori, attuatori e algoritmi di controllo per muoversi '.
            'nel mondo fisico. Questa forma incarnata di intelligenza artificiale deve gestire '.
            'equilibrio, presa degli oggetti e navigazione in ambienti reali e imprevedibili.</p>');

        $mk('ia_medica', 'Come l\'intelligenza artificiale sta trasformando la diagnosi medica in Italia', 'intelligenza-artificiale',
            'Radiologia e diagnosi assistita: l\'intelligenza artificiale negli ospedali italiani.',
            '<p>Negli ospedali italiani, sistemi di intelligenza artificiale assistono i radiologi '.
            'nell\'analisi di TAC e risonanze, segnalando aree sospette per la diagnosi precoce di '.
            'tumori e altre patologie.</p>');

        $mk('ia_allucina', 'Perché l\'intelligenza artificiale "allucina"?', 'intelligenza-artificiale',
            'Il fenomeno delle allucinazioni nei sistemi di intelligenza artificiale, spiegato semplice.',
            '<p>Quando un sistema di intelligenza artificiale produce affermazioni sicure ma false, '.
            'si parla di allucinazione. Il fenomeno nasce dal modo in cui questi sistemi imparano a '.
            'produrre risposte plausibili, non necessariamente vere.</p>');

        // Filler AI: stessa categoria, stesso concetto ampio "intelligenza
        // artificiale", nessun vocabolario specifico condiviso col
        // paragrafo sorgente — dà al segnale di document frequency una
        // base di corpus realistica (non un solo confronto isolato).
        foreach (range(1, 3) as $i) {
            $mk('ia_filler_'.$i, 'Intelligenza artificiale: notizie della settimana '.$i, 'intelligenza-artificiale',
                'Una rassegna di intelligenza artificiale applicata a settori diversi.',
                '<p>Questa settimana l\'intelligenza artificiale è stata protagonista in diversi '.
                'settori, dall\'industria ai servizi, con annunci di nuove applicazioni pratiche.</p>');
        }

        // ══════════════════════════════════════════════════════════════
        // DOMINIO B — Spazio (stesso schema, concetto "buco nero")
        // ══════════════════════════════════════════════════════════════

        $mk('spazio_source', 'Cosa succede oltre l\'orizzonte degli eventi', 'spazio',
            'Orizzonte degli eventi, radiazione di Hawking e singolarità: la fisica dei buchi neri.',
            '<p>L\'orizzonte degli eventi è il confine oltre il quale nulla, nemmeno la luce, può '.
            'sfuggire a un buco nero. Stephen Hawking dimostrò che un buco nero emette comunque una '.
            'radiazione di Hawking, dovuta a effetti quantistici vicino all\'orizzonte, che lo porta '.
            'lentamente a evaporare fino alla singolarità.</p>');

        $mk('spazio_specifico', 'La radiazione di Hawking: come evapora un buco nero', 'spazio',
            'Effetti quantistici, orizzonte degli eventi e la lenta evaporazione dei buchi neri.',
            '<p>Vicino all\'orizzonte degli eventi, effetti quantistici producono coppie di '.
            'particelle: una cade nel buco nero, l\'altra sfugge come radiazione di Hawking. Nel '.
            'tempo, questo processo porta il buco nero verso una lenta evaporazione fino alla '.
            'singolarità.</p>');

        foreach (range(1, 4) as $i) {
            $mk('spazio_filler_'.$i, 'Un nuovo buco nero osservato dagli astronomi '.$i, 'spazio',
                'Gli astronomi annunciano l\'osservazione di un nuovo buco nero.',
                '<p>Un team di astronomi ha annunciato l\'osservazione di un nuovo buco nero al '.
                'centro di una galassia lontana, grazie a un telescopio di nuova generazione.</p>');
        }

        // ══════════════════════════════════════════════════════════════
        // DOMINIO C — Energia (stesso schema, concetto "ioni di litio")
        // ══════════════════════════════════════════════════════════════

        $mk('energia_source', 'Perché le batterie si degradano nel tempo', 'energia',
            'Elettrolita, degradazione e capacità: cosa succede dentro le batterie a ioni di litio.',
            '<p>In una batteria a ioni di litio, la degradazione dell\'elettrolita e la crescita di '.
            'uno strato di interfaccia solida sugli elettrodi riducono progressivamente la capacità '.
            'utile. Il numero di cicli di carica sopportati dipende molto dalla chimica specifica '.
            'dell\'elettrolita impiegato.</p>');

        $mk('energia_specifico', 'La chimica dell\'elettrolita nelle batterie moderne', 'energia',
            'Interfaccia solida, cicli di carica e degradazione: la chimica dell\'elettrolita.',
            '<p>La formazione dello strato di interfaccia solida sull\'elettrodo dipende dalla '.
            'chimica dell\'elettrolita scelto per una batteria a ioni di litio. Un elettrolita più '.
            'stabile riduce la degradazione nel tempo e aumenta i cicli di carica sopportati.</p>');

        foreach (range(1, 4) as $i) {
            $mk('energia_filler_'.$i, 'Nuovo impianto di batterie a ioni di litio inaugurato '.$i, 'energia',
                'Un nuovo impianto per la produzione di batterie a ioni di litio.',
                '<p>È stato inaugurato un nuovo impianto per la produzione su larga scala di '.
                'batterie a ioni di litio, con l\'obiettivo di ridurre i costi di produzione.</p>');
        }
    }

    private function suggestionFor(string $sourceKey, string $targetKey): ?object
    {
        $suggestions = $this->service->analyzeForSource($this->corpus[$sourceKey]->fresh());

        return $suggestions->firstWhere('target_article_id', $this->corpus[$targetKey]->id);
    }

    /** @return array<string, int|null> target key => score (null se non suggerito) */
    private function scoresFor(string $sourceKey, array $targetKeys): array
    {
        $suggestions = $this->service->analyzeForSource($this->corpus[$sourceKey]->fresh());
        $scores = [];

        foreach ($targetKeys as $targetKey) {
            $suggestion = $suggestions->firstWhere('target_article_id', $this->corpus[$targetKey]->id);
            $scores[$targetKey] = $suggestion?->confidence_score;
        }

        return $scores;
    }

    // ── DOMINIO A: Intelligenza Artificiale (il caso osservato) ────────

    // Caso reale: prima della V2.2, gli 5 candidati qui sotto ottenevano
    // tutti un punteggio a soglia o superiore (40-75), quasi indistinguibili
    // fra loro — condividevano solo "concetto scientifico riconosciuto:
    // intelligenza artificiale" + "stessa categoria", nessuna reale
    // specificità contestuale col paragrafo sorgente (su token, Transformer,
    // pretraining, generazione del testo).
    public function test_llm_specific_article_clearly_outranks_same_concept_same_category_articles(): void
    {
        $scores = $this->scoresFor('ia_source', ['ia_llm', 'ia_turing', 'ia_fisica', 'ia_medica', 'ia_allucina', 'ia_filler_1']);

        $this->assertNotNull($scores['ia_llm'], 'L\'articolo genuinamente su LLM/token/Transformer deve essere suggerito.');

        foreach (['ia_turing', 'ia_fisica', 'ia_medica', 'ia_allucina', 'ia_filler_1'] as $other) {
            $this->assertTrue(
                $scores['ia_llm'] > ($scores[$other] ?? 0),
                "ia_llm ({$scores['ia_llm']}) deve superare {$other} (".($scores[$other] ?? 'non suggerito').
                ') — condividere solo categoria e il concetto ampio "intelligenza artificiale" non basta a eguagliare una reale specificità contestuale.'
            );
        }

        // Non un margine qualsiasi: la differenza deve essere quella di un
        // intero segnale (almeno un TERM_MATCH_SCORE pieno di scarto), non
        // un pareggio quasi impercettibile come nel caso osservato.
        $this->assertGreaterThanOrEqual(15, $scores['ia_llm'] - $scores['ia_allucina']);
    }

    // Il Test di Turing, l'IA fisica/robotica e la diagnosi medica non
    // condividono ALCUN vocabolario realmente specifico col paragrafo
    // sorgente (su LLM/token/Transformer) — solo lo stesso concetto ampio e
    // la stessa categoria. Dopo V2.2, questo non è più sufficiente a
    // superare la soglia minima: onestamente, non dovrebbero essere
    // suggeriti affatto per QUESTO paragrafo specifico.
    public function test_articles_sharing_only_the_broad_concept_and_category_are_not_suggested_for_this_specific_paragraph(): void
    {
        foreach (['ia_turing', 'ia_fisica', 'ia_medica'] as $targetKey) {
            $this->assertNull(
                $this->suggestionFor('ia_source', $targetKey),
                "{$targetKey} non dovrebbe essere suggerito: condivide solo la categoria e il concetto ampio \"intelligenza artificiale\", nessun vocabolario specifico del paragrafo (token, Transformer, pretraining)."
            );
        }
    }

    // ── DOMINIO B: Spazio (buco nero) ─────────────────────────────────

    public function test_hawking_radiation_specific_article_is_suggested_generic_black_hole_mentions_are_not(): void
    {
        $suggestion = $this->suggestionFor('spazio_source', 'spazio_specifico');
        $this->assertNotNull($suggestion, 'L\'articolo genuinamente sulla radiazione di Hawking/orizzonte degli eventi deve essere suggerito.');

        foreach (['spazio_filler_1', 'spazio_filler_2', 'spazio_filler_3', 'spazio_filler_4'] as $filler) {
            $this->assertNull(
                $this->suggestionFor('spazio_source', $filler),
                "{$filler} non dovrebbe essere suggerito: condivide solo categoria e il concetto \"buco nero\", nessun vocabolario specifico (orizzonte degli eventi, radiazione di Hawking, singolarità)."
            );
        }
    }

    // ── DOMINIO C: Energia (ioni di litio) ─────────────────────────────

    public function test_electrolyte_chemistry_specific_article_is_suggested_generic_lithium_ion_mentions_are_not(): void
    {
        $suggestion = $this->suggestionFor('energia_source', 'energia_specifico');
        $this->assertNotNull($suggestion, 'L\'articolo genuinamente sulla chimica dell\'elettrolita deve essere suggerito.');

        foreach (['energia_filler_1', 'energia_filler_2', 'energia_filler_3', 'energia_filler_4'] as $filler) {
            $this->assertNull(
                $this->suggestionFor('energia_source', $filler),
                "{$filler} non dovrebbe essere suggerito: condivide solo categoria e il concetto \"ioni di litio\", nessun vocabolario specifico (elettrolita, degradazione, interfaccia solida)."
            );
        }
    }

    // ── Esplicabilità (FASE 5) ──────────────────────────────────────────

    // Il nuovo segnale non deve introdurre debug/formule nella spiegazione
    // mostrata in redazione: la frase resta quella già esistente ("concetto
    // scientifico riconosciuto: X"), identica sia che il concetto abbia
    // contribuito a punteggio pieno sia ridotto — la classificazione
    // interna (document frequency, soglie) non trapela mai nel testo.
    public function test_the_explanation_never_leaks_internal_scoring_details(): void
    {
        $suggestion = $this->suggestionFor('ia_source', 'ia_llm');

        $this->assertNotNull($suggestion);
        $this->assertStringContainsString('Concetto scientifico riconosciuto: intelligenza artificiale', $suggestion->reason);
        $this->assertStringNotContainsString('generic', $suggestion->reason);
        $this->assertStringNotContainsString('score', $suggestion->reason);
        $this->assertStringNotContainsString('frequenc', $suggestion->reason);
        $this->assertMatchesRegularExpression('/^[^0-9]*$/', $suggestion->reason, 'La spiegazione non deve contenere numeri/punteggi grezzi.');
    }

    // ── Duplicazione/diversità (FASE 4) ─────────────────────────────────

    // Il caso osservato in admin: il pannello mostrava 5 suggerimenti sulla
    // stessa anchor "intelligenza artificiale" con punteggi quasi identici
    // (~75/100). Qui si verifica il percorso REALE che l'editor vede
    // (Article::proposedLinkSuggestions(), lo stesso metodo usato dal form
    // di modifica articolo) — non solo lo scoring isolato: nessuna regola
    // di deduplicazione nuova è stata aggiunta (vietata dalla missione),
    // la lista è più utile perché lo scoring stesso ora discrimina.
    public function test_the_editor_facing_panel_has_a_clear_top_choice_instead_of_a_near_tie(): void
    {
        $this->service->analyzeForSource($this->corpus['ia_source']->fresh());

        $panel = $this->corpus['ia_source']->fresh()->proposedLinkSuggestions()->sortByDesc('confidence_score')->values();

        $this->assertGreaterThan(0, $panel->count());

        // Il candidato genuinamente specifico (LLM) resta il primo per
        // punteggio, non uno qualsiasi tra pari merito.
        $this->assertSame($this->corpus['ia_llm']->id, $panel->first()->target_article_id);

        // Il caso osservato: primo e secondo classificato praticamente
        // indistinguibili (~75 punti entrambi). Ora il primo deve avere un
        // margine reale sul resto del pannello, non un pareggio nascosto.
        foreach ($panel->slice(1) as $rest) {
            $this->assertGreaterThan(
                $rest->confidence_score,
                $panel->first()->confidence_score,
                'Il primo classificato deve avere un margine reale sul resto del pannello, non un pareggio quasi impercettibile come nel caso osservato in admin.'
            );
        }
    }
}
