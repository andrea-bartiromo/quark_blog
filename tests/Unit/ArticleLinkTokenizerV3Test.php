<?php

namespace Tests\Unit;

use App\Services\ArticleLinkInsertionService;
use App\Services\ArticleLinkSuggestionService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Tokenizer V3 — normalizzazione Unicode conservativa (missione
 * "CONSOLIDAMENTO INTERNAL LINKING + NEWSLETTER SAFETY + TOKENIZER V3").
 *
 * Due problemi reali, entrambi plausibili da copia-incolla editoriale
 * (Word/Google Docs sostituiscono spesso il trattino ASCII e l'apice
 * dritto con le loro varianti tipografiche):
 *
 *   1) Trattini Unicode (U+2010/2011/2012/2013/2014/2212) NON sono nella
 *      classe di caratteri del tokenizer (solo "-" ASCII, U+002D): un
 *      trattino non-breaking/en-dash/em-dash spezza il token in due
 *      frammenti, entrambi troppo corti per essere tenuti singolarmente
 *      (es. "GPT‑5" -> "GPT" scartato per lunghezza, "5" scartato perché
 *      puramente numerico -> risultato vuoto, il termine sparisce del
 *      tutto).
 *   2) L'apice tipografico "’" (U+2019, quello che Word/Google Docs
 *      inseriscono automaticamente al posto di "'") non è nemmeno lui
 *      nella classe di caratteri: "dell’universo" si spezza in "dell"
 *      (frammento senza senso, ma abbastanza lungo da sopravvivere come
 *      falso termine) e "universo" (per puro caso corretto).
 *
 * Soluzione: normalizzazione DEL TESTO, non del tokenizer — le varianti
 * Unicode vengono ricondotte ai rispettivi caratteri ASCII PRIMA che la
 * regex di tokenizzazione (invariata da #140) veda il testo. Nessuna
 * nuova regola di matching, nessuno stemmer: un preprocessing di
 * normalizzazione puramente ortografico.
 */
class ArticleLinkTokenizerV3Test extends TestCase
{
    private function extractTerms(string $text): array
    {
        $service = new ArticleLinkSuggestionService(app(ArticleLinkInsertionService::class));
        $ref = new ReflectionMethod($service, 'extractTerms');
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, [$text]);
    }

    // ── Trattini Unicode: devono comportarsi come il trattino ASCII ────

    public function test_non_breaking_hyphen_u2011_behaves_like_ascii_hyphen(): void
    {
        $this->assertSame(['gpt-5'], $this->extractTerms("GPT\u{2011}5"));
    }

    public function test_hyphen_u2010_behaves_like_ascii_hyphen(): void
    {
        $this->assertSame(['gpt-5'], $this->extractTerms("GPT\u{2010}5"));
    }

    public function test_en_dash_u2013_behaves_like_ascii_hyphen(): void
    {
        $this->assertSame(['gpt-5'], $this->extractTerms("GPT\u{2013}5"));
    }

    public function test_em_dash_u2014_behaves_like_ascii_hyphen(): void
    {
        $this->assertSame(['gpt-5'], $this->extractTerms("GPT\u{2014}5"));
    }

    public function test_minus_sign_u2212_behaves_like_ascii_hyphen(): void
    {
        $this->assertSame(['sars-cov-2'], $this->extractTerms("SARS\u{2212}CoV\u{2212}2"));
    }

    public function test_wifi_with_non_breaking_hyphen_matches_wifi_with_ascii_hyphen(): void
    {
        $ascii = $this->extractTerms('Wi-Fi 6E');
        $unicode = $this->extractTerms("Wi\u{2011}Fi 6E");

        $this->assertSame($ascii, $unicode);
        $this->assertSame(['wi-fi', '6e'], $unicode);
    }

    public function test_crispr_cas9_with_em_dash_matches_ascii_version(): void
    {
        $ascii = $this->extractTerms('CRISPR-Cas9');
        $unicode = $this->extractTerms("CRISPR\u{2014}Cas9");

        $this->assertSame($ascii, $unicode);
    }

    /**
     * Controllo negativo: un trattino/dash usato come punteggiatura
     * (separato da spazi, come in un inciso) resta un separatore — non
     * deve MAI fondere due parole scorrelate in un unico token, con
     * nessuna delle varianti Unicode più di quanto già non accada con
     * quello ASCII.
     */
    public function test_em_dash_as_punctuation_between_words_never_merges_them(): void
    {
        $terms = $this->extractTerms("Il Sole \u{2014} una stella qualunque \u{2014} è stabile");

        $this->assertNotContains('sole-una', $terms);
        $this->assertContains('sole', $terms);
        $this->assertContains('stella', $terms);
        $this->assertContains('qualunque', $terms);
        $this->assertContains('stabile', $terms);
    }

    // ── Apice tipografico U+2019: deve comportarsi come l'apice ASCII ──

    public function test_typographic_apostrophe_u2019_behaves_like_ascii_apostrophe(): void
    {
        $ascii = $this->extractTerms("dell'universo");
        $typographic = $this->extractTerms("dell\u{2019}universo");

        $this->assertSame($ascii, $typographic);
    }

    public function test_typographic_apostrophe_does_not_leak_a_dell_fragment(): void
    {
        $terms = $this->extractTerms("dell\u{2019}universo");

        $this->assertNotContains('dell', $terms);
    }

    // ── Elisioni italiane: estrarre il sostantivo, non l'intera locuzione ──

    /**
     * Obiettivo: "dell'universo" (fin qui un unico token indivisibile,
     * mai equivalente al sostantivo nudo "universo" scritto altrove) deve
     * ridursi al sostantivo significativo. Solo un elenco chiuso di
     * preposizioni articolate/elisioni italiane comuni viene riconosciuto
     * — mai uno split indiscriminato su ogni apostrofo.
     */
    public function test_elided_preposition_dell_extracts_the_noun(): void
    {
        $this->assertSame(['universo'], $this->extractTerms("dell'universo"));
    }

    public function test_elided_preposition_nell_extracts_the_noun(): void
    {
        $this->assertSame(['universo'], $this->extractTerms("nell'universo"));
    }

    public function test_elided_preposition_all_extracts_the_noun(): void
    {
        $this->assertSame(['universo'], $this->extractTerms("all'universo"));
    }

    public function test_elided_preposition_sull_extracts_the_noun(): void
    {
        $this->assertSame(['universo'], $this->extractTerms("sull'universo"));
    }

    public function test_elided_preposition_dall_extracts_the_noun(): void
    {
        $this->assertSame(['universo'], $this->extractTerms("dall'universo"));
    }

    public function test_elided_article_l_extracts_the_noun(): void
    {
        $this->assertSame(['intelligenza'], $this->extractTerms("l'intelligenza"));
    }

    public function test_elided_article_un_extracts_the_noun(): void
    {
        $this->assertSame(['intelligenza'], $this->extractTerms("un'intelligenza"));
    }

    public function test_elided_universo_family_all_reduce_to_the_same_term(): void
    {
        $variants = ["l'universo", "dell'universo", "nell'universo", "all'universo", "sull'universo", "dall'universo"];

        foreach ($variants as $variant) {
            $this->assertSame(['universo'], $this->extractTerms($variant), "Variante fallita: {$variant}");
        }
    }

    public function test_elided_form_now_matches_the_bare_noun_written_elsewhere(): void
    {
        // Il vero obiettivo editoriale: un articolo che scrive "l'universo"
        // e uno che scrive "universo" (senza articolo, es. a inizio
        // titolo: "Universo in espansione") devono condividere lo stesso
        // termine ai fini del matching — prima di questo fix, mai.
        $this->assertSame($this->extractTerms('universo'), $this->extractTerms("dell'universo"));
    }

    public function test_typographic_apostrophe_elision_also_reduces_to_the_noun(): void
    {
        $this->assertSame(['universo'], $this->extractTerms("dell\u{2019}universo"));
    }

    // ── Controlli negativi: NON deve degenerare in uno split indiscriminato ──

    public function test_short_remainder_after_elision_is_still_dropped_by_length_rules(): void
    {
        // "un'AI" -> rimane "ai" dopo lo strip di "un'": 2 lettere, nessuna
        // cifra, sotto MIN_TERM_LENGTH=4 -> scartato, stesso comportamento
        // di qualunque altra parola troppo corta. Non un'eccezione per gli
        // acronimi (quella è FASE 6, separata e non decisa qui).
        $this->assertSame([], $this->extractTerms("un'AI"));
    }

    public function test_elided_stopword_remainder_is_still_filtered_as_stopword(): void
    {
        // "c'era" -> "era" dopo lo strip di "c'": "era" e' gia' in
        // STOPWORDS (verbo ausiliare) -> scartato comunque.
        $this->assertSame([], $this->extractTerms("c'era"));
    }

    public function test_apostrophe_not_matching_a_known_elision_prefix_is_left_untouched(): void
    {
        // Nessuna preposizione articolata italiana nota inizia per "z'" o
        // "x'": un ipotetico identificatore tecnico con apostrofo interno
        // non riconosciuto resta intero, non viene spezzato a caso.
        $terms = $this->extractTerms("zx'test");

        $this->assertSame(["zx'test"], $terms);
    }

    public function test_wifi_and_gpt_identifiers_are_never_affected_by_elision_stripping(): void
    {
        $this->assertSame(['wi-fi'], $this->extractTerms('Wi-Fi'));
        $this->assertSame(['gpt-5'], $this->extractTerms('GPT-5'));
        $this->assertSame(['crispr-cas9'], $this->extractTerms('CRISPR-Cas9'));
    }

    public function test_word_starting_with_letters_that_happen_to_prefix_an_elision_but_no_apostrophe_is_untouched(): void
    {
        // Parole che iniziano per "un"/"l"/"d"/... ma SENZA apostrofo dopo
        // il prefisso: nessuna elisione da riconoscere, il tokenizer non
        // le deve toccare. ("della" è comunque già una stopword di suo:
        // usata qui solo per verificare che resti [] come sempre, non che
        // venga alterata dalla nuova regola.)
        $this->assertSame(['unita'], $this->extractTerms('unita'));
        $this->assertSame([], $this->extractTerms('della'));
        $this->assertSame(['luce'], $this->extractTerms('luce'));
    }

    // ── Acronimi scientifici corti (2-3 lettere) ────────────────────
    //
    // MIN_TERM_LENGTH=4 scarta DNA/RNA/ESA/AI/ML/EU/ISS — identificatori
    // specifici, non parole generiche. Regola: un token composto SOLO da
    // lettere (nessuna cifra/trattino/apostrofo — quelli hanno già
    // MIN_ALNUM_TERM_LENGTH=2), lungo 2-4 caratteri, ammesso SOLO se
    // scritto INTERAMENTE in maiuscolo nel testo originale (prima
    // dell'abbassamento di case, che ora avviene per-token proprio per
    // rendere possibile questo controllo). Un pattern che in prosa
    // italiana editoriale corrente non si presenta mai per una parola
    // comune — "il", "di", "la" non compaiono mai TUTTE MAIUSCOLE in un
    // testo scritto normalmente. STOPWORDS e segnale di document-frequency
    // restano comunque applicati sopra, invariati.

    public function test_short_uppercase_acronyms_are_recognized(): void
    {
        $this->assertSame(['dna'], $this->extractTerms('DNA'));
        $this->assertSame(['rna'], $this->extractTerms('RNA'));
        $this->assertSame(['esa'], $this->extractTerms('ESA'));
        $this->assertSame(['ml'], $this->extractTerms('ML'));
        $this->assertSame(['eu'], $this->extractTerms('EU'));
        $this->assertSame(['iss'], $this->extractTerms('ISS'));
    }

    /**
     * "AI" collide con "ai" (preposizione articolata "a"+"i"), già una
     * STOPWORD prima di questa missione — deliberatamente ESCLUSA
     * dall'allowlist degli acronimi corti invece di un'eccezione ad hoc:
     * vedi ArticleLinkSuggestionService::SHORT_ACRONYM_ALLOWLIST. Limite
     * noto e documentato, non un difetto: "Intelligenza Artificiale" resta
     * comunque riconoscibile tramite la parola per intero, già sopra
     * MIN_TERM_LENGTH in qualunque testo reale ("intelligenza").
     */
    public function test_ai_acronym_is_deliberately_not_recognized_due_to_stopword_collision(): void
    {
        $this->assertSame([], $this->extractTerms('AI'));
    }

    public function test_short_uppercase_acronym_in_a_real_sentence_is_recognized(): void
    {
        $terms = $this->extractTerms('Il DNA contiene informazioni genetiche fondamentali.');

        $this->assertContains('dna', $terms);
    }

    public function test_lowercase_or_titlecase_version_of_the_same_letters_is_not_treated_as_an_acronym(): void
    {
        // Fuori dal pattern "tutto maiuscolo nel testo originale": una
        // parola scritta normalmente (minuscolo o solo iniziale
        // maiuscola) non diventa magicamente un acronimo solo perché è
        // corta — resta soggetta alle regole di lunghezza ordinarie.
        $this->assertSame([], $this->extractTerms('dna'));
        $this->assertSame([], $this->extractTerms('Dna'));
        $this->assertSame([], $this->extractTerms('ai'));
        $this->assertSame([], $this->extractTerms('Ai'));
    }

    #[DataProvider('commonShortWordsThatMustNeverBecomeKeywordsProvider')]
    public function test_common_short_words_never_become_keywords_even_if_someone_writes_them_uppercase(string $word): void
    {
        // Negativi deliberatamente numerosi: qualunque parola/preposizione/
        // pronome italiano comune di 2-3 lettere, ANCHE se scritta tutta
        // maiuscola (es. per enfasi in un titolo), non deve mai diventare
        // una keyword — la regola dell'acronimo non è "2-3 lettere
        // maiuscole", è "2-3 lettere maiuscole E non è una stopword".
        $this->assertSame([], $this->extractTerms(mb_strtoupper($word)), "Fallito per: {$word}");
    }

    public static function commonShortWordsThatMustNeverBecomeKeywordsProvider(): array
    {
        return array_map(fn (string $w) => [$w], [
            'il', 'lo', 'la', 'un', 'del', 'chi', 'che', 'con', 'per', 'tra', 'fra',
            'non', 'piu', 'gia', 'qui', 'qua', 'ora', 'poi', 'ieri', 'noi', 'voi', 'lui', 'lei',
            'sua', 'suo', 'tua', 'tuo', 'mia', 'mio', 'sta', 'fa', 'ha',
        ]);
    }

    public function test_five_letter_uppercase_word_is_not_treated_as_a_short_acronym_exception(): void
    {
        // La regola copre 2-4 lettere: da 5 in su non serve un'eccezione,
        // MIN_TERM_LENGTH=4 le ammette già normalmente indipendentemente
        // dal case (vedi "nasa" -> sopravvive già oggi).
        $this->assertSame(['nasa'], $this->extractTerms('NASA'));
    }

    public function test_acronym_immediately_followed_by_lowercase_word_is_still_recognized(): void
    {
        $terms = $this->extractTerms('La ISS orbita a bassa quota.');

        $this->assertContains('iss', $terms);
    }

    public function test_mixed_case_short_word_is_never_treated_as_an_acronym(): void
    {
        // "AiA" o simili grafie miste non sono "tutto maiuscolo": nessuna
        // eccezione, restano soggette a MIN_TERM_LENGTH ordinario.
        $this->assertSame([], $this->extractTerms('AiA'));
    }
}
