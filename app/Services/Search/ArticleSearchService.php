<?php

namespace App\Services\Search;

use App\Models\Article;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Search V1 — ricerca pubblica lessicale multi-token su Article::published().
 * Non usa AI, embedding, servizi esterni o infrastrutture nuove: solo
 * query builder/Eloquent, deterministica e testabile.
 *
 * Un termine significativo produce candidatura (item D della missione): un
 * articolo entra nei risultati se ALMENO uno dei token della query compare
 * in uno dei campi ricercabili. Il ranking (vedi buildRankingExpression())
 * somma un peso per ogni (token, campo) trovato — più termini coperti, più
 * punteggio — con il titolo pesato più di excerpt/categoria/body, più
 * bonus per la frase intera e per un titolo che copre l'intera query.
 *
 * Sicurezza: ogni valore utente entra in una LIKE solo tramite parametro
 * bindato, mai concatenato nella stringa SQL; ogni pattern è generato da
 * escapeLikeValue(), che neutralizza '%', '_' e il carattere di escape
 * stesso prima di racchiuderlo tra '%...%' — un utente che digita '%' o
 * '_' cerca quel carattere letterale (nella pratica: nessun token, perché
 * il tokenizer li tratta come separatori, quindi nessuna corrispondenza —
 * mai un wildcard arbitrario).
 */
class ArticleSearchService
{
    private const PER_PAGE = 15;

    /**
     * Peso per campo di un singolo termine trovato — il titolo pesa più di
     * tutto il resto (item C/E della missione: "peso massimo" al titolo),
     * la categoria pesa più del body pur restando "utile ma inferiore al
     * titolo" (item C).
     */
    private const FIELD_WEIGHTS = [
        'title' => 20,
        'excerpt' => 8,
        'category' => 5,
        'body' => 3,
    ];

    /** Titolo che corrisponde esattamente (case-insensitive) alla query normalizzata — segnale più forte possibile (item E, livello 1). */
    private const EXACT_TITLE_MATCH_BONUS = 100;

    /** La FRASE intera (token uniti da spazio) compare letteralmente nel titolo — item F. */
    private const FULL_PHRASE_IN_TITLE_BONUS = 60;

    /** Titolo che copre TUTTI i token significativi della query — item E, livello 2/3. */
    private const ALL_TOKENS_IN_TITLE_BONUS = 40;

    /** La frase intera compare in excerpt o body — bonus più piccolo del caso titolo (item F). */
    private const FULL_PHRASE_IN_BODY_OR_EXCERPT_BONUS = 15;

    /**
     * Stessa mappa di SearchTokenizer::normalizeUnicodePunctuation(),
     * applicata qui al VALORE DI COLONNA via REPLACE() SQL annidate (Codex,
     * PR #166 round 2): il tokenizer normalizza solo la query, quindi un
     * titolo con punteggiatura tipografica reale come "Wi‑Fi" (trattino
     * tipografico) non veniva mai trovato da una query digitata con il
     * trattino ASCII "Wi-Fi" — solo un lato era normalizzato. REPLACE() è
     * SQL standard, portabile su SQLite e MySQL. I caratteri sono costanti
     * fisse definite da noi (non input utente): nessun rischio di
     * injection nell'interpolarli come literal SQL in normalizedColumnSql().
     */
    private const UNICODE_PUNCTUATION_TO_ASCII = [
        "\u{2010}" => '-',
        "\u{2011}" => '-',
        "\u{2012}" => '-',
        "\u{2013}" => '-',
        "\u{2014}" => '-',
        "\u{2212}" => '-',
        "\u{2019}" => "'",
    ];

    public function __construct(private readonly SearchTokenizer $tokenizer = new SearchTokenizer) {}

    /**
     * @param  string|null  $query  Testo libero digitato dal lettore, o null/stringa vuota per nessun filtro testuale.
     * @param  string|null  $category  Slug categoria (già validato altrove — stesso filtro preesistente).
     * @param  int|null  $authorId  ID autore (stesso filtro preesistente).
     * @param  string|null  $from  Data 'Y-m-d' (stesso filtro preesistente).
     * @param  string|null  $to  Data 'Y-m-d' (stesso filtro preesistente).
     */
    public function search(
        ?string $query,
        ?string $category = null,
        ?int $authorId = null,
        ?string $from = null,
        ?string $to = null
    ): LengthAwarePaginator {
        $query = trim((string) $query);

        $builder = Article::published()->with('author');

        if ($category) {
            $builder->where('category', $category);
        }

        // Codex (PR #166, round 1): !== null, non un controllo "truthy" —
        // un ID 0 (sentinella per un valore malformato non risolvibile, vedi
        // SearchController::normalizeAuthorFilter()) deve restare un vincolo
        // applicato (nessun autore reale ha id 0, quindi produce zero
        // risultati), non essere saltato come se nessun filtro fosse stato
        // richiesto.
        if ($authorId !== null) {
            $builder->where('user_id', $authorId);
        }

        if ($from) {
            $builder->where('published_at', '>=', $from.' 00:00:00');
        }

        if ($to) {
            $builder->where('published_at', '<=', $to.' 23:59:59');
        }

        if ($query !== '') {
            $tokens = $this->tokenizer->tokenize($query);

            if ($tokens === []) {
                // Query non vuota ma senza alcun termine utilizzabile
                // (solo punteggiatura, solo stopword, solo token troppo
                // corti): mai una scansione indiscriminata del catalogo
                // solo perché l'utente ha digitato qualcosa di
                // insignificante (item I della missione) — zero risultati,
                // esplicitamente, non "come se non ci fosse query".
                $builder->whereRaw('1 = 0');
            } else {
                $this->applyTextSearch($builder, $query, $tokens);
            }
        }

        return $builder->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function applyTextSearch(Builder $builder, string $query, array $tokens): void
    {
        // Precalcolato una volta per token: la stessa coppia di pattern
        // (letterale + variante morfologica) viene riusata sia per la
        // clausola WHERE (candidatura) sia per il ranking (peso), mai
        // ricalcolata due volte per lo stesso token.
        $tokenFragments = [];

        foreach ($tokens as $token) {
            $tokenFragments[] = $this->tokenFieldFragments($token);
        }

        $whereClauses = [];
        $whereBindings = [];

        foreach ($tokenFragments as $fragmentsByField) {
            $tokenClauses = [];

            foreach ($fragmentsByField as $fragment) {
                $tokenClauses[] = $fragment['sql'];
                array_push($whereBindings, ...$fragment['bindings']);
            }

            $whereClauses[] = '('.implode(' OR ', $tokenClauses).')';
        }

        $builder->whereRaw('('.implode(' OR ', $whereClauses).')', $whereBindings);

        [$scoreSql, $scoreBindings] = $this->buildRankingExpression($query, $tokens, $tokenFragments);

        // reorder(): scopePublished() imposta già un ORDER BY published_at
        // DESC di default — qui va sostituito dal ranking di rilevanza,
        // non semplicemente accodato (published_at resta comunque il
        // tie-breaker finale, item E ultimo livello).
        $builder->reorder();
        $builder->orderByRaw($scoreSql.' DESC', $scoreBindings);
        $builder->orderByDesc('published_at');
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildRankingExpression(string $query, array $tokens, array $tokenFragments): array
    {
        $scoreParts = [];
        $bindings = [];

        foreach ($tokenFragments as $fragmentsByField) {
            foreach (self::FIELD_WEIGHTS as $field => $weight) {
                $fragment = $fragmentsByField[$field];
                $scoreParts[] = "(CASE WHEN {$fragment['sql']} THEN {$weight} ELSE 0 END)";
                array_push($bindings, ...$fragment['bindings']);
            }
        }

        $normalizedQuery = $this->tokenizer->normalizeUnicodePunctuation($query);

        $scoreParts[] = '(CASE WHEN LOWER('.$this->columnSql('title', $normalizedQuery).') = ? THEN '.self::EXACT_TITLE_MATCH_BONUS.' ELSE 0 END)';
        $bindings[] = mb_strtolower($normalizedQuery, 'UTF-8');

        // I bonus di frase intera hanno senso solo con almeno due token:
        // una "frase" di un solo termine è già coperta dal peso per
        // campo sopra, ripeterla come bonus separato non aggiungerebbe
        // alcun segnale distintivo.
        if (count($tokens) >= 2) {
            $phraseValue = implode(' ', $tokens);
            $phrasePattern = $this->likePattern($phraseValue);

            $scoreParts[] = "(CASE WHEN {$this->columnSql('title', $phraseValue)} LIKE ? ESCAPE '!' THEN ".self::FULL_PHRASE_IN_TITLE_BONUS.' ELSE 0 END)';
            $bindings[] = $phrasePattern;

            $scoreParts[] = "(CASE WHEN ({$this->columnSql('excerpt', $phraseValue)} LIKE ? ESCAPE '!' OR {$this->columnSql('body', $phraseValue)} LIKE ? ESCAPE '!') THEN ".self::FULL_PHRASE_IN_BODY_OR_EXCERPT_BONUS.' ELSE 0 END)';
            $bindings[] = $phrasePattern;
            $bindings[] = $phrasePattern;

            $titleCoverageClauses = [];

            foreach ($tokenFragments as $fragmentsByField) {
                $titleCoverageClauses[] = $fragmentsByField['title']['sql'];
                array_push($bindings, ...$fragmentsByField['title']['bindings']);
            }

            $scoreParts[] = '(CASE WHEN ('.implode(' AND ', $titleCoverageClauses).') THEN '.self::ALL_TOKENS_IN_TITLE_BONUS.' ELSE 0 END)';
        }

        return ['('.implode(' + ', $scoreParts).')', $bindings];
    }

    /**
     * @return array<string, array{sql: string, bindings: array<int, string>}>
     */
    private function tokenFieldFragments(string $token): array
    {
        $patterns = [$this->likePattern($token)];

        $variant = $this->tokenizer->morphologicalVariant($token);

        if ($variant !== null && $variant !== $token) {
            $patterns[] = $this->likePattern($variant);
        }

        $fragments = [];

        foreach (array_keys(self::FIELD_WEIGHTS) as $field) {
            $columnSql = $this->columnSql($field, $token);
            $clauses = array_map(fn () => "{$columnSql} LIKE ? ESCAPE '!'", $patterns);

            $fragments[$field] = [
                'sql' => '('.implode(' OR ', $clauses).')',
                'bindings' => $patterns,
            ];
        }

        return $fragments;
    }

    private function likePattern(string $value): string
    {
        return '%'.$this->escapeLikeValue($value).'%';
    }

    /**
     * Codex (PR #166, round 3): avvolgere SEMPRE il campo in REPLACE()
     * annidate (una per ogni coppia di UNICODE_PUNCTUATION_TO_ASCII, quindi
     * fino a 7 chiamate annidate) moltiplicato per token/variante/campo/
     * predicato (candidatura + ranking) può produrre oltre un centinaio di
     * espressioni di normalizzazione per riga esaminata su una query a più
     * token — costoso soprattutto su body, il campo più lungo. La
     * normalizzazione serve SOLO a far combaciare trattini/apici
     * tipografici: se il valore confrontato ($comparisonValue: un token, la
     * frase intera, o la query normalizzata per il bonus di corrispondenza
     * esatta) non contiene alcun '-' o "'" — la stragrande maggioranza delle
     * query reali — non può esistere alcuna variante tipografica da
     * normalizzare da nessuna delle due parti, quindi il confronto resta
     * sul campo grezzo, senza REPLACE() alcuno.
     */
    private function columnSql(string $field, string $comparisonValue): string
    {
        return $this->needsPunctuationNormalization($comparisonValue)
            ? $this->normalizedColumnSql($field)
            : $field;
    }

    private function needsPunctuationNormalization(string $value): bool
    {
        return str_contains($value, '-') || str_contains($value, "'");
    }

    /**
     * Espressione SQL che legge $field applicando la stessa normalizzazione
     * di UNICODE_PUNCTUATION_TO_ASCII prima del confronto — REPLACE()
     * annidate, una per coppia, così sia il lato colonna sia il lato query
     * (già normalizzato da SearchTokenizer) finiscono nella stessa forma
     * canonica. $field è sempre uno dei nomi fissi in FIELD_WEIGHTS (mai
     * input utente): i caratteri sorgente/destinazione sono costanti
     * nostre, quindi interpolarli come literal SQL qui non introduce alcun
     * rischio di injection. Chiamata solo tramite columnSql() sopra, mai
     * incondizionatamente (vedi il suo docblock).
     */
    private function normalizedColumnSql(string $field): string
    {
        $sql = $field;

        foreach (self::UNICODE_PUNCTUATION_TO_ASCII as $from => $to) {
            $sql = 'REPLACE('.$sql.', '.$this->sqlStringLiteral($from).', '.$this->sqlStringLiteral($to).')';
        }

        return $sql;
    }

    private function sqlStringLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    /**
     * Neutralizza i caratteri speciali di LIKE ('%', '_') e il carattere
     * di escape stesso ('!') PRIMA di racchiudere il valore tra '%...%' —
     * un utente che digita letteralmente '%' o '_' cerca quel carattere,
     * non attiva un wildcard SQL arbitrario (item G della missione). Ogni
     * LIKE che consuma questo pattern dichiara sempre ESCAPE '!', stesso
     * carattere di escape usato qui, esplicito e portabile (SQLite e MySQL
     * supportano entrambi la clausola ESCAPE con lo stesso carattere).
     */
    private function escapeLikeValue(string $value): string
    {
        $escaped = str_replace('!', '!!', $value);
        $escaped = str_replace('%', '!%', $escaped);

        return str_replace('_', '!_', $escaped);
    }
}
