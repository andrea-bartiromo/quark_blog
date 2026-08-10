<?php

namespace App\Services\InternalLinking;

/**
 * Un concetto riconosciuto in un testo — una singola occorrenza trovata da
 * ScientificConceptMatcher. Non un record persistito, non un'entità di
 * dominio: un DTO in-memory, pensato per restare riutilizzabile se in
 * futuro un vero registro Concetti/Alias (Content Graph) sostituirà
 * config/scientific_concepts.php come sorgente — la forma resta la stessa,
 * cambia solo da dove viene popolata (vedi FASE 20/Content Graph readiness
 * in docs/INTERNAL_LINKING_V2.md).
 */
final readonly class ConceptCandidate
{
    public function __construct(
        /** Forma di riferimento del concetto (es. "buco nero") — usata per confrontare se due occorrenze in testi diversi indicano lo stesso concetto. */
        public string $canonicalTerm,
        /** Il testo VERBATIM trovato nel testo analizzato (es. "buchi neri") — mai il canonical, sempre ciò che compare davvero, perché deve poter diventare un'anchor letterale. */
        public string $matchedText,
        /** Posizione (in caratteri, mb_*) del match nel testo analizzato. */
        public int $position,
        /** Quante "parole" compone il concetto — usato come proxy di specificità: una frase di 2-3 parole è un segnale più forte di una singola parola generica. */
        public int $wordCount,
        /** Da dove viene il concetto: 'config' per il registro statico attuale — lascia spazio a una futura origine 'content_graph' senza cambiare la forma del DTO. */
        public string $source = 'config',
    ) {}
}
