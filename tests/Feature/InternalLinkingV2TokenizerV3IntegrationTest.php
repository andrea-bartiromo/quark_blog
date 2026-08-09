<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleLinkSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integrazione V2 (scoring/document-frequency/anchor ranking) + Tokenizer
 * V3 (Unicode/elisioni/acronimi corti), dopo il merge di #142 e #144 sullo
 * stesso main. I due si compongono attraverso un solo punto di contatto:
 * extractTerms() (V3) è la primitiva di tokenizzazione, extractTargetTerms
 * /buildDocumentFrequency/scoreLink (V2) la consumano senza mai
 * duplicarne la logica — verificato qui end-to-end con
 * analyzeForSource(), non solo per singola funzione via reflection (già
 * coperto altrove).
 */
class InternalLinkingV2TokenizerV3IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ArticleLinkSuggestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ArticleLinkSuggestionService::class);
    }

    private function article(string $title, string $category, ?string $excerpt, string $body): Article
    {
        $author = User::firstOrCreate(
            ['email' => 'v2v3-integration@kairus.it'],
            ['name' => 'Editor Test', 'role' => 'editor', 'password' => bcrypt('password')]
        );

        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.uniqid(),
            'excerpt' => $excerpt,
            'body' => $body,
            'category' => $category,
            'status' => 'published',
            'published_at' => now()->subDays(random_int(1, 30)),
            'read_minutes' => 4,
            'verification_status' => 'unverified',
        ]);
    }

    private function suggestionFrom(Article $source, Article $target): ?object
    {
        return $this->service->analyzeForSource($source->fresh())
            ->firstWhere('target_article_id', $target->id);
    }

    /**
     * "dell'universo" (V3, elisione) produce "universo" e contribuisce
     * correttamente al matching V2 con un articolo che scrive "universo"
     * senza articolo elisio — prima del merge questo non era nemmeno
     * possibile testare, perché V3 non esisteva ancora sullo stesso main
     * di V2.
     */
    public function test_elided_noun_contributes_to_v2_matching_against_the_bare_noun(): void
    {
        $source = $this->article(
            'Guardare lontano nello spazio', 'spazio',
            'La luce impiega tempo a viaggiare.',
            '<p>Osservare dell\'universo profondo significa vedere indietro nel tempo, poiché la luce di galassie lontane ci raggiunge dopo miliardi di anni di viaggio nello spaziotempo.</p>'
        );

        $target = $this->article(
            'Universo osservabile: i confini della conoscenza', 'spazio',
            'Quanto possiamo vedere davvero dello spazio che ci circonda.',
            '<p>L\'universo osservabile ha un raggio limitato dalla velocità della luce e dall\'età del cosmo, circa 13,8 miliardi di anni.</p>'
        );

        $this->assertNotNull($this->suggestionFrom($source, $target));
    }

    /**
     * Gli acronimi corti dell'allowlist V3 (qui: ESA) restano comunque
     * soggetti al segnale di document-frequency V2 — non un bypass della
     * classificazione generico/specifico, solo della soglia minima di
     * lunghezza. Un acronimo onnipresente nel pool di candidati (>=20% dei
     * candidati) deve continuare a valere il punteggio ridotto, esattamente
     * come qualunque altra parola.
     */
    public function test_short_acronym_from_v3_allowlist_still_goes_through_v2_document_frequency(): void
    {
        // 6 articoli su 8 (75% del pool, ben oltre la soglia 20%) citano
        // "ESA" solo come riferimento istituzionale incidentale, mai come
        // argomento reale condiviso — devono restare classificati generici.
        for ($i = 1; $i <= 6; $i++) {
            $this->article(
                "Notizia breve numero {$i} sul programma spaziale europeo", 'spazio',
                null,
                '<p>Il programma ESA prosegue con vari progetti scientifici indipendenti tra loro.</p>'
            );
        }

        $source = $this->article(
            'Missione Rosetta: la cometa 67P', 'spazio',
            'Il primo atterraggio su una cometa della storia.',
            '<p>La missione ESA Rosetta ha rilasciato il lander Philae sulla cometa 67P/Churyumov-Gerasimenko nel 2014, un traguardo storico per l\'esplorazione spaziale.</p>'
        );

        $target = $this->article(
            'Il rover Curiosity su Marte', 'spazio',
            'Dieci anni di esplorazione della superficie marziana.',
            '<p>Il rover Curiosity della NASA esplora il cratere Gale su Marte dal 2012, analizzando rocce e atmosfera. Anche l\'ESA partecipa a missioni marziane con ExoMars.</p>'
        );

        // "ESA" da solo (generico, +5, dato il pool sopra) non deve MAI
        // bastare a collegare due missioni spaziali altrimenti scorrelate
        // (Rosetta/cometa vs Curiosity/Marte non condividono altro
        // vocabolario specifico).
        $this->assertNull($this->suggestionFrom($source, $target));
    }

    /**
     * Un trattino Unicode (copia-incolla editoriale) non deve MAI generare
     * un match cross-cluster spurio: normalizzato correttamente a "-",
     * l'identificatore tecnico risultante ("gpt-5") partecipa al matching
     * come la sua controparte ASCII, non genera alcun frammento fantasma
     * che potrebbe accidentalmente combaciare con vocabolario di un
     * cluster non correlato.
     */
    public function test_unicode_hyphen_does_not_produce_cross_cluster_false_match(): void
    {
        $source = $this->article(
            'Il futuro dei modelli linguistici', 'intelligenza-artificiale',
            'Cosa aspettarsi dai prossimi modelli.',
            "<p>Con l'arrivo di GPT\u{2011}5 (trattino non-breaking, tipico di un copia-incolla da Word), molti si interrogano sulle capacità dei nuovi modelli linguistici.</p>"
        );

        $unrelated = $this->article(
            'Le batterie al sodio spiegate semplice', 'energia',
            'Costi e prestazioni di una tecnologia emergente.',
            '<p>Le batterie al sodio promettono un costo delle materie prime nettamente inferiore rispetto al litio, per l\'accumulo stazionario di energia.</p>'
        );

        $this->assertNull($this->suggestionFrom($source, $unrelated));

        // Controllo positivo: lo stesso trattino Unicode, verso un
        // articolo genuinamente sullo stesso argomento, deve invece
        // contribuire normalmente al matching (stesso identificatore
        // "gpt-5" riconosciuto).
        $related = $this->article(
            'GPT-5: benchmark e prime impressioni', 'intelligenza-artificiale',
            'I risultati dei primi test indipendenti.',
            '<p>I benchmark di GPT-5 mostrano miglioramenti significativi rispetto ai modelli linguistici precedenti su compiti di ragionamento.</p>'
        );

        $this->assertNotNull($this->suggestionFrom($source, $related));
    }

    /**
     * DNA/RNA non diventano scorciatoie per suggerimenti irrilevanti: da
     * soli (senza altro vocabolario specifico condiviso) non bastano a
     * superare la soglia — restano comunque un singolo termine da +15
     * (o +5 se generico), non un bypass del punteggio minimo complessivo.
     */
    public function test_allowed_short_acronyms_alone_are_not_enough_to_cross_the_threshold(): void
    {
        $source = $this->article(
            'Come funziona la doppia elica del DNA', 'salute',
            'La struttura scoperta da Watson e Crick.',
            '<p>Il DNA è organizzato in una doppia elica, la cui struttura fu descritta nel 1953.</p>'
        );

        $target = $this->article(
            'Il DNA sintetico e le sue applicazioni industriali', 'tecnologia',
            'Biotecnologie applicate alla produzione industriale.',
            '<p>Il DNA sintetico trova applicazioni crescenti nell\'industria chimica e nella produzione di materiali biodegradabili.</p>'
        );

        // Unico termine condiviso realmente specifico e' "dna" (+15,
        // corpus troppo piccolo per il segnale document-frequency,
        // MIN_CORPUS_SIZE_FOR_SPECIFICITY=5 non raggiunto con soli 2
        // articoli) - punteggio massimo raggiungibile 15, ben sotto la
        // soglia 40: NON deve mai risultare suggerito da solo.
        $this->assertNull($this->suggestionFrom($source, $target));
    }
}
