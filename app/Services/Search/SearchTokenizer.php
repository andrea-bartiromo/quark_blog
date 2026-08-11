<?php

namespace App\Services\Search;

use Illuminate\Support\Str;

/**
 * Search V1 — trasforma una query utente in termini significativi
 * normalizzati, indipendente dal tokenizer V3 di
 * App\Services\ArticleLinkSuggestionService (dominio diverso: quello
 * confronta due testi editoriali tra loro con soglie/stopword pensate per
 * quel matching, questo interpreta una query breve digitata da un lettore
 * — stessa idea di fondo, implementazione volutamente separata per non
 * accoppiare i due domini).
 *
 * Deterministico, nessuno stemming linguistico generale: solo una
 * variante morfologica MOLTO conservativa (vedi morphologicalVariant()).
 */
class SearchTokenizer
{
    /**
     * Un token di 1 solo carattere non è mai un segnale di ricerca
     * utilizzabile (rumore, quasi sempre una preposizione/congiunzione
     * tronca o un refuso) — politica conservativa esplicitamente richiesta
     * per i token di un solo carattere. Un token di 2 caratteri (es. "IA")
     * resta invece valido: non tutte le sigle corte sono trascurabili.
     */
    private const MIN_TOKEN_LENGTH = 2;

    /**
     * Limite conservativo al numero di termini significativi elaborati per
     * query: una query anomala/molto lunga non deve generare una clausola
     * SQL arbitrariamente grande. Oltre questo numero i token in eccesso
     * vengono scartati (non troncano la query, solo il numero di termini
     * considerati per il matching/ranking).
     */
    public const MAX_SIGNIFICANT_TOKENS = 8;

    /**
     * Stopword italiane brevi/comuni — elenco deliberatamente piccolo e
     * mirato alla ricerca (non lo stesso elenco, più ampio, di
     * ArticleLinkSuggestionService::STOPWORDS, pensato per un altro
     * contesto): preposizioni articolate, articoli, congiunzioni corte che
     * comparirebbero praticamente in ogni articolo e non portano alcun
     * segnale di ricerca.
     */
    private const STOPWORDS = [
        'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una',
        'di', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
        'del', 'dello', 'della', 'dei', 'degli', 'delle',
        'al', 'allo', 'alla', 'ai', 'agli', 'alle',
        'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle',
        'nel', 'nello', 'nella', 'nei', 'negli', 'nelle',
        'sul', 'sullo', 'sulla', 'sui', 'sugli', 'sulle',
        'e', 'ed', 'o', 'od', 'ma', 'che', 'se',
    ];

    /**
     * Coppie di suffissi per la variante morfologica conservativa (§B della
     * missione): "stella"/"stelle" (a/e), "albero"/"alberi" (o/i) — scelte
     * bidirezionali, applicate SOLO all'ultimo carattere del token e solo
     * sopra MIN_LENGTH_FOR_MORPHOLOGICAL_VARIANT. Non è uno stemmer: non
     * tenta di indovinare la radice, si limita a scambiare la vocale
     * finale con la sua controparte più comune in italiano per i nomi
     * regolari di 1a/2a classe. Può produrre una variante che non è una
     * parola italiana reale (es. "pianeta" -> "pianete"): innocuo, la
     * variante viene solo usata per un confronto testuale, non validata
     * come parola — se non compare in nessun articolo, semplicemente non
     * cambia nulla.
     */
    private const MORPHOLOGICAL_SUFFIX_PAIRS = [
        'a' => 'e',
        'e' => 'a',
        'o' => 'i',
        'i' => 'o',
    ];

    private const MIN_LENGTH_FOR_MORPHOLOGICAL_VARIANT = 5;

    /**
     * Tokenizza $query in termini significativi normalizzati (minuscolo,
     * deduplicati, ordine di prima apparizione preservato, troncati a
     * MAX_SIGNIFICANT_TOKENS). Un token puramente numerico (nessuna
     * lettera) è escluso: mai un segnale tematico isolato in un contesto
     * di ricerca editoriale. Restituisce un array vuoto se $query non
     * contiene alcun termine utilizzabile (punteggiatura pura, solo
     * stopword, solo token troppo corti) — il chiamante decide come
     * trattare questo caso (mai una scansione indiscriminata del catalogo,
     * vedi ArticleSearchService).
     *
     * @return array<int, string>
     */
    public function tokenize(string $query): array
    {
        $query = $this->normalizeUnicodePunctuation(trim($query));

        if ($query === '') {
            return [];
        }

        preg_match_all("/[\\p{L}\\p{N}](?:[\\p{L}\\p{N}'-]*[\\p{L}\\p{N}])?/u", $query, $matches);

        $terms = [];

        foreach ($matches[0] ?? [] as $rawWord) {
            $word = mb_strtolower($rawWord, 'UTF-8');
            $word = $this->stripItalianElision($word);

            if ($word === '' || mb_strlen($word, 'UTF-8') < self::MIN_TOKEN_LENGTH) {
                continue;
            }

            // Un token senza alcuna lettera (solo cifre) non è mai un
            // segnale tematico isolato — stessa scelta del tokenizer V3.
            if (preg_match('/\p{L}/u', $word) !== 1) {
                continue;
            }

            $normalized = mb_strtolower(Str::ascii($word));

            if (in_array($normalized, self::STOPWORDS, true)) {
                continue;
            }

            $terms[$word] = true;

            if (count($terms) >= self::MAX_SIGNIFICANT_TOKENS) {
                break;
            }
        }

        return array_keys($terms);
    }

    /**
     * Variante morfologica conservativa di $term (vedi
     * MORPHOLOGICAL_SUFFIX_PAIRS), o null se $term è troppo corto o non
     * termina con una vocale coperta dalla tabella.
     */
    public function morphologicalVariant(string $term): ?string
    {
        $length = mb_strlen($term, 'UTF-8');

        if ($length < self::MIN_LENGTH_FOR_MORPHOLOGICAL_VARIANT) {
            return null;
        }

        $lastChar = mb_substr($term, -1, 1, 'UTF-8');

        if (! isset(self::MORPHOLOGICAL_SUFFIX_PAIRS[$lastChar])) {
            return null;
        }

        return mb_substr($term, 0, $length - 1, 'UTF-8').self::MORPHOLOGICAL_SUFFIX_PAIRS[$lastChar];
    }

    /**
     * Stesso principio del tokenizer V3 (ArticleLinkSuggestionService):
     * varianti Unicode di trattino/apice tipografico normalizzate alle
     * forme ASCII prima della tokenizzazione, così "Wi‑Fi" (trattino
     * tipografico) e "Wi-Fi" producono lo stesso token.
     */
    private function normalizeUnicodePunctuation(string $text): string
    {
        return strtr($text, [
            "\u{2010}" => '-',
            "\u{2011}" => '-',
            "\u{2012}" => '-',
            "\u{2013}" => '-',
            "\u{2014}" => '-',
            "\u{2212}" => '-',
            "\u{2019}" => "'",
        ]);
    }

    /**
     * Elenco chiuso di elisioni italiane comuni davanti a sostantivo/
     * aggettivo (stesso principio del tokenizer V3, elenco più corto:
     * qui basta gestire il caso comune "un'AI"/"l'universo" in una query
     * breve, non l'intera casistica editoriale).
     */
    private const ITALIAN_ELISION_PREFIXES = [
        "dell'", "dall'", "nell'", "sull'", "all'", "quell'", "quest'",
        "gl'", "un'", "l'", "d'",
    ];

    private function stripItalianElision(string $word): string
    {
        foreach (self::ITALIAN_ELISION_PREFIXES as $prefix) {
            if (str_starts_with($word, $prefix)) {
                return mb_substr($word, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8');
            }
        }

        return $word;
    }
}
