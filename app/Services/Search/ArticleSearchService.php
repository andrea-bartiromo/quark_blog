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

        if ($authorId) {
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

        $scoreParts[] = '(CASE WHEN LOWER(title) = ? THEN '.self::EXACT_TITLE_MATCH_BONUS.' ELSE 0 END)';
        $bindings[] = mb_strtolower($query, 'UTF-8');

        // I bonus di frase intera hanno senso solo con almeno due token:
        // una "frase" di un solo termine è già coperta dal peso per
        // campo sopra, ripeterla come bonus separato non aggiungerebbe
        // alcun segnale distintivo.
        if (count($tokens) >= 2) {
            $phrasePattern = $this->likePattern(implode(' ', $tokens));

            $scoreParts[] = "(CASE WHEN title LIKE ? ESCAPE '\\' THEN ".self::FULL_PHRASE_IN_TITLE_BONUS.' ELSE 0 END)';
            $bindings[] = $phrasePattern;

            $scoreParts[] = "(CASE WHEN (excerpt LIKE ? ESCAPE '\\' OR body LIKE ? ESCAPE '\\') THEN ".self::FULL_PHRASE_IN_BODY_OR_EXCERPT_BONUS.' ELSE 0 END)';
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
            $clauses = array_map(fn () => "{$field} LIKE ? ESCAPE '\\'", $patterns);

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
     * Neutralizza i caratteri speciali di LIKE ('%', '_') e il carattere
     * di escape stesso ('\') PRIMA di racchiudere il valore tra '%...%' —
     * un utente che digita letteralmente '%' o '_' cerca quel carattere,
     * non attiva un wildcard SQL arbitrario (item G della missione). Ogni
     * LIKE che consuma questo pattern dichiara sempre ESCAPE '\'', stesso
     * carattere di escape usato qui, esplicito e portabile (SQLite e MySQL
     * supportano entrambi la clausola ESCAPE con lo stesso carattere).
     */
    private function escapeLikeValue(string $value): string
    {
        $escaped = str_replace('\\', '\\\\', $value);
        $escaped = str_replace('%', '\\%', $escaped);

        return str_replace('_', '\\_', $escaped);
    }
}
