<?php

namespace Tests\Unit;

use App\Services\ArticleLinkInsertionService;
use App\Services\ArticleLinkSuggestionService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Batteria di caratterizzazione/regressione per
 * ArticleLinkSuggestionService::extractTerms() — SOLA tokenizzazione,
 * nessuna modifica a scoring/soglia/anchor ranking.
 *
 * extractTerms() è privato e puramente funzionale (stringa in, array di
 * termini out, nessun side-effect): invocato via reflection invece che
 * attraverso l'intera pipeline di analyzeForSource(), per isolare
 * esattamente il comportamento sotto test senza costruire coppie di
 * Article per ciascuno dei ~30 casi.
 */
class ArticleLinkTokenizerCharacterizationTest extends TestCase
{
    private function extractTerms(string $text): array
    {
        $service = new ArticleLinkSuggestionService(new ArticleLinkInsertionService);

        $method = new ReflectionMethod(ArticleLinkSuggestionService::class, 'extractTerms');
        $method->setAccessible(true);

        return $method->invoke($service, $text);
    }

    // ── Termini scientifici/tecnologici alfanumerici (bug da correggere) ──

    public function test_gpt_5_is_preserved_intact(): void
    {
        $this->assertContains('gpt-5', $this->extractTerms('GPT-5'));
    }

    public function test_gpt_4o_is_preserved_intact(): void
    {
        $this->assertContains('gpt-4o', $this->extractTerms('GPT-4o'));
    }

    public function test_h2o_is_not_lost_entirely(): void
    {
        $this->assertContains('h2o', $this->extractTerms('H2O'));
    }

    public function test_co2_is_not_lost_entirely(): void
    {
        $this->assertContains('co2', $this->extractTerms('CO2'));
    }

    public function test_5g_is_not_lost_entirely(): void
    {
        $this->assertContains('5g', $this->extractTerms('5G'));
    }

    public function test_covid_19_is_preserved_intact(): void
    {
        $this->assertContains('covid-19', $this->extractTerms('COVID-19'));
    }

    public function test_sars_cov_2_is_preserved_intact(): void
    {
        $this->assertContains('sars-cov-2', $this->extractTerms('SARS-CoV-2'));
    }

    public function test_crispr_cas9_is_preserved_intact(): void
    {
        $this->assertContains('crispr-cas9', $this->extractTerms('CRISPR-Cas9'));
    }

    public function test_crispr_cas9_and_cas12_are_distinguished_not_collapsed(): void
    {
        $terms9 = $this->extractTerms('CRISPR-Cas9');
        $terms12 = $this->extractTerms('CRISPR-Cas12');

        $this->assertContains('crispr-cas9', $terms9);
        $this->assertContains('crispr-cas12', $terms12);
        $this->assertNotSame($terms9, $terms12);
    }

    public function test_wifi_6_anchor_and_isolated_number(): void
    {
        $terms = $this->extractTerms('Wi-Fi 6');
        $this->assertContains('wi-fi', $terms);
    }

    public function test_wifi_6e_is_preserved_intact_as_its_own_variant(): void
    {
        $terms = $this->extractTerms('Wi-Fi 6E');
        $this->assertContains('wi-fi', $terms);
        // "6E" ha una lettera: alfanumerico valido, distinto da "wi-fi".
        $this->assertContains('6e', $terms);
    }

    // ── Numeri isolati: MAI parole chiave (invariante da preservare) ──

    public function test_a_bare_isolated_number_is_never_a_keyword(): void
    {
        $this->assertEmpty($this->extractTerms('11'));
        $this->assertEmpty($this->extractTerms('2026'));
    }

    public function test_apollo_11_keeps_apollo_but_never_promotes_the_bare_number(): void
    {
        $terms = $this->extractTerms('Apollo 11');
        $this->assertContains('apollo', $terms);
        $this->assertNotContains('11', $terms);
    }

    public function test_common_numbers_in_a_sentence_never_become_keywords(): void
    {
        $terms = $this->extractTerms('Nel 2026 abbiamo pubblicato 15 articoli in 3 categorie');
        $this->assertNotContains('2026', $terms);
        $this->assertNotContains('15', $terms);
        $this->assertNotContains('3', $terms);
    }

    // ── Apostrofi italiani (regressione — non deve rompersi) ──

    public function test_apostrophe_after_article_is_preserved(): void
    {
        $this->assertContains("l'informazione", $this->extractTerms("l'informazione"));
    }

    public function test_apostrophe_with_preposition_dell_is_preserved(): void
    {
        $this->assertContains("dell'intelligenza", $this->extractTerms("dell'intelligenza artificiale"));
    }

    // ── Trattini (regressione) ──

    public function test_compound_hyphenated_word_is_preserved(): void
    {
        $this->assertContains('stato-nazione', $this->extractTerms('stato-nazione'));
    }

    public function test_ligo_virgo_kagra_multi_hyphen_compound_is_preserved(): void
    {
        $this->assertContains('ligo-virgo-kagra', $this->extractTerms('LIGO-Virgo-KAGRA'));
    }

    // ── Lettere accentate / Unicode (regressione) ──

    public function test_accented_word_is_preserved_with_accent(): void
    {
        $this->assertContains('città', $this->extractTerms('città'));
    }

    public function test_perche_with_accent_is_still_filtered_as_stopword(): void
    {
        // "perché" e' stopword (forma non accentata "perche" nella lista):
        // stripAccents() e' usato solo per il confronto, il match deve
        // funzionare anche sulla forma accentata originale nel testo.
        $this->assertNotContains('perché', $this->extractTerms('Perché il Sole non esplode?'));
    }

    // ── Maiuscole/minuscole (regressione) ──

    public function test_terms_are_normalized_to_lowercase(): void
    {
        $terms = $this->extractTerms('BETELGEUSE Betelgeuse betelgeuse');
        $this->assertSame(['betelgeuse'], $terms);
    }

    // ── Punteggiatura / confini di parola (regressione) ──

    public function test_trailing_punctuation_does_not_pollute_the_term(): void
    {
        foreach (['GPT-5)', 'GPT-5,', 'GPT-5.', 'GPT-5;', 'GPT-5!', 'GPT-5?'] as $case) {
            $this->assertContains('gpt-5', $this->extractTerms($case), "Fallito per: {$case}");
            $this->assertNotContains('gpt-5)', $this->extractTerms($case));
        }
    }

    public function test_leading_punctuation_does_not_pollute_the_term(): void
    {
        $this->assertContains('h2o', $this->extractTerms('("H2O")'));
    }

    // ── Simboli adiacenti (regressione) ──

    public function test_percentage_sign_does_not_attach_to_the_number_or_leak_a_keyword(): void
    {
        $terms = $this->extractTerms('sconto del 20%');
        $this->assertContains('sconto', $terms);
        $this->assertNotContains('20', $terms);
        $this->assertNotContains('20%', $terms);
    }

    public function test_email_style_hyphenation_is_preserved_as_a_single_term(): void
    {
        $this->assertContains('e-mail', $this->extractTerms('e-mail'));
    }

    // ── Termini normali già supportati (regressione, nessun impatto atteso) ──

    public function test_a_normal_multi_word_sentence_extracts_the_expected_significant_terms(): void
    {
        $terms = $this->extractTerms('Il nuovo osservatorio astronomico rileva onde gravitazionali');

        $this->assertContains('nuovo', $terms);
        $this->assertContains('osservatorio', $terms);
        $this->assertContains('astronomico', $terms);
        $this->assertContains('rileva', $terms);
        $this->assertContains('onde', $terms);
        $this->assertContains('gravitazionali', $terms);
    }

    public function test_short_alpha_words_below_min_length_are_still_excluded(): void
    {
        // "eco" (3 lettere, nessuna cifra) resta sotto soglia: invariante
        // preservato, il minimo di 4 per token puramente alfabetici non
        // cambia con questa correzione.
        $this->assertNotContains('eco', $this->extractTerms('eco'));
    }

    public function test_generic_stopwords_from_the_quality_audit_remain_filtered(): void
    {
        $terms = $this->extractTerms('Potrebbe significa che sta accadendo qualcosa di importante');
        $this->assertNotContains('potrebbe', $terms);
        $this->assertNotContains('significa', $terms);
        $this->assertNotContains('importante', $terms);
    }

    public function test_generic_mente_adverb_remains_filtered(): void
    {
        $this->assertNotContains('profondamente', $this->extractTerms('Cambia profondamente il modo di vedere'));
    }

    public function test_exoplanet_toi_700_d_case(): void
    {
        $terms = $this->extractTerms('exoplanet TOI-700 d');
        $this->assertContains('exoplanet', $terms);
        $this->assertContains('toi-700', $terms);
    }
}
