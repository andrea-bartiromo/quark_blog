<?php

namespace Tests\Unit\EditorialQuality;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleLinkInsertionService;
use App\Services\EditorialQuality\EditorialQualityChecker;
use App\Services\EditorialQuality\EditorialQualityCheckResult as R;
use App\Services\EditorialQuality\EditorialQualityReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialQualityCheckerTest extends TestCase
{
    use RefreshDatabase;

    private EditorialQualityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new EditorialQualityChecker(new ArticleLinkInsertionService);
    }

    /**
     * Articolo "completo": deve passare tutti i controlli applicabili
     * senza alcuna eccezione — usato come baseline per gli scenari
     * A/B/C/D/E/F/G/H/I/J/K/L della missione.
     */
    private function completeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'La scoperta di un nuovo esopianeta abitabile',
            'slug' => 'scoperta-esopianeta-abitabile-'.uniqid(),
            'excerpt' => 'Un team internazionale ha individuato un pianeta nella zona abitabile di una stella vicina.',
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso sulla scoperta. ', 15).'</p>',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'cover_image' => 'copertina.webp',
            'cover_alt' => 'Rappresentazione artistica dell\'esopianeta',
            'primary_sources' => 'https://www.nasa.gov/press-release/esopianeta',
        ], $overrides));
    }

    private function resultFor(EditorialQualityReport $report, string $code): R
    {
        foreach ($report->results as $result) {
            if ($result->code === $code) {
                return $result;
            }
        }

        $this->fail("Nessun risultato con code={$code}");
    }

    // ── Titolo ──

    public function test_a_blank_title_fails(): void
    {
        $article = $this->completeArticle(['title' => '   ']);

        $result = $this->resultFor($this->checker->check($article), 'title_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
        $this->assertSame(R::IMPORTANCE_ESSENTIAL, $result->importance);
    }

    public function test_a_short_but_legitimate_title_still_passes(): void
    {
        $article = $this->completeArticle(['title' => 'Ali']);

        $result = $this->resultFor($this->checker->check($article), 'title_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    // ── Slug ──

    public function test_a_blank_slug_fails(): void
    {
        $article = $this->completeArticle();
        $article->slug = '';

        $result = $this->resultFor($this->checker->check($article), 'slug_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_an_invalid_slug_format_fails(): void
    {
        $article = $this->completeArticle();
        $article->slug = 'Slug Con Spazi!';

        $result = $this->resultFor($this->checker->check($article), 'slug_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_a_historical_slug_unrelated_to_the_current_title_still_passes(): void
    {
        $article = $this->completeArticle(['title' => 'Titolo cambiato molte volte']);
        $article->slug = 'slug-storico-invariato';

        $result = $this->resultFor($this->checker->check($article), 'slug_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    // ── Sommario ──

    public function test_an_empty_excerpt_warns_but_never_fails(): void
    {
        $article = $this->completeArticle(['excerpt' => '']);

        $result = $this->resultFor($this->checker->check($article), 'excerpt_present');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    // ── Corpo ──

    public function test_an_html_only_body_with_no_real_text_fails(): void
    {
        $article = $this->completeArticle(['body' => '<p>&nbsp;</p><br><hr>']);

        $result = $this->resultFor($this->checker->check($article), 'body_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_a_body_made_only_of_images_without_real_text_fails(): void
    {
        $article = $this->completeArticle(['body' => '<img src="/a.jpg"><img src="/b.jpg">']);

        $result = $this->resultFor($this->checker->check($article), 'body_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_a_short_but_legitimate_body_still_passes_if_above_the_word_threshold(): void
    {
        $article = $this->completeArticle(['body' => '<p>'.str_repeat('parola ', 60).'</p>']);

        $result = $this->resultFor($this->checker->check($article), 'body_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    // ── Placeholder ──

    public function test_lorem_ipsum_in_the_body_is_detected(): void
    {
        $article = $this->completeArticle(['body' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>']);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_a_todo_marker_in_the_excerpt_is_detected(): void
    {
        $article = $this->completeArticle(['excerpt' => 'TODO: scrivere il sommario definitivo']);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    /**
     * Falso positivo noto da evitare: un articolo scientifico legittimo
     * può usare "test" in senso tecnico ("test di laboratorio",
     * "Test di Turing") senza essere un placeholder.
     */
    public function test_the_word_test_used_in_a_legitimate_scientific_context_is_never_flagged(): void
    {
        $article = $this->completeArticle([
            'title' => 'Il nuovo test di laboratorio per la diagnosi precoce',
            'body' => '<p>'.str_repeat('Il test clinico ha coinvolto centinaia di pazienti in tutta Italia. ', 10).'</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    // ── Placeholder — falso positivo reale (articoli #2 e #15 in produzione):
    // "todo" e' una sottostringa letterale di "metodo"/"metodologia"/
    // "metodologico", parole scientifiche legittime e comuni. Il marker
    // deve continuare a essere riconosciuto quando e' davvero un
    // segnaposto (parola a se stante, delimitata da spazi/punteggiatura),
    // mai quando e' incorporato dentro una parola più lunga. ──

    public function test_the_word_metodo_never_triggers_the_todo_marker(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Il metodo utilizzato dai ricercatori si e\' rivelato efficace. ', 10).'</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_the_word_metodologia_never_triggers_the_todo_marker(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('La metodologia sperimentale adottata e\' descritta di seguito. ', 10).'</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_the_word_metodologico_never_triggers_the_todo_marker(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Un approccio metodologico rigoroso guida questa ricerca scientifica. ', 10).'</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_a_word_that_accidentally_embeds_a_marker_substring_is_never_flagged(): void
    {
        // "fixme" non e' una sottostringa nota di alcuna parola italiana
        // comune, ma il principio va comunque verificato in astratto: un
        // marker incorporato in una parola più lunga, con lettere sui due
        // lati, non deve mai far scattare il controllo.
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Un prefissoxxxxxxxxsuffisso non e\' mai un segnaposto reale. ', 10).'</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_standalone_uppercase_todo_is_still_detected(): void
    {
        $article = $this->completeArticle(['body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><p>TODO</p>']);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_todo_with_a_colon_is_still_detected(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><p>TODO: completare questa sezione</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_todo_mid_sentence_is_still_detected(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><p>Testo provvisorio. TODO aggiungere fonte.</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_todo_wrapped_in_html_tags_is_still_detected(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><p>TODO</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    /**
     * Falso negativo segnalato in review: se il body salvato non ha alcuno
     * spazio letterale tra due tag di blocco adiacenti (tipico di un
     * editor che non inserisce whitespace tra i paragrafi), strip_tags()
     * da solo fonderebbe "...sostanzioso.TODO" in un'unica "parola",
     * mascherando un placeholder reale. Il confine tra tag di blocco deve
     * sempre contare come separatore, anche senza whitespace nell'HTML.
     */
    public function test_todo_immediately_adjacent_to_a_block_tag_boundary_with_no_literal_whitespace_is_detected(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso.', 15).'</p><p>TODO</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    /**
     * Falso positivo segnalato in review: un marker che compare solo
     * dentro un attributo HTML tra virgolette (mai visibile a un lettore)
     * non deve mai contare come segnaposto. Con un'estrazione basata su
     * regex "[^>]*", il ">" dentro l'attributo tronca il tag in anticipo e
     * fa trapelare il resto dell'attributo come testo — un parser DOM
     * vero analizza correttamente gli attributi e non ha questo problema.
     */
    public function test_a_marker_inside_a_quoted_html_attribute_is_never_flagged(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><p><span title="A &gt; TODO">testo</span></p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    /**
     * Contropartita del test precedente: i tag "inline" (formattazione
     * dentro una parola, incl. gli span-artefatto tipici del copia-incolla
     * da Word/Docs in TinyMCE) non devono introdurre uno spazio artificiale
     * che spezzerebbe una parola legittima in due token — altrimenti
     * "<em>me</em>todo" (renderizzato come "metodo") verrebbe letto come
     * "me" + "todo", reintroducendo esattamente il falso positivo che
     * questa missione elimina.
     */
    public function test_a_legitimate_word_split_by_an_inline_formatting_tag_is_never_flagged(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Il <em>me</em>todo utilizzato dai ricercatori si e\' rivelato efficace. ', 10).'</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    /**
     * Falso positivo segnalato in review: <wbr> (punto di interruzione di
     * parola facoltativo) e altri elementi di fraseggio HTML5 non comuni
     * (time, kbd, samp, var, ...) non introducono mai uno spazio visibile
     * — "me<wbr>todo" resta "metodo" per un lettore, esattamente come per
     * i tag di formattazione già coperti dal test precedente.
     */
    public function test_a_legitimate_word_split_by_a_word_break_tag_is_never_flagged(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Il me<wbr>todo utilizzato dai ricercatori si e\' rivelato efficace. ', 10).'</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    /**
     * Falso positivo segnalato in review: <label> è un altro elemento di
     * fraseggio non presente in una precedente allowlist di soli tag
     * "inline" noti. Da qui la scelta architetturale definitiva:
     * un'allowlist esplicita di tag di BLOCCO (piccola e chiusa) invece
     * che di tag inline (elenco HTML5 troppo ampio per essere enumerato
     * con certezza) — un tag sconosciuto o esotico come <label> resta
     * "inline" per default, senza bisogno di elencarlo esplicitamente.
     */
    public function test_a_legitimate_word_split_by_a_label_tag_is_never_flagged(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Il me<label>todo</label> utilizzato dai ricercatori si e\' rivelato efficace. ', 10).'</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    /**
     * Falso negativo segnalato in review: un elemento "replaced" come
     * <img> non ha mai un textContent proprio, quindi senza uno spazio
     * esplicito farebbe da collante invisibile tra due nodi di testo
     * altrimenti separati — "<p>testo<img src=\"x\">TODO</p>" diventerebbe
     * "testoTODO", mascherando un placeholder reale. Raggiungibile
     * concretamente tramite il plugin immagini di TinyMCE nell'admin.
     */
    public function test_todo_immediately_after_an_inline_image_with_no_literal_whitespace_is_detected(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'<img src="x">TODO</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    /**
     * Falso negativo segnalato in review: un tag non presente in una
     * precedente allowlist di soli tag di blocco (es. <dialog>, digitabile
     * tramite il plugin "code"/sorgente HTML di TinyMCE nell'admin) non
     * inseriva alcuno spazio, fondendo il testo di due elementi
     * visivamente distinti. Da qui la decisione architetturale finale:
     * il default è "inserisci uno spazio" per qualunque tag non elencato
     * esplicitamente come puro formattatore inline — un tag sconosciuto o
     * esotico come <dialog> resta quindi sempre un separatore.
     */
    public function test_a_marker_separated_only_by_an_unlisted_exotic_tag_is_still_detected(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><dialog open>testo</dialog><dialog open>TODO</dialog>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    /**
     * Falso positivo segnalato in review: quando l'HTML salvato contiene
     * un tag di chiusura non bilanciato (stesso bug di troncamento già
     * noto altrove nel codice, vedi ArticleTableOfContentsTest), il
     * contenuto successivo può finire "fuori" dal wrapper sintetico usato
     * per il parsing. Anche in quel caso limite un marker dentro un
     * attributo tra virgolette (mai visibile a un lettore) non deve mai
     * essere rilevato: l'estrazione resta interamente basata su DOM, mai
     * su una regex che potrebbe troncare a un ">" tra virgolette.
     */
    public function test_a_marker_inside_a_quoted_attribute_after_an_unbalanced_closing_tag_is_never_flagged(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p></div><p><span title="A &gt; TODO">visibile</span></p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_todo_inside_inline_formatting_tags_is_still_detected(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><p><strong>TODO:</strong> verificare questo dato</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_lowercase_todo_standalone_is_still_detected_case_insensitively(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><p>todo</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_the_word_fixme_style_markers_still_work_with_the_shared_boundary_rule(): void
    {
        $article = $this->completeArticle([
            'excerpt' => 'FIXME: questo sommario e\' ancora da rivedere completamente',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_lorem_ipsum_phrase_boundary_still_works(): void
    {
        $article = $this->completeArticle(['body' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>']);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_bracket_prefixed_marker_still_works_at_a_natural_word_boundary(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><p>Testo con [inserire qui il titolo definitivo].</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_da_completare_standalone_is_still_detected(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><p>Sezione da completare.</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_titolo_articolo_standalone_is_still_detected(): void
    {
        $article = $this->completeArticle(['title' => 'Titolo articolo']);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_placeholder_standalone_is_still_detected(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><p>[placeholder]</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_the_eight_x_placeholder_run_standalone_is_still_detected(): void
    {
        $article = $this->completeArticle([
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><p>xxxxxxxx</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    // ── Cover / alt ──

    public function test_no_cover_fails(): void
    {
        $article = $this->completeArticle(['cover_image' => null]);

        $result = $this->resultFor($this->checker->check($article), 'cover_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_a_cover_without_alt_text_fails_the_alt_check(): void
    {
        $article = $this->completeArticle(['cover_alt' => null]);

        $result = $this->resultFor($this->checker->check($article), 'cover_alt_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_the_cover_alt_check_is_not_applicable_without_a_cover(): void
    {
        $article = $this->completeArticle(['cover_image' => null]);

        $result = $this->resultFor($this->checker->check($article), 'cover_alt_present');

        $this->assertSame(R::STATUS_NOT_APPLICABLE, $result->status);
    }

    public function test_a_body_image_without_alt_warns(): void
    {
        $article = $this->completeArticle(['body' => '<p>'.str_repeat('Testo. ', 20).'</p><img src="/foto.jpg">']);

        $result = $this->resultFor($this->checker->check($article), 'body_images_alt');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_a_body_with_no_images_is_not_applicable(): void
    {
        $article = $this->completeArticle();

        $result = $this->resultFor($this->checker->check($article), 'body_images_alt');

        $this->assertSame(R::STATUS_NOT_APPLICABLE, $result->status);
    }

    // ── SEO ──

    public function test_seo_checks_are_not_applicable_for_a_draft(): void
    {
        $article = $this->completeArticle(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $report = $this->checker->check($article);

        $this->assertSame(R::STATUS_NOT_APPLICABLE, $this->resultFor($report, 'seo_title')->status);
        $this->assertSame(R::STATUS_NOT_APPLICABLE, $this->resultFor($report, 'meta_description')->status);
    }

    public function test_a_very_long_effective_seo_title_warns(): void
    {
        $article = $this->completeArticle(['title' => str_repeat('Un titolo scientifico molto lungo e dettagliato ', 3)]);

        $result = $this->resultFor($this->checker->check($article), 'seo_title');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_noindex_on_a_published_article_warns(): void
    {
        $article = $this->completeArticle(['robots' => 'noindex,follow']);

        $result = $this->resultFor($this->checker->check($article), 'indexability');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    // ── Struttura ──

    public function test_a_long_article_without_any_heading_warns(): void
    {
        $article = $this->completeArticle(['body' => '<p>'.str_repeat('parola ', 700).'</p>']);

        $result = $this->resultFor($this->checker->check($article), 'structure_headings');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_a_long_article_with_headings_passes(): void
    {
        $article = $this->completeArticle(['body' => '<h2>Introduzione</h2><p>'.str_repeat('parola ', 700).'</p>']);

        $result = $this->resultFor($this->checker->check($article), 'structure_headings');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_a_short_article_never_requires_headings(): void
    {
        $article = $this->completeArticle();

        $result = $this->resultFor($this->checker->check($article), 'structure_headings');

        $this->assertSame(R::STATUS_NOT_APPLICABLE, $result->status);
    }

    // ── Fonti ──

    public function test_missing_sources_warn_but_never_fail(): void
    {
        $article = $this->completeArticle(['primary_sources' => null]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_a_recognized_institutional_domain_is_surfaced_in_details(): void
    {
        $article = $this->completeArticle(['primary_sources' => 'Fonte: https://www.nature.com/articles/xyz']);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
        $this->assertSame('nature.com', $result->details['recognized_domain'] ?? null);
    }

    // ── Fonti strutturate nel corpo (falso negativo reale, articolo #13) ──

    /**
     * Riproduce esattamente l'articolo reale "Il Test di Turing spiegato
     * davvero": una sezione Fonti nel corpo con un elenco bibliografico
     * classico, senza alcun URL — il falso negativo osservato in
     * produzione (primary_sources mai compilato, sources_present in
     * warning nonostante le fonti fossero chiaramente presenti nel body).
     */
    public function test_a_body_sources_heading_with_a_classic_bibliography_list_passes_without_urls(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                <h3>Fonti</h3>
                <ul>
                    <li>Alan M. Turing, Computing Machinery and Intelligence, Mind, Vol. 59, n. 236 (1950).</li>
                    <li>Stanford Encyclopedia of Philosophy, The Turing Test.</li>
                    <li>Encyclopaedia Britannica, Turing Test.</li>
                    <li>John R. Searle, Minds, Brains and Programs, Behavioral and Brain Sciences (1980).</li>
                </ul>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
        $this->assertSame('body_heading', $result->details['detected_in'] ?? null);
    }

    public function test_a_body_sources_heading_with_institutional_links_passes(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                <h3>Fonti</h3>
                <ul>
                    <li><a href="https://www.acm.org/">ACM</a></li>
                    <li><a href="https://www.computerhistory.org/">Computer History Museum</a></li>
                </ul>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_a_bibliografia_heading_with_citations_passes(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                <h2>Bibliografia</h2>
                <ul>
                    <li>Rossi, M., Introduzione alla relatività, Zanichelli (2015).</li>
                </ul>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_an_english_sources_heading_is_recognized(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                <h3>Sources</h3>
                <ul>
                    <li>Alan M. Turing, Computing Machinery and Intelligence, Mind (1950).</li>
                </ul>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_an_empty_sources_heading_still_warns(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                <h3>Fonti</h3>
                <ul></ul>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_a_sources_heading_followed_by_a_negation_sentence_still_warns(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                <h3>Fonti</h3>
                <p>Nessuna fonte disponibile per questo articolo.</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_the_word_fonti_inside_ordinary_body_text_never_triggers_a_pass(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                <p>Le fonti di questa scoperta non sono ancora state verificate del tutto, ma la comunità scientifica è ottimista.</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_an_article_with_no_sources_anywhere_warns(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso senza alcuna fonte. ', 15).'</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_a_sources_heading_after_several_other_sections_still_passes(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<h2>Introduzione</h2>
                <p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                <h2>Approfondimento</h2>
                <p>'.str_repeat('Ulteriore testo scientifico reale. ', 15).'</p>
                <h3>Fonti</h3>
                <ul>
                    <li>Stanford Encyclopedia of Philosophy, The Turing Test.</li>
                </ul>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_html_entities_and_inline_formatting_inside_sources_still_pass(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                <h3>Fonti&nbsp;</h3>
                <ul>
                    <li>Alan M. Turing, &laquo;<em>Computing Machinery and Intelligence</em>&raquo;, <strong>Mind</strong>, Vol.&nbsp;59 (1950).</li>
                </ul>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    // ── Fonti dopo il delimitatore "---" (seconda convenzione documentata,
    // trovata in review — Codex): le linee guida Redazione istruiscono
    // esplicitamente "Separa le fonti con --- alla fine del testo", e il
    // renderer pubblico (articolo.blade.php) tratta già il testo dopo il
    // primo "---" come fonti a se stanti. Un articolo scritto secondo
    // questa convenzione non ha necessariamente una heading Fonti nel
    // corpo, quindi va rilevato indipendentemente dal rilevamento a heading. ──

    public function test_sources_after_the_delimiter_pass_without_urls_when_multiline(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                ---
                Alan M. Turing, Computing Machinery and Intelligence, Mind, Vol. 59 (1950).
                Stanford Encyclopedia of Philosophy, The Turing Test.',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
        $this->assertSame('body_delimiter', $result->details['detected_in'] ?? null);
    }

    public function test_a_single_line_after_the_delimiter_with_a_url_passes(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                ---
                Fonte: https://www.acm.org/turing-award',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_a_single_narrative_line_after_the_delimiter_still_warns(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                ---
                Nessuna fonte disponibile per questo articolo.',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_an_empty_section_after_the_delimiter_still_warns(): void
    {
        $article = $this->completeArticle([
            'primary_sources' => null,
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p>
                ---
                   ',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    // ── Autore / categoria / pubblicazione ──

    public function test_a_missing_category_fails(): void
    {
        $article = $this->completeArticle(['category' => 'categoria-inesistente-xyz']);

        $result = $this->resultFor($this->checker->check($article), 'category_valid');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_an_overdue_scheduled_article_warns_on_publishing_coherence(): void
    {
        $article = $this->completeArticle([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->subHour(),
        ]);

        $result = $this->resultFor($this->checker->check($article), 'publishing_coherence');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    // ── Readiness ──

    public function test_a_fully_complete_article_is_ready(): void
    {
        $article = $this->completeArticle(['body' => '<p>'.str_repeat('Testo scientifico reale. ', 20).'</p><a href="/articolo/altro">altro articolo</a>']);

        $report = $this->checker->check($article);

        $this->assertSame(EditorialQualityReport::LEVEL_READY, $report->level());
    }

    public function test_an_essential_failure_makes_the_article_incomplete_regardless_of_other_passes(): void
    {
        $article = $this->completeArticle(['cover_image' => null]);

        $report = $this->checker->check($article);

        // cover_present è ESSENTIAL: la sua assenza basta da sola.
        $this->assertSame(EditorialQualityReport::LEVEL_INCOMPLETE, $report->level());
    }

    public function test_only_recommended_warnings_result_in_attention_not_incomplete(): void
    {
        $article = $this->completeArticle(['excerpt' => '']);

        $report = $this->checker->check($article);

        $this->assertSame(EditorialQualityReport::LEVEL_ATTENTION, $report->level());
    }

    public function test_not_applicable_checks_are_excluded_from_the_denominator(): void
    {
        // Articolo minimale (scenario B della missione): draft, quindi
        // SEO/indexability/internal_links/structure sono NOT_APPLICABLE.
        $article = $this->completeArticle(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $report = $this->checker->check($article);

        $this->assertLessThan(count($report->results), $report->applicableCount());
        $this->assertGreaterThan(0, $report->applicableCount());
    }

    public function test_a_minimal_but_valid_draft_never_throws(): void
    {
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Bozza minima',
            'slug' => 'bozza-minima-'.uniqid(),
            'body' => '<p>'.str_repeat('parola ', 60).'</p>',
            'category' => 'spazio',
            'status' => Article::STATUS_DRAFT,
            'published_at' => null,
        ]);

        $report = $this->checker->check($article);

        $this->assertNotEmpty($report->results);
    }
}
