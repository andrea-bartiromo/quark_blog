<?php

namespace Tests\Unit\InternalLinking;

use App\Services\InternalLinking\ScientificConceptMatcher;
use Tests\TestCase;

class ScientificConceptMatcherTest extends TestCase
{
    private ScientificConceptMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new ScientificConceptMatcher;
    }

    public function test_a_known_multi_word_concept_is_recognized(): void
    {
        $found = $this->matcher->conceptsPresentIn('Gli astronomi hanno osservato un buco nero al centro della galassia.');

        $this->assertCount(1, $found);
        $this->assertSame('buco nero', $found[0]->canonicalTerm);
        $this->assertSame('buco nero', $found[0]->matchedText);
    }

    public function test_a_plural_alias_resolves_to_the_same_canonical_term(): void
    {
        $found = $this->matcher->conceptsPresentIn('Molti buchi neri sono stati catalogati.');

        $this->assertCount(1, $found);
        $this->assertSame('buco nero', $found[0]->canonicalTerm);
        $this->assertSame('buchi neri', $found[0]->matchedText);
    }

    public function test_a_concept_is_never_matched_as_a_substring_inside_a_longer_word(): void
    {
        // "rete" da solo non deve mai far scattare "rete neurale" se il
        // testo dice davvero un'altra cosa, e nessun alias multiparola può
        // combaciare con una sotto-stringa interna a una parola diversa.
        $found = $this->matcher->conceptsPresentIn('La retenzione idrica non è un concetto neurale.');

        $this->assertSame([], $found);
    }

    /**
     * Il confine di parola si applica anche ai bordi ESTERNI della frase
     * multi-parola, non solo al suo interno: "xbuco nero" o "buco nerox"
     * (l'alias attaccato senza spazio a un altro carattere alfanumerico)
     * non sono un'occorrenza valida del concetto.
     */
    public function test_a_concept_glued_to_another_alphanumeric_character_at_either_edge_is_not_matched(): void
    {
        $this->assertSame([], $this->matcher->conceptsPresentIn('Un xbuco nero non esiste.'));
        $this->assertSame([], $this->matcher->conceptsPresentIn('Un buco nerox non esiste.'));
    }

    public function test_no_concept_found_returns_an_empty_array(): void
    {
        $this->assertSame([], $this->matcher->conceptsPresentIn('Un testo qualunque senza alcun concetto scientifico noto.'));
    }

    public function test_empty_text_returns_an_empty_array(): void
    {
        $this->assertSame([], $this->matcher->conceptsPresentIn(''));
    }

    public function test_matching_is_case_insensitive(): void
    {
        $found = $this->matcher->conceptsPresentIn('La RELATIVITÀ GENERALE di Einstein.');

        $this->assertCount(1, $found);
        $this->assertSame('relatività generale', $found[0]->canonicalTerm);
    }

    /**
     * FASE 5 — longest match first: nessun alias è oggi una sotto-stringa
     * letterale di un altro, ma il registro potrebbe crescere in futuro.
     * Un match più lungo reclama il suo intervallo di testo, impedendo a
     * un alias più corto di ri-consumare la stessa porzione — verificato
     * qui indirettamente controllando che due concetti realmente distinti
     * e non sovrapposti nello stesso testo vengano entrambi trovati, in
     * ordine di posizione.
     */
    public function test_two_distinct_concepts_in_the_same_text_are_both_found_in_order(): void
    {
        $found = $this->matcher->conceptsPresentIn(
            'La relatività generale spiega la gravità; le onde gravitazionali ne sono una conseguenza diretta.'
        );

        $this->assertCount(2, $found);
        $this->assertSame('relatività generale', $found[0]->canonicalTerm);
        $this->assertSame('onde gravitazionali', $found[1]->canonicalTerm);
        $this->assertLessThan($found[1]->position, $found[0]->position);
    }

    public function test_canonical_terms_present_in_deduplicates_repeated_mentions(): void
    {
        $canonical = $this->matcher->canonicalTermsPresentIn(
            'Un buco nero è affascinante. Gli scienziati studiano ogni buco nero conosciuto.'
        );

        $this->assertSame(['buco nero'], $canonical);
    }

    public function test_matched_text_is_verbatim_from_the_source_not_the_canonical_form(): void
    {
        $found = $this->matcher->conceptsPresentIn('Gli orologi atomici a bordo dei satelliti GPS vanno corretti.');

        $this->assertSame('orologio atomico', $found[0]->canonicalTerm);
        $this->assertSame('orologi atomici', $found[0]->matchedText);
    }
}
