<?php

namespace Tests\Unit\Search;

use App\Services\Search\SearchTokenizer;
use PHPUnit\Framework\TestCase;

class SearchTokenizerTest extends TestCase
{
    private SearchTokenizer $tokenizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenizer = new SearchTokenizer;
    }

    // 1. Multi-token: "test turing" produce due termini distinti, non una frase indivisibile
    public function test_it_splits_a_multi_word_query_into_separate_tokens(): void
    {
        $this->assertSame(['test', 'turing'], $this->tokenizer->tokenize('test turing'));
    }

    // 2. Ordine dei token differente: stesso multiset, indipendentemente dall'ordine digitato
    public function test_token_order_does_not_change_the_resulting_set(): void
    {
        $forward = $this->tokenizer->tokenize('test turing');
        $backward = $this->tokenizer->tokenize('turing test');

        sort($forward);
        sort($backward);

        $this->assertSame($forward, $backward);
    }

    // 3. Maiuscole/minuscole equivalenti
    public function test_it_is_case_insensitive(): void
    {
        $this->assertSame(['turing'], $this->tokenizer->tokenize('TURING'));
        $this->assertSame(['turing'], $this->tokenizer->tokenize('turing'));
        $this->assertSame(['turing'], $this->tokenizer->tokenize('TuRiNg'));
    }

    // 4. Punteggiatura comune non spezza né corrompe la tokenizzazione
    public function test_common_punctuation_does_not_break_tokenization(): void
    {
        $this->assertSame(['test', 'turing'], $this->tokenizer->tokenize('Test, Turing!'));
        $this->assertSame(['test', 'turing'], $this->tokenizer->tokenize('"Test" — Turing?'));
    }

    // 5. Spazi multipli e trim
    public function test_it_collapses_extra_whitespace(): void
    {
        $this->assertSame(['test', 'turing'], $this->tokenizer->tokenize('   test    turing   '));
    }

    // 6. Query vuota
    public function test_empty_query_produces_no_tokens(): void
    {
        $this->assertSame([], $this->tokenizer->tokenize(''));
        $this->assertSame([], $this->tokenizer->tokenize('   '));
    }

    // 7. Query di sola punteggiatura non produce token (mai una scansione indiscriminata a valle)
    public function test_punctuation_only_query_produces_no_tokens(): void
    {
        $this->assertSame([], $this->tokenizer->tokenize('!!!'));
        $this->assertSame([], $this->tokenizer->tokenize('???...'));
    }

    // 8. Wildcard SQL isolati non sopravvivono come token (mai un carattere jolly incontrollato)
    public function test_bare_wildcard_characters_produce_no_tokens(): void
    {
        $this->assertSame([], $this->tokenizer->tokenize('%'));
        $this->assertSame([], $this->tokenizer->tokenize('_'));
        $this->assertSame([], $this->tokenizer->tokenize('%_%'));
    }

    // 9. Un wildcard embedded resta solo un separatore, mai parte di un token
    public function test_embedded_wildcard_characters_only_separate_tokens(): void
    {
        $this->assertSame(['caffe', 'latte'], $this->tokenizer->tokenize('caffe%latte'));
        $this->assertSame(['caffe', 'latte'], $this->tokenizer->tokenize('caffe_latte'));
    }

    // 10. Duplicati deduplicati
    public function test_duplicate_tokens_are_deduplicated(): void
    {
        $this->assertSame(['test', 'turing'], $this->tokenizer->tokenize('test test turing test'));
    }

    // 11. Identificatori alfanumerici/con trattino preservati come un unico token
    public function test_it_preserves_alphanumeric_and_hyphenated_identifiers(): void
    {
        $this->assertSame(['chatgpt'], $this->tokenizer->tokenize('ChatGPT'));
        $this->assertSame(['wi-fi'], $this->tokenizer->tokenize('Wi-Fi'));
        $this->assertSame(['church-turing'], $this->tokenizer->tokenize('Church-Turing'));
        $this->assertSame(['gpt-5'], $this->tokenizer->tokenize('GPT-5'));
        $this->assertSame(['bletchley', 'park'], $this->tokenizer->tokenize('Bletchley Park'));
        $this->assertSame(['betelgeuse'], $this->tokenizer->tokenize('Betelgeuse'));
        $this->assertSame(['transformer'], $this->tokenizer->tokenize('transformer'));
    }

    // 12. Un token di un solo carattere è sempre escluso (politica conservativa)
    public function test_single_character_tokens_are_excluded(): void
    {
        $this->assertSame([], $this->tokenizer->tokenize('a'));
        $this->assertSame([], $this->tokenizer->tokenize('x'));
    }

    // 13. Un token di due caratteri significativo resta valido ("IA")
    public function test_two_character_tokens_remain_valid(): void
    {
        $this->assertSame(['ia'], $this->tokenizer->tokenize('IA'));
    }

    // 14. Stopword italiane comuni filtrate
    public function test_common_italian_stopwords_are_filtered(): void
    {
        $this->assertSame(['test'], $this->tokenizer->tokenize('il test'));
        $this->assertSame(['turing', 'test'], $this->tokenizer->tokenize('il turing e il test'));
    }

    // 15. Elisione italiana ridotta al sostantivo
    public function test_italian_elision_is_reduced_to_the_noun(): void
    {
        $this->assertSame(['universo'], $this->tokenizer->tokenize("l'universo"));
    }

    // 16. Token puramente numerico escluso (mai un segnale tematico isolato)
    public function test_purely_numeric_tokens_are_excluded(): void
    {
        $this->assertSame([], $this->tokenizer->tokenize('2026'));
        $this->assertSame(['covid-19'], $this->tokenizer->tokenize('covid-19'));
    }

    // 17. Limite conservativo al numero di token significativi
    public function test_it_caps_the_number_of_significant_tokens(): void
    {
        $words = ['rosso', 'verde', 'blu', 'giallo', 'viola', 'arancione', 'marrone', 'grigio', 'nero', 'bianco'];

        $tokens = $this->tokenizer->tokenize(implode(' ', $words));

        $this->assertCount(SearchTokenizer::MAX_SIGNIFICANT_TOKENS, $tokens);
        $this->assertSame(array_slice($words, 0, SearchTokenizer::MAX_SIGNIFICANT_TOKENS), $tokens);
    }

    // 18. Variante morfologica stella/stelle (bidirezionale)
    public function test_morphological_variant_for_stella_stelle(): void
    {
        $this->assertSame('stelle', $this->tokenizer->morphologicalVariant('stella'));
        $this->assertSame('stella', $this->tokenizer->morphologicalVariant('stelle'));
    }

    // 19. Variante morfologica albero/alberi (bidirezionale)
    public function test_morphological_variant_for_albero_alberi(): void
    {
        $this->assertSame('alberi', $this->tokenizer->morphologicalVariant('albero'));
        $this->assertSame('albero', $this->tokenizer->morphologicalVariant('alberi'));
    }

    // 20. Nessuna variante per parole troppo corte o senza suffisso coperto
    public function test_no_morphological_variant_for_short_or_unmatched_words(): void
    {
        $this->assertNull($this->tokenizer->morphologicalVariant('ia'));
        $this->assertNull($this->tokenizer->morphologicalVariant('turing'));
        $this->assertNull($this->tokenizer->morphologicalVariant('chatgpt'));
        $this->assertNull($this->tokenizer->morphologicalVariant('test'));
    }
}
