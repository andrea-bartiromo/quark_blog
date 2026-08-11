<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid('', true),
            'excerpt' => 'Un breve sommario di prova.',
            'body' => '<p>Corpo articolo di prova, senza alcuna relazione tematica particolare.</p>',
            'category' => 'energia',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    private function resultIds(TestResponse $response): array
    {
        return $response->viewData('results')->pluck('id')->all();
    }

    // 1. Ricerca per singolo termine presente nel titolo
    public function test_single_term_in_title_is_found(): void
    {
        $article = $this->article([
            'title' => 'Il Test di Turing spiegato davvero: può una macchina pensare?',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'test']));

        $response->assertOk();
        $this->assertContains($article->id, $this->resultIds($response));
    }

    // 2. Ricerca per termine non iniziale nel titolo
    public function test_a_non_initial_term_in_title_is_found(): void
    {
        $article = $this->article([
            'title' => 'Il Test di Turing spiegato davvero: può una macchina pensare?',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'turing']));

        $response->assertOk();
        $this->assertContains($article->id, $this->resultIds($response));
    }

    // 3. Multi-token: non dipende dalla frase letterale esatta
    public function test_multi_token_query_finds_a_pertinent_article_without_the_literal_phrase(): void
    {
        $article = $this->article([
            'title' => 'Un pioniere della crittografia',
            'excerpt' => 'La storia del test proposto da Turing per riconoscere una macchina pensante.',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'test turing']));

        $response->assertOk();
        $this->assertContains($article->id, $this->resultIds($response));
    }

    // 4. Ordine dei token differente produce comunque un risultato pertinente
    public function test_different_token_order_still_finds_the_pertinent_article(): void
    {
        $article = $this->article([
            'title' => 'Il Test di Turing spiegato davvero',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'turing test']));

        $response->assertOk();
        $this->assertContains($article->id, $this->resultIds($response));
    }

    // 5. Un match nel titolo si ordina prima di uno solo nel body
    public function test_a_title_match_outranks_a_body_only_match(): void
    {
        $titleMatch = $this->article([
            'title' => 'Turing e la nascita dell\'informatica',
            'excerpt' => 'Sommario neutro.',
            'body' => '<p>'.str_repeat('Testo neutro senza relazione. ', 10).'</p>',
        ]);

        $bodyOnlyMatch = $this->article([
            'title' => 'Storia della crittografia europea',
            'excerpt' => 'Sommario neutro.',
            'body' => '<p>Un capitolo importante riguarda il contributo di Turing alla disciplina.</p>',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'turing']));

        $response->assertOk();
        $ids = $this->resultIds($response);

        $this->assertContains($titleMatch->id, $ids);
        $this->assertContains($bodyOnlyMatch->id, $ids);
        $this->assertLessThan(array_search($bodyOnlyMatch->id, $ids), array_search($titleMatch->id, $ids));
    }

    // 6. Copertura multi-token: due termini coperti battono uno solo
    public function test_covering_more_tokens_outranks_covering_only_one(): void
    {
        $bothTerms = $this->article([
            'title' => 'Il Test di Turing spiegato davvero',
            'body' => '<p>'.str_repeat('Testo neutro senza relazione. ', 10).'</p>',
        ]);

        $oneTermOnly = $this->article([
            'title' => 'Un test qualunque su un altro argomento',
            'body' => '<p>'.str_repeat('Testo neutro senza relazione. ', 10).'</p>',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'test turing']));

        $response->assertOk();
        $ids = $this->resultIds($response);

        $this->assertContains($bothTerms->id, $ids);
        $this->assertContains($oneTermOnly->id, $ids);
        $this->assertLessThan(array_search($oneTermOnly->id, $ids), array_search($bothTerms->id, $ids));
    }

    // 7. Match tramite excerpt
    public function test_a_match_only_in_the_excerpt_is_found(): void
    {
        $article = $this->article([
            'title' => 'Un titolo neutro qualsiasi',
            'excerpt' => 'Un approfondimento su Betelgeuse, la celebre stella rossa.',
            'body' => '<p>'.str_repeat('Testo neutro senza relazione. ', 10).'</p>',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'betelgeuse']));

        $response->assertOk();
        $this->assertContains($article->id, $this->resultIds($response));
    }

    // 8. Match tramite body (peso inferiore, ma trovato)
    public function test_a_match_only_in_the_body_is_found(): void
    {
        $article = $this->article([
            'title' => 'Un titolo neutro qualsiasi',
            'excerpt' => 'Un sommario neutro.',
            'body' => '<p>Nel corso dell\'articolo si parla anche di transformer applicati al linguaggio naturale.</p>',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'transformer']));

        $response->assertOk();
        $this->assertContains($article->id, $this->resultIds($response));
    }

    // 9. Match tramite categoria
    public function test_a_category_match_contributes_to_the_results(): void
    {
        $article = $this->article([
            'title' => 'Un titolo neutro qualsiasi',
            'excerpt' => 'Un sommario neutro.',
            'body' => '<p>'.str_repeat('Testo neutro senza relazione. ', 10).'</p>',
            'category' => 'spazio',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'spazio']));

        $response->assertOk();
        $this->assertContains($article->id, $this->resultIds($response));
    }

    // 10. Variazione morfologica stella/stelle
    public function test_morphological_variation_stella_stelle(): void
    {
        $article = $this->article([
            'title' => 'Il ciclo di vita delle stelle',
            'excerpt' => 'Come nascono e muoiono le stelle massicce.',
            'body' => '<p>'.str_repeat('Testo neutro senza relazione. ', 10).'</p>',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'stella']));

        $response->assertOk();
        $this->assertContains($article->id, $this->resultIds($response));
    }

    // 11. Variazione morfologica albero/alberi
    public function test_morphological_variation_albero_alberi(): void
    {
        $article = $this->article([
            'title' => 'Perché gli alberi raffreddano le città',
            'excerpt' => 'Il ruolo degli alberi nella regolazione termica urbana.',
            'body' => '<p>'.str_repeat('Testo neutro senza relazione. ', 10).'</p>',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'albero']));

        $response->assertOk();
        $this->assertContains($article->id, $this->resultIds($response));
    }

    // 12. Maiuscole/minuscole: comportamento equivalente
    public function test_uppercase_and_lowercase_queries_behave_equivalently(): void
    {
        $article = $this->article(['title' => 'Il Test di Turing spiegato davvero']);

        $upper = $this->resultIds($this->get(route('ricerca', ['q' => 'TURING'])));
        $lower = $this->resultIds($this->get(route('ricerca', ['q' => 'turing'])));

        $this->assertContains($article->id, $upper);
        $this->assertContains($article->id, $lower);
    }

    // 13. Punteggiatura ragionevole non fa fallire la ricerca
    public function test_reasonable_punctuation_does_not_break_the_search(): void
    {
        $article = $this->article(['title' => 'Il Test di Turing spiegato davvero']);

        $response = $this->get(route('ricerca', ['q' => 'Turing?']));

        $response->assertOk();
        $this->assertContains($article->id, $this->resultIds($response));
    }

    // 14. Duplicati: un articolo che matcha title+excerpt+body compare una sola volta
    public function test_an_article_matching_multiple_fields_appears_only_once(): void
    {
        $article = $this->article([
            'title' => 'Turing e la nascita dell\'informatica',
            'excerpt' => 'Un approfondimento su Turing e il suo lavoro.',
            'body' => '<p>Turing ha lasciato un\'eredità enorme nella disciplina.</p>',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'turing']));

        $response->assertOk();
        $ids = $this->resultIds($response);

        $this->assertSame(1, count(array_filter($ids, fn ($id) => $id === $article->id)));
    }

    // 15. Solo gli articoli pubblicati compaiono
    public function test_only_published_articles_appear(): void
    {
        $draft = $this->article([
            'title' => 'Turing in bozza',
            'status' => 'draft',
            'published_at' => null,
        ]);

        $review = $this->article([
            'title' => 'Turing in revisione',
            'status' => 'review',
            'published_at' => null,
        ]);

        $response = $this->get(route('ricerca', ['q' => 'turing']));

        $response->assertOk();
        $ids = $this->resultIds($response);

        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($review->id, $ids);
    }

    // 16. Un articolo scheduled non ancora pubblico non compare
    public function test_a_scheduled_not_yet_public_article_does_not_appear(): void
    {
        $scheduled = $this->article([
            'title' => 'Turing programmato per il futuro',
            'status' => 'scheduled',
            'published_at' => now()->addDays(5),
        ]);

        $response = $this->get(route('ricerca', ['q' => 'turing']));

        $response->assertOk();
        $this->assertNotContains($scheduled->id, $this->resultIds($response));
    }

    // 17. Filtro categoria + testo insieme
    public function test_category_filter_combined_with_text_still_works(): void
    {
        $matching = $this->article([
            'title' => 'Turing e la crittografia',
            'category' => 'spazio',
        ]);

        $wrongCategory = $this->article([
            'title' => 'Turing e la crittografia, altra categoria',
            'category' => 'energia',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'turing', 'categoria' => 'spazio']));

        $response->assertOk();
        $ids = $this->resultIds($response);

        $this->assertContains($matching->id, $ids);
        $this->assertNotContains($wrongCategory->id, $ids);
    }

    // 18. Filtro autore + testo insieme
    public function test_author_filter_combined_with_text_still_works(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $otherAuthor = User::factory()->create(['role' => 'editor']);

        $matching = $this->article(['user_id' => $author->id, 'title' => 'Turing e la crittografia']);
        $wrongAuthor = $this->article(['user_id' => $otherAuthor->id, 'title' => 'Turing e la crittografia, altro autore']);

        $response = $this->get(route('ricerca', ['q' => 'turing', 'autore' => $author->id]));

        $response->assertOk();
        $ids = $this->resultIds($response);

        $this->assertContains($matching->id, $ids);
        $this->assertNotContains($wrongAuthor->id, $ids);
    }

    // 18b. Codex (PR #166, round 1): un valore "autore" malformato (non numerico) non deve
    // essere silenziosamente scartato (mostrando TUTTI gli autori) né far coincidere un ID
    // arbitrario diverso da quello digitato — deve produrre zero risultati, come un autore
    // che non esiste.
    public function test_malformed_author_filter_returns_zero_results_not_everyone(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $this->article(['user_id' => $author->id, 'title' => 'Turing e la crittografia']);
        $this->article(['user_id' => $author->id, 'title' => 'Un altro articolo qualunque']);

        $response = $this->get(route('ricerca', ['autore' => 'abc']));

        $response->assertOk();
        $results = $response->viewData('results');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertSame(0, $results->total());
    }

    // 18c. Codex (PR #166, round 1): "1abc" non deve essere silenziosamente interpretato
    // come l'autore con ID 1 — è un valore malformato, non l'ID digitato.
    public function test_author_filter_with_trailing_garbage_does_not_match_the_leading_digits(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $article = $this->article(['user_id' => $author->id, 'title' => 'Turing e la crittografia']);

        $response = $this->get(route('ricerca', ['autore' => $author->id.'abc']));

        $response->assertOk();
        $results = $response->viewData('results');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertSame(0, $results->total());
        $this->assertNotContains($article->id, $this->resultIds($response));
    }

    // 19. Filtro date + testo insieme
    public function test_date_filters_combined_with_text_still_work(): void
    {
        $inRange = $this->article([
            'title' => 'Turing e la crittografia',
            'published_at' => now()->subDays(5),
        ]);

        $outOfRange = $this->article([
            'title' => 'Turing e la crittografia, fuori intervallo',
            'published_at' => now()->subDays(50),
        ]);

        $response = $this->get(route('ricerca', [
            'q' => 'turing',
            'da' => now()->subDays(10)->toDateString(),
            'a' => now()->toDateString(),
        ]));

        $response->assertOk();
        $ids = $this->resultIds($response);

        $this->assertContains($inRange->id, $ids);
        $this->assertNotContains($outOfRange->id, $ids);
    }

    // 20. Filtri senza testo continuano a funzionare
    public function test_filters_without_text_still_work(): void
    {
        $matching = $this->article(['category' => 'spazio']);
        $other = $this->article(['category' => 'energia']);

        $response = $this->get(route('ricerca', ['categoria' => 'spazio']));

        $response->assertOk();
        $ids = $this->resultIds($response);

        $this->assertContains($matching->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    // 21. Query vuota: nessuna regressione (nessun elenco, stato "inizia una ricerca")
    public function test_empty_query_shows_no_listing(): void
    {
        $this->article();

        $response = $this->get(route('ricerca'));

        $response->assertOk();
        $results = $response->viewData('results');

        $this->assertFalse($results instanceof LengthAwarePaginator);
    }

    // 22. Query di solo whitespace/punteggiatura: zero risultati, non una scansione indiscriminata
    public function test_whitespace_or_punctuation_only_query_returns_zero_results_not_everything(): void
    {
        $this->article();
        $this->article();

        $response = $this->get(route('ricerca', ['q' => '!!!']));

        $response->assertOk();
        $results = $response->viewData('results');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertSame(0, $results->total());
    }

    // 23. Un token di due caratteri ("IA") funziona
    public function test_two_character_token_ia_is_a_valid_query(): void
    {
        $article = $this->article([
            'title' => 'Come la IA sta cambiando la ricerca scientifica',
        ]);

        $response = $this->get(route('ricerca', ['q' => 'IA']));

        $response->assertOk();
        $this->assertContains($article->id, $this->resultIds($response));
    }

    // 24. Wildcard % non diventa un jolly SQL arbitrario
    public function test_percent_wildcard_input_is_not_treated_as_an_sql_wildcard(): void
    {
        $this->article(['title' => 'Un articolo qualunque']);
        $this->article(['title' => 'Un altro articolo qualunque']);

        $response = $this->get(route('ricerca', ['q' => '%']));

        $response->assertOk();
        $results = $response->viewData('results');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertSame(0, $results->total());
    }

    // 25. Wildcard _ non diventa un jolly SQL arbitrario
    public function test_underscore_wildcard_input_is_not_treated_as_an_sql_wildcard(): void
    {
        $this->article(['title' => 'Un articolo qualunque']);
        $this->article(['title' => 'Un altro articolo qualunque']);

        $response = $this->get(route('ricerca', ['q' => '_']));

        $response->assertOk();
        $results = $response->viewData('results');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertSame(0, $results->total());
    }

    // 26. La paginazione preserva i parametri di ricerca
    public function test_pagination_preserves_search_parameters(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->article([
                'title' => 'Turing e la crittografia numero '.$i,
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->get(route('ricerca', ['q' => 'turing']));

        $response->assertOk();
        $response->assertSee('q=turing', false);
    }

    // 27. Tie-breaker deterministico: a parità di punteggio, published_at DESC
    public function test_equal_score_results_are_ordered_by_published_at_as_a_tiebreaker(): void
    {
        $older = $this->article([
            'title' => 'Turing e la crittografia',
            'published_at' => now()->subDays(2),
        ]);

        $newer = $this->article([
            'title' => 'Turing e la crittografia',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get(route('ricerca', ['q' => 'turing crittografia']));

        $response->assertOk();
        $ids = $this->resultIds($response);

        $this->assertLessThan(array_search($older->id, $ids), array_search($newer->id, $ids));
    }

    /**
     * Performance/N+1: la ricerca resta un numero FISSO di query
     * (candidatura+ranking in un'unica SELECT, più COUNT per la
     * paginazione, più l'eager load autore) indipendentemente dal numero
     * di token nella query e dalla dimensione del corpus — nessuna query
     * per candidato, nessuna query per token.
     */
    public function test_search_query_count_does_not_grow_with_corpus_size_or_token_count(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $this->article([
                'title' => 'Articolo generico numero '.$i,
                'body' => '<p>'.str_repeat('Testo neutro senza relazione. ', 10).'</p>',
            ]);
        }

        DB::enableQueryLog();

        $this->get(route('ricerca', ['q' => 'test turing chatgpt transformer stella albero buco nero']));

        $countLarge = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        for ($i = 0; $i < 5; $i++) {
            $this->article([
                'title' => 'Altro articolo numero '.$i,
            ]);
        }

        DB::enableQueryLog();
        $this->get(route('ricerca', ['q' => 'test']));

        $countSmall = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $countSmall,
            $countLarge,
            "Con una query a 8 token su un corpus più grande: {$countLarge} query. Con una query a 1 token su un corpus più piccolo: {$countSmall} query. Devono coincidere."
        );
    }
}
