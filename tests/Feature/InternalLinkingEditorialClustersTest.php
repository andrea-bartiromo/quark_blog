<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleLinkSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Caratterizzazione indipendente su un campione più vicino al catalogo
 * reale di Kairus, organizzato per CLUSTER TEMATICO (Spazio, IA, Salute,
 * Tecnologia, Ambiente). A differenza di InternalLinkingQualityTest.php
 * (una corpus condivisa unica), qui ogni test costruisce la propria
 * corpus ISOLATA e piccola (2-4 articoli): un test scorrelato non può mai
 * alterare il segnale di document-frequency di un altro (vedi missione
 * precedente — aggiungere righe a una corpus condivisa aveva causato una
 * regressione en passant su un test non correlato).
 *
 * Verifica due proprietà opposte e ugualmente importanti:
 *   A) articoli genuinamente correlati hanno una possibilità ragionevole
 *      di essere suggeriti (il motore non è così restrittivo da non
 *      proporre mai nulla);
 *   B) articoli di cluster diversi NON vengono collegati solo perché
 *      condividono fraseologia generica da "articolo divulgativo".
 *
 * Filosofia esplicita: meglio perdere un collegamento valido (falso
 * negativo) che proporne uno assurdo (falso positivo) — nessuna soglia è
 * stata abbassata per aumentare artificialmente i suggerimenti in (A).
 */
class InternalLinkingEditorialClustersTest extends TestCase
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
            ['email' => 'editor-cluster-test@kairus.it'],
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

    // ── A) Cluster SPAZIO — possibilità ragionevole di collegamento ────

    public function test_sole_and_betelgeuse_have_a_reasonable_chance_of_being_linked(): void
    {
        $sole = $this->article('Il Sole e la sua stabilità nucleare', 'spazio',
            'Fusione idrogeno-elio ed equilibrio idrostatico.',
            '<p>Il Sole mantiene la sua luminosità grazie alla fusione nucleare. Diverso il destino di stelle molto più massicce, che terminano in una supernova.</p>');

        $betelgeuse = $this->article('Betelgeuse e il rischio supernova', 'spazio',
            'La supergigante rossa di Orione e la sua luminosità variabile.',
            '<p>Betelgeuse è una supergigante rossa la cui luminosità varia, alimentando ipotesi su una futura supernova.</p>');

        $this->assertNotNull($this->suggestionFrom($sole, $betelgeuse));
        $this->assertNotNull($this->suggestionFrom($betelgeuse, $sole));
    }

    public function test_relativita_and_gps_cross_category_have_a_reasonable_chance_of_being_linked(): void
    {
        $relativita = $this->article('La relatività generale spiegata semplice', 'spazio',
            'Einstein e la curvatura dello spaziotempo.',
            '<p>La relatività generale descrive la gravità come curvatura dello spaziotempo. Il GPS deve correggere i suoi orologi atomici per gli effetti relativistici.</p>');

        $gps = $this->article('Come funziona il posizionamento GPS', 'societa',
            'Trilaterazione e orologi atomici di bordo.',
            '<p>Il GPS calcola la posizione tramite trilaterazione con orologi atomici. Senza correzioni relativistiche, il sistema andrebbe alla deriva.</p>');

        $this->assertNotNull($this->suggestionFrom($relativita, $gps));
        $this->assertNotNull($this->suggestionFrom($gps, $relativita));
    }

    // ── A) Cluster AI ───────────────────────────────────────────────

    public function test_chatgpt_and_turing_test_have_a_reasonable_chance_of_being_linked(): void
    {
        $chatgpt = $this->article('ChatGPT e i modelli linguistici', 'intelligenza-artificiale',
            'Come funziona un modello linguistico di grandi dimensioni.',
            '<p>ChatGPT è un\'interfaccia costruita su un modello linguistico addestrato a prevedere la parola successiva. Non supera necessariamente il test di Turing in ogni conversazione.</p>');

        $turing = $this->article('Il test di Turing, 75 anni dopo', 'intelligenza-artificiale',
            'Il gioco dell\'imitazione di Alan Turing.',
            '<p>Il test di Turing propone che una macchina sia intelligente se indistinguibile da un umano in conversazione. I moderni modelli linguistici riaprono il dibattito.</p>');

        $this->assertNotNull($this->suggestionFrom($chatgpt, $turing));
        $this->assertNotNull($this->suggestionFrom($turing, $chatgpt));
    }

    // ── A) Cluster SALUTE ───────────────────────────────────────────

    public function test_diagnosi_ai_and_chirurgia_robotica_have_a_reasonable_chance_of_being_linked(): void
    {
        $diagnosi = $this->article('Intelligenza artificiale e diagnosi medica', 'salute',
            'Visione artificiale su radiografie e TAC.',
            '<p>I sistemi di intelligenza artificiale per la diagnosi medica analizzano immagini radiologiche. Restano strumenti di supporto al chirurgo, non sostituti.</p>');

        $chirurgia = $this->article('La chirurgia robotica in sala operatoria', 'salute',
            'Precisione millimetrica e comandi del chirurgo.',
            '<p>I sistemi di chirurgia robotica traducono i movimenti del chirurgo in incisioni precise. L\'intelligenza artificiale inizia ad assistere anche in fase diagnostica.</p>');

        $this->assertNotNull($this->suggestionFrom($diagnosi, $chirurgia));
        $this->assertNotNull($this->suggestionFrom($chirurgia, $diagnosi));
    }

    // ── A) Cluster AMBIENTE ─────────────────────────────────────────

    public function test_clima_and_fotovoltaico_have_a_reasonable_chance_of_being_linked(): void
    {
        $clima = $this->article('Come si misura il cambiamento climatico', 'ambiente',
            'Satelliti, carotaggi di ghiaccio e stazioni meteo.',
            '<p>Il cambiamento climatico si misura tramite osservazioni satellitari e stazioni meteo. La transizione verso il fotovoltaico è una delle risposte principali.</p>');

        $fotovoltaico = $this->article('Come funziona un pannello fotovoltaico', 'energia',
            'Effetto fotoelettrico e silicio semiconduttore.',
            '<p>Un pannello fotovoltaico converte la luce solare in elettricità. È una delle tecnologie chiave per ridurre le emissioni legate al cambiamento climatico.</p>');

        $this->assertNotNull($this->suggestionFrom($clima, $fotovoltaico));
        $this->assertNotNull($this->suggestionFrom($fotovoltaico, $clima));
    }

    // ── B) Cluster diversi NON collegati da fraseologia generica ────

    /**
     * Il segnale di specificità (document-frequency) si disattiva sotto
     * MIN_CORPUS_SIZE_FOR_SPECIFICITY=5 candidati per costruzione (vedi
     * ArticleLinkSuggestionService) — un corpus isolato di sole 2 righe,
     * per quanto "pulito" a fini di isolamento test, NON è rappresentativo
     * del catalogo reale di Kairus (decine di articoli pubblicati) e
     * farebbe scattare falsi positivi che in produzione non si
     * osserverebbero. Filler minimi (mai referenziati da un'asserzione)
     * per portare la corpus locale a >=5 candidati, restando comunque
     * isolata al singolo test — nessuna condivisione tra metodi.
     */
    private function withFillerCandidates(): void
    {
        $this->article('Rassegna breve: tre notizie di scienza della settimana', 'spazio',
            null, '<p>Una raccolta di notizie brevi senza approfondimento specifico.</p>');
        $this->article('Notizie in breve dal mondo della tecnologia', 'societa',
            null, '<p>Aggiornamenti rapidi su vari argomenti tecnologici.</p>');
        $this->article('Curiosità scientifiche del mese', 'salute',
            null, '<p>Una selezione di curiosità scientifiche varie.</p>');
        $this->article('Approfondimenti brevi di attualità scientifica', 'ambiente',
            null, '<p>Sintesi di alcuni temi di attualità scientifica.</p>');

    }

    public function test_spazio_and_ai_are_not_linked_through_generic_popular_science_language(): void
    {
        $this->withFillerCandidates();

        $spazio = $this->article('Il futuro dell\'esplorazione spaziale', 'spazio',
            'Nuove missioni e tecnologie per i prossimi decenni.',
            '<p>Il settore vede uno sviluppo continuo, con nuove tecnologie che permettono risultati importanti nei prossimi anni per l\'esplorazione dello spazio.</p>');

        $ai = $this->article('Il futuro dell\'intelligenza artificiale in medicina', 'intelligenza-artificiale',
            'Nuove applicazioni cliniche nei prossimi anni.',
            '<p>Il settore medico vede uno sviluppo continuo, con nuove tecnologie che permettono risultati importanti nei prossimi anni per la diagnosi.</p>');

        $this->assertNull($this->suggestionFrom($spazio, $ai));
        $this->assertNull($this->suggestionFrom($ai, $spazio));
    }

    public function test_salute_and_tecnologia_are_not_linked_through_generic_popular_science_language(): void
    {
        $this->withFillerCandidates();

        $salute = $this->article('Nuove scoperte sul sonno e i sogni', 'salute',
            'Cosa succede al cervello durante il sonno REM.',
            '<p>Gli esperti sottolineano come questo campo stia cambiando rapidamente, con nuovi studi che aprono prospettive interessanti per la ricerca futura.</p>');

        $tecnologia = $this->article('Il Wi-Fi del futuro: cosa cambia con i nuovi standard', 'societa',
            'Wi-Fi 7 e le reti domestiche di prossima generazione.',
            '<p>Gli esperti sottolineano come questo campo stia cambiando rapidamente, con nuovi standard che aprono prospettive interessanti per gli utenti futuri.</p>');

        $this->assertNull($this->suggestionFrom($salute, $tecnologia));
        $this->assertNull($this->suggestionFrom($tecnologia, $salute));
    }

    public function test_ambiente_and_spazio_are_not_linked_through_generic_popular_science_language(): void
    {
        $this->withFillerCandidates();

        $ambiente = $this->article('Le api e il loro ruolo negli ecosistemi', 'ambiente',
            'Impollinazione e biodiversità agricola.',
            '<p>Si tratta di un argomento che merita attenzione crescente, dato il suo impatto significativo sul sistema nel suo complesso, secondo diversi esperti del settore.</p>');

        $spazio = $this->article('Il paradosso di Olbers: perché il cielo è nero', 'spazio',
            'Guardare lontano nello spazio significa guardare nel passato.',
            '<p>Si tratta di un argomento che merita attenzione crescente, dato il suo impatto significativo sul sistema nel suo complesso, secondo diversi esperti del settore.</p>');

        $this->assertNull($this->suggestionFrom($ambiente, $spazio));
        $this->assertNull($this->suggestionFrom($spazio, $ambiente));
    }

    /**
     * Documenta esplicitamente la degradazione a corpus piccolo (vedi
     * withFillerCandidates()): con SOLO i due articoli target (nessun
     * filler, corpus size 2 < MIN_CORPUS_SIZE_FOR_SPECIFICITY=5) il
     * segnale di specificità è disattivo per progetto — il comportamento
     * degrada a "tutti i termini specifici" (V1-equivalente) e la stessa
     * coppia PUÒ risultare suggerita. Non è un bug: è la soglia di
     * sicurezza statistica already documentata, con le sue conseguenze
     * rese esplicite qui invece che lasciate implicite.
     */
    public function test_generic_language_false_positive_reappears_below_the_minimum_corpus_size(): void
    {
        $spazio = $this->article('Il futuro dell\'esplorazione spaziale', 'spazio',
            'Nuove missioni e tecnologie per i prossimi decenni.',
            '<p>Il settore vede uno sviluppo continuo, con nuove tecnologie che permettono risultati importanti nei prossimi anni per l\'esplorazione dello spazio.</p>');

        $ai = $this->article('Il futuro dell\'intelligenza artificiale in medicina', 'intelligenza-artificiale',
            'Nuove applicazioni cliniche nei prossimi anni.',
            '<p>Il settore medico vede uno sviluppo continuo, con nuove tecnologie che permettono risultati importanti nei prossimi anni per la diagnosi.</p>');

        $this->assertNotNull($this->suggestionFrom($spazio, $ai));
    }
}
