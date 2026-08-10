<?php

namespace App\Services\InternalLinking;

/**
 * Riconosce concetti scientifici multi-parola (config/scientific_concepts.php)
 * in un testo semplice — layer aggiuntivo rispetto al tokenizer a singola
 * parola già esistente in App\Services\ArticleLinkSuggestionService (#140,
 * #142), che non può riconoscere "buco nero" come UNA unità (la vedrebbe
 * sempre come i due termini indipendenti "buco" e "nero").
 *
 * Nessun NLP, nessun embedding: ricerca di frase letterale (stesso
 * approccio già usato per il match del titolo, findPhrase() in
 * ArticleLinkSuggestionService), estesa con un controllo di confine di
 * parola — un alias non può mai combaciare con una sotto-stringa dentro
 * una parola più lunga (vedi findWordBoundaryPhrase()).
 *
 * LONGEST MATCH FIRST (FASE 5): se più alias si sovrappongono nello stesso
 * testo, viene tenuto solo il più lungo — un intervallo di testo già
 * "reclamato" da un match non può essere ri-consumato da un alias più
 * corto. Con il registro attuale (nessun alias è sottostringa letterale di
 * un altro, per costruzione) questo non ha mai effetto pratico oggi, ma
 * rende l'algoritmo corretto per qualunque registro futuro più ricco senza
 * doverlo riscrivere.
 */
class ScientificConceptMatcher
{
    /**
     * @return array<int, ConceptCandidate> in ordine di posizione nel testo
     */
    public function conceptsPresentIn(string $plainText): array
    {
        if (trim($plainText) === '') {
            return [];
        }

        $found = [];
        $claimedRanges = []; // array<int, array{0:int,1:int}> posizioni [start, end) già reclamate

        foreach ($this->aliasesLongestFirst() as [$canonical, $alias, $wordCount]) {
            $offset = 0;

            while (($occurrence = $this->findWordBoundaryPhrase($plainText, $alias, $offset)) !== null) {
                $start = $occurrence['position'];
                $end = $start + mb_strlen($occurrence['text'], 'UTF-8');
                $offset = $start + 1;

                if ($this->overlapsClaimedRange($claimedRanges, $start, $end)) {
                    continue;
                }

                $claimedRanges[] = [$start, $end];

                $found[] = new ConceptCandidate(
                    canonicalTerm: $canonical,
                    matchedText: $occurrence['text'],
                    position: $start,
                    wordCount: $wordCount,
                );
            }
        }

        usort($found, fn (ConceptCandidate $a, ConceptCandidate $b) => $a->position <=> $b->position);

        return $found;
    }

    /**
     * @return array<int, string> le forme canoniche (uniche) dei concetti trovati nel testo
     */
    public function canonicalTermsPresentIn(string $plainText): array
    {
        return array_values(array_unique(array_map(
            fn (ConceptCandidate $c) => $c->canonicalTerm,
            $this->conceptsPresentIn($plainText)
        )));
    }

    /**
     * Elenco (canonical, alias, wordCount) dal config, ordinato per numero
     * di parole decrescente — garantisce che un alias più lungo/specifico
     * venga cercato e reclamato prima di uno più corto (FASE 5).
     *
     * @return array<int, array{0:string,1:string,2:int}>
     */
    private function aliasesLongestFirst(): array
    {
        $entries = [];

        foreach (config('scientific_concepts.concepts', []) as $concept) {
            foreach ($concept['aliases'] as $alias) {
                $entries[] = [$concept['canonical'], $alias, count(preg_split('/\s+/u', trim($alias)))];
            }
        }

        usort($entries, fn (array $a, array $b) => $b[2] <=> $a[2]);

        return $entries;
    }

    private function overlapsClaimedRange(array $claimedRanges, int $start, int $end): bool
    {
        foreach ($claimedRanges as [$claimedStart, $claimedEnd]) {
            if ($start < $claimedEnd && $end > $claimedStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * Come ArticleLinkSuggestionService::findPhrase(), ma con un controllo
     * aggiuntivo di confine di parola: un carattere alfanumerico Unicode
     * immediatamente prima o dopo la frase trovata la scarta (non è un
     * confine di parola valido — evita che un alias combaci con una
     * sotto-stringa dentro una parola più lunga). Case-insensitive, come
     * il resto della pipeline di matching esistente.
     *
     * @return array{position:int,text:string}|null
     */
    private function findWordBoundaryPhrase(string $haystack, string $phrase, int $offset = 0): ?array
    {
        $phrase = trim($phrase);

        if ($phrase === '') {
            return null;
        }

        $haystackLength = mb_strlen($haystack, 'UTF-8');
        $phraseLength = mb_strlen($phrase, 'UTF-8');

        while ($offset <= $haystackLength - $phraseLength) {
            $position = mb_stripos($haystack, $phrase, $offset, 'UTF-8');

            if ($position === false) {
                return null;
            }

            $before = $position > 0 ? mb_substr($haystack, $position - 1, 1, 'UTF-8') : '';
            $after = mb_substr($haystack, $position + $phraseLength, 1, 'UTF-8');

            $boundaryOk = preg_match('/[\p{L}\p{N}]/u', $before) !== 1
                && preg_match('/[\p{L}\p{N}]/u', $after) !== 1;

            if ($boundaryOk) {
                return [
                    'position' => $position,
                    'text' => mb_substr($haystack, $position, $phraseLength, 'UTF-8'),
                ];
            }

            $offset = $position + 1;
        }

        return null;
    }
}
