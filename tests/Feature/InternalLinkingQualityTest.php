<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleLinkSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Internal Linking — suite di qualità permanente, corpus sintetico
 * realistico su 6 categorie Kairus (Spazio, Intelligenza Artificiale,
 * Tecnologia & Società, Ambiente, Energia & Clima, Salute & Biotech).
 *
 * Ricostruisce l'audit empirico di 32 casi (24 PASS / 8 FAIL su V1,
 * pre-#140/pre-V2). Le aspettative qui sotto descrivono il comportamento
 * DESIDERATO — quello che un editor umano onesto giudicherebbe corretto —
 * non quello che il codice attuale produce. Non sono state abbassate per
 * far passare l'implementazione: dove il motore non può ragionevolmente
 * risolvere un caso senza inventare segnali semantici fuori scope
 * (nessun vocabolario letterale condiviso — limite lessicale accettato da
 * #135), l'aspettativa resta "non suggerito" perché è la risposta onesta,
 * non perché il codice oggi si comporta così.
 *
 * Esecuzione contro V1 (main post-#140, prima di V2): documenta
 * esattamente quali assertion sono rosse — vedi commento per ciascun caso.
 */
class InternalLinkingQualityTest extends TestCase
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
                'slug' => Str::slug($title).'-'.uniqid(),
                'excerpt' => $excerpt,
                'body' => $body,
                'category' => $category,
                'status' => 'published',
                'published_at' => now()->subDays(random_int(1, 30)),
                'read_minutes' => 4,
                'verification_status' => 'unverified',
            ]);
        };

        // ── Spazio ────────────────────────────────────────────────────
        $mk('sole', 'Perché il Sole non esplode', 'spazio',
            'Un viaggio nell\'equilibrio idrostatico che tiene in vita la nostra stella.',
            '<p>Il Sole è una stella stabile grazie all\'equilibrio idrostatico tra pressione '.
            'di radiazione e gravità. Diverso il caso di Betelgeuse, la cui luminosità varia '.
            'nel tempo e che un giorno terminerà la sua vita in una spettacolare supernova.</p>');

        $mk('betelgeuse', 'Betelgeuse: la supergigante che potrebbe esplodere', 'spazio',
            'Tutto sulla luminosità e la futura supernova di Betelgeuse, supergigante rossa di Orione.',
            '<p>Betelgeuse è una supergigante rossa che potrebbe esplodere come supernova.</p>');

        $mk('relativita', 'La relatività generale spiegata semplice', 'spazio',
            'Einstein e la curvatura dello spaziotempo, dalla teoria alle applicazioni pratiche.',
            '<p>La relatività generale di Einstein descrive la gravità come curvatura dello '.
            'spaziotempo. Un\'applicazione pratica sorprendente: i satelliti GPS devono correggere '.
            'i loro orologi atomici per la dilatazione temporale relativistica, altrimenti la '.
            'posizione calcolata deriverebbe di chilometri al giorno.</p>');

        $mk('apollo', 'Missione Artemis: si torna sulla Luna', 'spazio',
            'A oltre 50 anni da Apollo 11, la NASA pianifica il ritorno sulla Luna.',
            '<p>A 57 anni dalla missione Apollo 11, la NASA pianifica Artemis, il ritorno '.
            'sulla Luna con equipaggio.</p>');

        $mk('gps', 'Come funziona il GPS', 'tecnologia',
            'Triangolazione satellitare, orologi atomici e correzione relativistica.',
            '<p>Il GPS usa una costellazione di satelliti con orologi atomici di bordo. La '.
            'correzione relativistica (dovuta sia alla relatività ristretta sia a quella generale) '.
            'è essenziale per la precisione del posizionamento.</p>');

        // ── Intelligenza Artificiale ─────────────────────────────────
        $mk('gpt5', 'GPT-5 supera i benchmark scientifici', 'intelligenza-artificiale',
            'Il nuovo modello di OpenAI supera GPT-4o nei benchmark scientifici.',
            '<p>Il nuovo modello GPT-5 di OpenAI supera GPT-4o nei benchmark scientifici, '.
            'secondo quanto riportato dai ricercatori.</p>');

        $mk('ia_medica', 'Come l\'IA sta cambiando la diagnosi medica', 'intelligenza-artificiale',
            'Machine learning e radiologia: l\'IA supporta i medici nella diagnosi.',
            '<p>Il machine learning applicato alla radiologia aiuta i medici nella diagnosi '.
            'precoce di tumori, analizzando migliaia di immagini TAC.</p>');

        // ── Tecnologia & Società ─────────────────────────────────────
        $mk('remoto', 'Lavoro remoto: la rivoluzione dello smart working', 'tecnologia',
            'Produttività, tecnologia e nuovi equilibri tra vita privata e lavoro.',
            '<p>Il lavoro remoto ha cambiato la produttività aziendale, spinto da nuova '.
            'tecnologia di collaborazione a distanza.</p>');

        $mk('connettivita', '5G e Wi-Fi 6: la nuova generazione di connettività', 'tecnologia',
            'Velocità e banda: cosa cambia con 5G e Wi-Fi 6 negli uffici e nel lavoro remoto.',
            '<p>Il 5G e il Wi-Fi 6 offrono velocità e banda molto superiori, abilitando anche '.
            'un lavoro remoto più fluido con videochiamate in alta definizione.</p>');

        // Fraseologia tecnologica generica: due articoli NON correlati che
        // condividono solo termini onnipresenti ("nuove", "tecnologie",
        // "prossimi", "importante"-filtrato) — il falso positivo osservato
        // empiricamente nell'audit (score 45, anchor "tecnologie").
        $mk('generico_spazio', 'Il futuro della tecnologia spaziale', 'spazio',
            'Nuove tecnologie per l\'esplorazione dello spazio nei prossimi decenni.',
            '<p>Il settore vede uno sviluppo continuo, con nuove tecnologie che permettono '.
            'risultati importanti nei prossimi anni.</p>');

        $mk('generico_medico', 'Il futuro della tecnologia medica', 'salute',
            'Nuove tecnologie per la medicina nei prossimi decenni.',
            '<p>Il settore medico vede uno sviluppo continuo, con nuove tecnologie che '.
            'permettono risultati importanti nei prossimi anni.</p>');

        // Corpus filler: la fraseologia generica ("nuove", "tecnologie",
        // "settore", "sviluppo", "prossimi") ricorre realisticamente in
        // molti articoli editoriali indipendentemente dal tema — non sono
        // casi di test individuali (nessuna assertion li referenzia per
        // nome), servono solo a dare al segnale di specificità lessicale
        // (document frequency) una base di corpus realistica, non a
        // truccare artificialmente il caso sopra.
        $mk('filler_1', 'Il mercato dei trasporti elettrici cambia rotta', 'tecnologia',
            'Il settore dei trasporti vede uno sviluppo continuo verso nuove soluzioni.',
            '<p>Il settore vede uno sviluppo continuo, con nuove tecnologie che permettono '.
            'risultati importanti nei prossimi anni per la mobilità urbana.</p>');

        $mk('filler_2', 'Robotica industriale: cosa cambia in fabbrica', 'tecnologia',
            'Il settore manifatturiero vede uno sviluppo continuo grazie a nuove tecnologie.',
            '<p>Il settore vede uno sviluppo continuo, con nuove tecnologie che permettono '.
            'risultati importanti nei prossimi anni per la produttività.</p>');

        $mk('filler_3', 'Agricoltura di precisione: i droni in campo', 'ambiente',
            'Il settore agricolo vede uno sviluppo continuo grazie a nuove tecnologie.',
            '<p>Il settore vede uno sviluppo continuo, con nuove tecnologie che permettono '.
            'risultati importanti nei prossimi anni per le coltivazioni.</p>');

        $mk('filler_4', 'Materiali avanzati per l\'edilizia sostenibile', 'energia',
            'Il settore delle costruzioni vede uno sviluppo continuo verso nuove soluzioni.',
            '<p>Il settore vede uno sviluppo continuo, con nuove tecnologie che permettono '.
            'risultati importanti nei prossimi anni per l\'edilizia.</p>');

        // ── Ambiente / Energia & Clima ───────────────────────────────
        $mk('clima_satellite', 'Il riscaldamento globale visto dai satelliti', 'ambiente',
            'Monitoraggio satellitare e CO2: cosa ci dicono i dati sul clima.',
            '<p>Il monitoraggio satellitare misura la concentrazione di CO2 in atmosfera, '.
            'confermando il trend di riscaldamento globale e l\'aumento delle temperature medie.</p>');

        $mk('ghiacciai', 'Scioglimento dei ghiacciai: i dati NASA', 'energia',
            'Clima, satellite e temperatura: cosa rivelano i dati NASA sui ghiacciai.',
            '<p>I dati satellitari NASA mostrano lo scioglimento accelerato dei ghiacciai, '.
            'legato al riscaldamento globale e all\'aumento delle temperature.</p>');

        $mk('solare', 'Energia solare: il futuro del fotovoltaico', 'energia',
            'Pannelli solari ed energie rinnovabili: lo stato dell\'arte.',
            '<p>I pannelli solari di nuova generazione aumentano l\'efficienza delle energie '.
            'rinnovabili, riducendo il costo per kWh prodotto.</p>');

        $mk('biodiversita', 'Biodiversità in pericolo: la deforestazione in Amazzonia', 'ambiente',
            'Ecosistema e deforestazione: la crisi della biodiversità in Amazzonia.',
            '<p>La deforestazione in Amazzonia minaccia l\'ecosistema più ricco di '.
            'biodiversità del pianeta.</p>');

        // Famiglia morfologica satellite/satellitare/satelliti/satellitari,
        // ripetuta in body E titolo+excerpt su entrambi i lati — controllo
        // di simmetria per isolare l'asimmetria titolo+excerpt/body da un
        // problema di corpus.
        $mk('sat_a', 'Satelliti e clima: il monitoraggio dall\'orbita', 'ambiente',
            'Satelliti in orbita monitorano il clima e la temperatura globale.',
            '<p>I satelliti in orbita monitorano costantemente il clima e la temperatura '.
            'globale, fornendo dati satellitari cruciali.</p>');

        $mk('sat_b', 'Come i dati satellitari cambiano la scienza del clima', 'energia',
            'Dati satellitari, clima e temperatura: la nuova scienza dell\'osservazione.',
            '<p>I dati satellitari sul clima e sulla temperatura globale permettono previsioni '.
            'più accurate, grazie ai satelliti in orbita.</p>');

        // ── Salute & Biotech ─────────────────────────────────────────
        $mk('crispr', 'CRISPR-Cas9: cos\'è l\'editing genetico', 'salute',
            'DNA, terapia genica e le applicazioni di CRISPR-Cas9 in medicina.',
            '<p>CRISPR-Cas9 è uno strumento di editing genetico che taglia il DNA in punti '.
            'precisi, aprendo la strada alla terapia genica per malattie ereditarie.</p>');

        $mk('mrna', 'Vaccini a mRNA: come funzionano', 'salute',
            'Biotecnologia e immunità: il meccanismo dei vaccini a mRNA.',
            '<p>I vaccini a mRNA insegnano alle cellule a produrre una proteina che innesca '.
            'una risposta immunitaria, senza usare il virus vivo.</p>');

        $mk('dieta', 'Dieta mediterranea e obesità infantile', 'salute',
            'Nutrizione e prevenzione: cosa dice la scienza sulla dieta mediterranea.',
            '<p>La dieta mediterranea è associata a una minore incidenza di obesità '.
            'infantile, secondo diversi studi nutrizionali.</p>');

        // Soglia: 2 termini specifici + bonus categoria = esattamente al
        // limite storico di MIN_SCORE_THRESHOLD (40, introdotto da #135).
        $mk('supernove', 'Le supernove e la nucleosintesi stellare', 'spazio',
            'Come le supernove producono gli elementi pesanti nell\'universo.',
            '<p>Le supernove sono responsabili della nucleosintesi degli elementi pesanti.</p>');

        $mk('nane_bianche', 'Le nane bianche e il loro destino finale', 'spazio',
            'Nucleosintesi e supernove: il destino finale delle nane bianche.',
            '<p>Le nane bianche possono diventare supernove attraverso la nucleosintesi.</p>');
    }

    private function suggestionFor(string $sourceKey, string $targetKey): ?object
    {
        $suggestions = $this->service->analyzeForSource($this->corpus[$sourceKey]->fresh());

        return $suggestions->firstWhere('target_article_id', $this->corpus[$targetKey]->id);
    }

    // ── 1. Sole ↔ Betelgeuse ─────────────────────────────────────────

    public function test_sole_suggests_betelgeuse(): void
    {
        $this->assertNotNull($this->suggestionFor('sole', 'betelgeuse'));
    }

    // Direzionalità (9): la stessa coppia deve funzionare anche al contrario.
    public function test_betelgeuse_suggests_sole_symmetrically(): void
    {
        $this->assertNotNull($this->suggestionFor('betelgeuse', 'sole'));
    }

    // Qualità anchor (11): l'anchor scelta deve essere il nome proprio
    // specifico, non un sostantivo generico condiviso più lungo per caso.
    public function test_sole_to_betelgeuse_anchor_is_the_specific_entity_not_a_generic_noun(): void
    {
        $suggestion = $this->suggestionFor('sole', 'betelgeuse');

        $this->assertNotNull($suggestion);
        $this->assertSame('Betelgeuse', $suggestion->anchor_text);
    }

    // ── 2. relatività ↔ GPS (cross-category) ────────────────────────

    public function test_relativita_suggests_gps_cross_category(): void
    {
        $this->assertNotNull($this->suggestionFor('relativita', 'gps'));
    }

    public function test_gps_suggests_relativita_symmetrically(): void
    {
        $this->assertNotNull($this->suggestionFor('gps', 'relativita'));
    }

    // ── 3. IA ↔ diagnosi medica ───────────────────────────────────────
    // Condividono solo vocabolario IA generico ("intelligenza", "modello"):
    // nessuna relazione editoriale reale, deve restare non suggerito anche
    // in V2 — non è un caso da "correggere".

    public function test_gpt5_does_not_suggest_ia_medica_shared_generic_ai_vocabulary_only(): void
    {
        $this->assertNull($this->suggestionFor('gpt5', 'ia_medica'));
    }

    // ── 4. clima ↔ satellite (famiglia morfologica, categoria 10) ────

    public function test_clima_satellite_suggests_ghiacciai_despite_morphological_variation(): void
    {
        // satellite/satellitare/satellitari/satelliti: forme diverse della
        // stessa radice, oggi (V1) mai riconosciute come lo stesso termine.
        $this->assertNotNull($this->suggestionFor('clima_satellite', 'ghiacciai'));
    }

    public function test_ghiacciai_suggests_clima_satellite_symmetrically(): void
    {
        $this->assertNotNull($this->suggestionFor('ghiacciai', 'clima_satellite'));
    }

    public function test_satellite_family_symmetry_control_a_to_b(): void
    {
        $this->assertNotNull($this->suggestionFor('sat_a', 'sat_b'));
    }

    public function test_satellite_family_symmetry_control_b_to_a(): void
    {
        $this->assertNotNull($this->suggestionFor('sat_b', 'sat_a'));
    }

    // ── 5. Cross-category valido ──────────────────────────────────────

    public function test_remoto_suggests_connettivita_cross_topic_shared_vocabulary(): void
    {
        $this->assertNotNull($this->suggestionFor('remoto', 'connettivita'));
    }

    public function test_connettivita_suggests_remoto_symmetrically(): void
    {
        $this->assertNotNull($this->suggestionFor('connettivita', 'remoto'));
    }

    // ── 6. Stessa categoria ma contenuti non correlati (mai falsi positivi) ──

    public function test_relativita_does_not_suggest_apollo_same_category_unrelated(): void
    {
        $this->assertNull($this->suggestionFor('relativita', 'apollo'));
    }

    public function test_sole_does_not_suggest_apollo_same_category_unrelated(): void
    {
        $this->assertNull($this->suggestionFor('sole', 'apollo'));
    }

    public function test_crispr_does_not_suggest_dieta_same_category_unrelated(): void
    {
        $this->assertNull($this->suggestionFor('crispr', 'dieta'));
    }

    public function test_clima_satellite_does_not_suggest_biodiversita_same_category_unrelated(): void
    {
        $this->assertNull($this->suggestionFor('clima_satellite', 'biodiversita'));
    }

    public function test_solare_does_not_suggest_ghiacciai_same_category_unrelated(): void
    {
        $this->assertNull($this->suggestionFor('solare', 'ghiacciai'));
    }

    // ── 7. Fraseologia tecnologica generica (falso positivo da correggere) ──

    public function test_generic_tech_phrasing_alone_is_not_enough_to_suggest_unrelated_topics(): void
    {
        // V1: FALSO POSITIVO empiricamente osservato — score 45, anchor
        // "tecnologie". V2 (STEP C, segnale di document frequency): "nuove",
        // "tecnologie", "prossimi" sono troppo comuni nel pool di candidati
        // per costituire da soli un segnale di pertinenza reale.
        $this->assertNull($this->suggestionFor('generico_spazio', 'generico_medico'));
    }

    public function test_generic_tech_phrasing_alone_is_not_enough_reverse_direction(): void
    {
        $this->assertNull($this->suggestionFor('generico_medico', 'generico_spazio'));
    }

    // ── 8. Soglia esatta ───────────────────────────────────────────────

    public function test_threshold_boundary_case_is_suggested(): void
    {
        // 2 termini specifici condivisi (nucleosintesi, supernove) + bonus
        // categoria: esattamente al limite storico di MIN_SCORE_THRESHOLD.
        $this->assertNotNull($this->suggestionFor('supernove', 'nane_bianche'));
    }

    public function test_threshold_boundary_case_is_suggested_reverse(): void
    {
        $this->assertNotNull($this->suggestionFor('nane_bianche', 'supernove'));
    }

    // ── 12. Assenza di vocabolario letterale comune (limite accettato) ──

    public function test_crispr_does_not_suggest_mrna_no_shared_literal_vocabulary(): void
    {
        // Concettualmente entrambi "biotecnologia", ma i testi come scritti
        // non condividono alcun termine letterale. Questo è il limite
        // lessicale esplicitamente documentato e accettato da #135 (nessun
        // embedding/similarità semantica in questa missione) — la risposta
        // onesta resta "non suggerito", non un difetto da correggere in V2.
        $this->assertNull($this->suggestionFor('crispr', 'mrna'));
    }
}
