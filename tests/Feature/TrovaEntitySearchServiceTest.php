<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\Search\TrovaEntitySearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mission 07 — TROVA/Search Prefix Convergence.
 *
 * Recuperato da PR #319 (che aveva già corretto il gap di sicurezza
 * pubblica di PR #291 — vedi il docblock di TrovaEntitySearchService)
 * e convergente sulla policy Percorsi Scheduling V1 già in produzione
 * per ogni altro consumer pubblico (ContentCluster::publiclyVisible(),
 * non più solo active()).
 */
class TrovaEntitySearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_categories_backed_by_published_articles(): void
    {
        $publishedCategory = Category::create(['name' => 'Astrofisica Test', 'slug' => 'astrofisica-test', 'description' => 'Spazio e stelle']);
        $draftCategory = Category::create(['name' => 'Biologia Test', 'slug' => 'biologia-test', 'description' => 'Vita e cellule']);
        $this->article('stelle-test', 'astrofisica-test', Article::STATUS_PUBLISHED);
        $this->article('cellule-test', 'biologia-test', Article::STATUS_DRAFT);

        $results = app(TrovaEntitySearchService::class)->search('Astrofisica Test');

        $this->assertSame([$publishedCategory->id], $results['categories']->pluck('id')->all());
        $this->assertFalse($results['categories']->contains('id', $draftCategory->id));
        $this->assertSame('EXACT', $results['categories']->first()['match_class']);
    }

    public function test_published_secondary_category_membership_also_makes_category_eligible(): void
    {
        // $primary deliberatamente senza "Test" nel nome: ogni altra fixture
        // in questo file lo usa come suffisso di isolamento, ma qui
        // $primary è solo il "foil" che NON deve comparire nei risultati —
        // condividerebbe altrimenti il token "test" con la query
        // "Secondaria Pubblica Test" e farebbe scattare legittimamente
        // ANY_TOKEN (stessa semantica "OR tra token" già usata da
        // ArticleSearchService — vedi il docblock di
        // TrovaEntitySearchService::result()), producendo un falso
        // positivo del test, non del servizio.
        $primary = Category::create(['name' => 'Categoria Non Cercata', 'slug' => 'categoria-non-cercata']);
        $secondary = Category::create(['name' => 'Secondaria Pubblica Test', 'slug' => 'secondaria-pubblica-test']);
        $article = $this->article('secondary-membership-test', $primary->slug, Article::STATUS_PUBLISHED);
        $article->secondaryCategories()->attach($secondary->id);

        $results = app(TrovaEntitySearchService::class)->search('Secondaria Pubblica Test');
        $this->assertSame([$secondary->id], $results['categories']->pluck('id')->all());
    }

    /**
     * ANY_TOKEN è deliberatamente "OR tra i token", identico al criterio di
     * inclusione già usato da ArticleSearchService::applyTextSearch() (le
     * clausole per-token sono unite con OR, non AND) — un match debole su un
     * solo token comune non è un difetto di TROVA, è lo stesso contratto di
     * ricerca già in produzione per gli articoli. Regression esplicita
     * perché un lettore reale può digitare un solo termine e aspettarsi
     * comunque un risultato.
     */
    public function test_any_token_match_is_intentional_and_mirrors_article_search_semantics(): void
    {
        Category::create(['name' => 'Fisica Quantistica', 'slug' => 'fisica-quantistica', 'description' => 'Meccanica quantistica']);
        $this->article('quantistica-article', 'fisica-quantistica', Article::STATUS_PUBLISHED);

        $results = app(TrovaEntitySearchService::class)->search('quantistica nonexistentword');

        $this->assertSame('ANY_TOKEN', $results['categories']->first()['match_class']);
    }

    /**
     * Una query composta solo da stopword/token troppo corti non deve mai
     * degenerare in una scansione indiscriminata del catalogo — stesso
     * principio già documentato su SearchTokenizer::tokenize() e applicato
     * da ArticleSearchService: nessun token utilizzabile, zero risultati.
     */
    public function test_a_query_with_no_usable_tokens_returns_no_results(): void
    {
        Category::create(['name' => 'Una Categoria Qualsiasi', 'slug' => 'una-categoria-qualsiasi']);
        $this->article('una-categoria-article', 'una-categoria-qualsiasi', Article::STATUS_PUBLISHED);

        $results = app(TrovaEntitySearchService::class)->search('di per con su');

        $this->assertTrue($results['categories']->isEmpty());
        $this->assertTrue($results['percorsi']->isEmpty());
    }

    /**
     * L'accento non deve impedire il match: la normalizzazione ASCII-fold
     * di TrovaEntitySearchService::normalize() (Str::ascii()) deve
     * applicarsi in modo identico a testo memorizzato e query digitata.
     */
    public function test_accented_query_matches_unaccented_stored_text_and_vice_versa(): void
    {
        Category::create(['name' => 'Società e Ambiente', 'slug' => 'societa-e-ambiente']);
        $this->article('societa-article', 'societa-e-ambiente', Article::STATUS_PUBLISHED);

        $accentedQuery = app(TrovaEntitySearchService::class)->search('società');
        $unaccentedQuery = app(TrovaEntitySearchService::class)->search('societa');

        $this->assertSame(1, $accentedQuery['categories']->count());
        $this->assertSame(1, $unaccentedQuery['categories']->count());
    }

    public function test_percorso_requires_a_non_empty_continuous_public_prefix(): void
    {
        $published = $this->article('pubblico-test', 'fisica-test', Article::STATUS_PUBLISHED);
        $scheduled = $this->article('programmato-test', 'fisica-test', Article::STATUS_SCHEDULED, now()->addDay());

        $safe = ContentCluster::create(['name' => 'Percorso Sicuro Test', 'slug' => 'percorso-sicuro-test', 'is_active' => true]);
        $blocked = ContentCluster::create(['name' => 'Percorso Bloccato Test', 'slug' => 'percorso-bloccato-test', 'is_active' => true]);
        $inactive = ContentCluster::create(['name' => 'Percorso Inattivo Test', 'slug' => 'percorso-inattivo-test', 'is_active' => false]);

        $safe->articles()->attach($published->id, ['position' => 10, 'is_primary' => true]);
        $blocked->articles()->attach($scheduled->id, ['position' => 10, 'is_primary' => true]);
        $blocked->articles()->attach($published->id, ['position' => 20, 'is_primary' => false]);
        $inactive->articles()->attach($published->id, ['position' => 10, 'is_primary' => true]);

        $results = app(TrovaEntitySearchService::class)->search('Percorso Test');

        $this->assertTrue($results['percorsi']->contains('id', $safe->id));
        $this->assertFalse($results['percorsi']->contains('id', $blocked->id));
        $this->assertFalse($results['percorsi']->contains('id', $inactive->id));
    }

    public function test_percorso_becomes_eligible_when_first_gap_opens(): void
    {
        $first = $this->article('prima-tappa-test', 'fisica-test', Article::STATUS_SCHEDULED, now()->addDay());
        $second = $this->article('seconda-tappa-test', 'fisica-test', Article::STATUS_PUBLISHED);
        $cluster = ContentCluster::create(['name' => 'Percorso Apertura Test', 'slug' => 'percorso-apertura-test', 'is_active' => true]);
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true]);
        $cluster->articles()->attach($second->id, ['position' => 20, 'is_primary' => false]);

        $this->assertFalse(app(TrovaEntitySearchService::class)->search('Percorso Apertura Test')['percorsi']->contains('id', $cluster->id));

        $first->update(['status' => Article::STATUS_PUBLISHED, 'published_at' => now()->subMinute()]);

        $this->assertTrue(app(TrovaEntitySearchService::class)->search('Percorso Apertura Test')['percorsi']->contains('id', $cluster->id));
    }

    /**
     * Convergenza (Mission 07): "Percorsi Scheduled Activation V1" (questa
     * stessa sessione) ha introdotto ContentCluster::publiclyVisible(),
     * usata oggi da ogni altro consumer pubblico dei Percorsi. Un Percorso
     * is_active=true con publish_at futuro è "programmato", non ancora
     * pubblico: TROVA deve trattarlo esattamente come un Percorso inattivo,
     * mai come uno già raggiungibile.
     */
    public function test_a_scheduled_not_yet_public_percorso_is_excluded_even_with_a_public_prefix(): void
    {
        $published = $this->article('percorso-programmato-membro-test', 'fisica-test', Article::STATUS_PUBLISHED);

        $scheduledPercorso = ContentCluster::create([
            'name' => 'Percorso Futuro Test',
            'slug' => 'percorso-futuro-test',
            'is_active' => true,
            'publish_at' => now()->addDay(),
        ]);
        $scheduledPercorso->articles()->attach($published->id, ['position' => 10, 'is_primary' => true]);

        $immediatePercorso = ContentCluster::create([
            'name' => 'Percorso Immediato Test',
            'slug' => 'percorso-immediato-test',
            'is_active' => true,
            'publish_at' => null,
        ]);
        $immediatePercorso->articles()->attach($published->id, ['position' => 10, 'is_primary' => true]);

        $results = app(TrovaEntitySearchService::class)->search('Percorso Test');

        $this->assertFalse($results['percorsi']->contains('id', $scheduledPercorso->id));
        $this->assertTrue($results['percorsi']->contains('id', $immediatePercorso->id));
    }

    /**
     * Mission 29 — TROVA Percorsi Scheduling Safety. La formulazione della
     * missione elenca esplicitamente "inactive future Percorsi" come caso
     * da coprire: is_active=false E publish_at futuro insieme, non solo
     * separatamente (già provato altrove — vedi
     * test_percorso_requires_a_non_empty_continuous_public_prefix per
     * is_active=false da solo, e
     * test_a_scheduled_not_yet_public_percorso_is_excluded_even_with_a_public_prefix
     * per publish_at futuro da solo). scopePubliclyVisible() valuta
     * is_active PRIMA di publish_at (vedi ContentCluster::scopePubliclyVisible()),
     * quindi il caso combinato è già coperto dalla stessa query — questo
     * test lo rende esplicito e non deducibile.
     */
    public function test_a_percorso_that_is_both_inactive_and_scheduled_for_the_future_is_excluded(): void
    {
        $published = $this->article('percorso-inattivo-futuro-test', 'fisica-test', Article::STATUS_PUBLISHED);

        $inactiveAndFuture = ContentCluster::create([
            'name' => 'Percorso Inattivo Futuro Test',
            'slug' => 'percorso-inattivo-futuro-test',
            'is_active' => false,
            'publish_at' => now()->addWeek(),
        ]);
        $inactiveAndFuture->articles()->attach($published->id, ['position' => 10, 'is_primary' => true]);

        $results = app(TrovaEntitySearchService::class)->search('Percorso Inattivo Futuro Test');

        $this->assertFalse($results['percorsi']->contains('id', $inactiveAndFuture->id));
    }

    /**
     * Un Percorso i cui membri sono TUTTI non pubblici (nessun gap parziale,
     * zero prefisso pubblico fin dall'inizio) è un caso distinto dal "gap"
     * già testato sopra — qui non esiste alcun membro pubblico, non solo il
     * primo. ContentClusterPublicSequence::resolveLoaded() deve risolvere a
     * una lista vuota, escludendo il Percorso.
     */
    public function test_a_percorso_with_no_publicly_visible_articles_at_all_is_excluded(): void
    {
        $draft = $this->article('percorso-tutto-bozza-test', 'fisica-test', Article::STATUS_DRAFT, null);
        $scheduled = $this->article('percorso-tutto-programmato-test', 'fisica-test', Article::STATUS_SCHEDULED, now()->addDay());

        $cluster = ContentCluster::create(['name' => 'Percorso Senza Membri Pubblici Test', 'slug' => 'percorso-senza-membri-pubblici-test', 'is_active' => true]);
        $cluster->articles()->attach($draft->id, ['position' => 10, 'is_primary' => true]);
        $cluster->articles()->attach($scheduled->id, ['position' => 20, 'is_primary' => false]);

        $results = app(TrovaEntitySearchService::class)->search('Percorso Senza Membri Pubblici Test');

        $this->assertFalse($results['percorsi']->contains('id', $cluster->id));
    }

    /**
     * Un Percorso pubblicamente visibile (is_active=true, nessuna
     * programmazione) ma senza alcun articolo collegato non deve mai
     * comparire: nessun prefisso pubblico può esistere su una sequenza
     * vuota. Distinto dal caso sopra (membri presenti ma tutti non
     * pubblici) — qui manca la relazione stessa.
     */
    public function test_a_publicly_visible_percorso_with_no_articles_attached_is_excluded(): void
    {
        $cluster = ContentCluster::create(['name' => 'Percorso Vuoto Test', 'slug' => 'percorso-vuoto-test', 'is_active' => true]);

        $results = app(TrovaEntitySearchService::class)->search('Percorso Vuoto Test');

        $this->assertFalse($results['percorsi']->contains('id', $cluster->id));
    }

    /**
     * Contratto di sicurezza pubblica per l'item risultato: un Percorso non
     * deve mai esporre, tramite il proprio metadato TROVA, quanti articoli
     * contiene, quali sono, o dove si trova il gap che li nasconde. Chiude
     * esplicitamente la formulazione "articles beyond a continuous-prefix
     * gap through path/entity metadata" della Missione 29: prova che le
     * uniche chiavi esposte sono quelle documentate in
     * TrovaEntitySearchService::result(), nessuna in più.
     */
    public function test_percorso_result_never_exposes_article_membership_or_gap_metadata(): void
    {
        $public = $this->article('percorso-schema-pubblico-test', 'fisica-test', Article::STATUS_PUBLISHED);
        $hidden = $this->article('percorso-schema-nascosto-test', 'fisica-test', Article::STATUS_SCHEDULED, now()->addDay());

        $cluster = ContentCluster::create(['name' => 'Percorso Schema Test', 'slug' => 'percorso-schema-test', 'is_active' => true]);
        $cluster->articles()->attach($public->id, ['position' => 10, 'is_primary' => true]);
        $cluster->articles()->attach($hidden->id, ['position' => 20, 'is_primary' => false]);

        $result = app(TrovaEntitySearchService::class)->search('Percorso Schema Test')['percorsi']->first();

        $this->assertNotNull($result);
        $this->assertSame(
            ['type', 'id', 'label', 'slug', 'match_class', 'match_rank'],
            array_keys($result)
        );
    }

    public function test_match_classes_are_deterministic_without_numeric_relevance_scores(): void
    {
        Category::create(['name' => 'Cosmo Test', 'slug' => 'cosmo-test', 'description' => 'Missioni e universo']);
        Category::create(['name' => 'Scienza Vita Test', 'slug' => 'scienza-vita-test', 'description' => 'Scienza nella vita quotidiana']);
        $this->article('cosmo-test-article', 'cosmo-test', Article::STATUS_PUBLISHED);
        $this->article('vita-test-article', 'scienza-vita-test', Article::STATUS_PUBLISHED);

        $exact = app(TrovaEntitySearchService::class)->search('Cosmo Test')['categories']->first();
        $partial = app(TrovaEntitySearchService::class)->search('vita scienza')['categories']->first();

        $this->assertSame('EXACT', $exact['match_class']);
        $this->assertSame('ALL_TOKENS', $partial['match_class']);
        $this->assertArrayNotHasKey('score', $exact);
    }

    /**
     * Mission 28 — TROVA Ranking Quality. Il servizio ha esattamente tre
     * classi di match (EXACT/ALL_TOKENS/ANY_TOKEN, vedi
     * TrovaEntitySearchService::result()) e nessun punteggio numerico —
     * "avoid unexplained magic weights" è già rispettato dal codice
     * esistente. Questo test prova che l'ORDINAMENTO risultante da
     * UN'UNICA query che produce risultati di qualità mista rispetti
     * quella gerarchia end-to-end, non solo che ogni classe venga
     * assegnata correttamente in isolamento (già coperto sopra).
     */
    public function test_mixed_match_qualities_in_a_single_query_sort_exact_before_all_tokens_before_any_token(): void
    {
        $exact = Category::create(['name' => 'Fisica Quantistica', 'slug' => 'fisica-quantistica-rank', 'description' => 'Meccanica quantistica']);
        $allTokens = Category::create(['name' => 'Quantistica e Fisica moderna', 'slug' => 'quantistica-fisica-moderna-rank', 'description' => 'Approfondimenti']);
        $anyToken = Category::create(['name' => 'Solo Fisica generale', 'slug' => 'solo-fisica-generale-rank', 'description' => 'Introduzione']);
        $this->article('exact-rank-article', $exact->slug, Article::STATUS_PUBLISHED);
        $this->article('all-tokens-rank-article', $allTokens->slug, Article::STATUS_PUBLISHED);
        $this->article('any-token-rank-article', $anyToken->slug, Article::STATUS_PUBLISHED);

        $results = app(TrovaEntitySearchService::class)->search('Fisica Quantistica');

        $this->assertSame(
            [$exact->id, $allTokens->id, $anyToken->id],
            $results['categories']->pluck('id')->all(),
        );
        $this->assertSame(['EXACT', 'ALL_TOKENS', 'ANY_TOKEN'], $results['categories']->pluck('match_class')->all());
    }

    /**
     * Stessa gerarchia, lato Percorsi — la sola copertura esistente prima
     * di questa missione era sul lato Categorie.
     */
    public function test_percorsi_also_sort_exact_before_all_tokens_before_any_token(): void
    {
        $article = $this->article('percorso-rank-article', 'fisica-rank-test', Article::STATUS_PUBLISHED);

        $exact = ContentCluster::create(['name' => 'Viaggio Quantistico', 'slug' => 'viaggio-quantistico-rank', 'is_active' => true]);
        $allTokens = ContentCluster::create(['name' => 'Quantistico e il Viaggio nella materia', 'slug' => 'quantistico-viaggio-materia-rank', 'is_active' => true]);
        $anyToken = ContentCluster::create(['name' => 'Solo Viaggio nel tempo', 'slug' => 'solo-viaggio-tempo-rank', 'is_active' => true]);
        foreach ([$exact, $allTokens, $anyToken] as $cluster) {
            $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        }

        $results = app(TrovaEntitySearchService::class)->search('Viaggio Quantistico');

        $this->assertSame(
            [$exact->id, $allTokens->id, $anyToken->id],
            $results['percorsi']->pluck('id')->all(),
        );
    }

    /**
     * A parità di match_class, l'ordine deve dipendere solo da
     * label ASC poi id ASC — mai dall'ordine di inserimento o da un
     * ranking implicito del database. Etichette che normalizzano allo
     * STESSO testo (stesso match_class, stesso "peso") isolano il
     * tie-break finale: solo l'id decide, deterministicamente.
     */
    public function test_same_match_class_ties_break_by_label_then_by_id(): void
    {
        $second = Category::create(['name' => 'Tema Ricorrente', 'slug' => 'tema-ricorrente-a-rank']);
        $first = Category::create(['name' => 'Tema Ricorrente', 'slug' => 'tema-ricorrente-b-rank']);
        $this->article('tie-break-a-rank-article', $second->slug, Article::STATUS_PUBLISHED);
        $this->article('tie-break-b-rank-article', $first->slug, Article::STATUS_PUBLISHED);

        $results = app(TrovaEntitySearchService::class)->search('Tema Ricorrente');

        // Stesso nome normalizzato per entrambe (stesso match_class EXACT):
        // il tie-break decide solo per id crescente, indipendentemente da
        // quale delle due sia stata creata per prima nel test.
        $this->assertSame([$second->id, $first->id], $results['categories']->sortBy('id')->pluck('id')->all());
        $this->assertSame('EXACT', $results['categories']->first()['match_class']);
        $this->assertSame('EXACT', $results['categories']->last()['match_class']);
    }

    /**
     * Mission 28 menziona esplicitamente "prefix" tra i casi da testare.
     * Il servizio non ha una classe di match PREFIX dedicata (solo
     * EXACT/ALL_TOKENS/ANY_TOKEN — vedi il docblock di result()): una
     * query che è un prefisso multi-parola del nome risolve comunque in
     * modo prevedibile tramite ALL_TOKENS (ogni token della query è
     * contenuto nel testo), non tramite un peso "prefix" nascosto e
     * separato. Questo test documenta ed esplicita quel comportamento,
     * cosi che resti una scelta cosciente e non un dettaglio implicito.
     */
    public function test_a_multi_word_prefix_query_resolves_via_all_tokens_not_a_hidden_prefix_weight(): void
    {
        Category::create(['name' => 'Fisica Quantistica Avanzata', 'slug' => 'fisica-quantistica-avanzata-rank']);
        $this->article('prefix-rank-article', 'fisica-quantistica-avanzata-rank', Article::STATUS_PUBLISHED);

        $results = app(TrovaEntitySearchService::class)->search('Fisica Quantistica');

        $this->assertSame('ALL_TOKENS', $results['categories']->first()['match_class']);
    }

    /**
     * Chiude il set: nessun match_class al di fuori delle tre classi
     * documentate può mai comparire — non esiste ancora un'entità "alias"
     * in questo servizio (dipende dal Content Graph, Missione 30), quindi
     * il set valido resta chiuso a EXACT/ALL_TOKENS/ANY_TOKEN finché quella
     * missione non lo estende esplicitamente.
     */
    public function test_match_class_is_always_one_of_the_three_documented_values(): void
    {
        Category::create(['name' => 'Categoria Esatta Rank', 'slug' => 'categoria-esatta-rank']);
        Category::create(['name' => 'Categoria Esatta Rank Estesa', 'slug' => 'categoria-esatta-rank-estesa']);
        Category::create(['name' => 'Solo Rank generico', 'slug' => 'solo-rank-generico']);
        $this->article('closed-set-a-rank-article', 'categoria-esatta-rank', Article::STATUS_PUBLISHED);
        $this->article('closed-set-b-rank-article', 'categoria-esatta-rank-estesa', Article::STATUS_PUBLISHED);
        $this->article('closed-set-c-rank-article', 'solo-rank-generico', Article::STATUS_PUBLISHED);

        $results = app(TrovaEntitySearchService::class)->search('Categoria Esatta Rank');

        $this->assertNotEmpty($results['categories']);
        foreach ($results['categories'] as $result) {
            $this->assertContains($result['match_class'], ['EXACT', 'ALL_TOKENS', 'ANY_TOKEN']);
        }
    }

    public function test_query_count_does_not_grow_linearly_with_number_of_percorsi(): void
    {
        $one = $this->measureQueryCountForPercorsi(1);
        $eight = $this->measureQueryCountForPercorsi(8);

        $this->assertSame($one, $eight);
    }

    private function measureQueryCountForPercorsi(int $count): int
    {
        ContentCluster::query()->delete();
        $article = $this->article('query-article-'.$count, 'query-test', Article::STATUS_PUBLISHED);

        for ($i = 1; $i <= $count; $i++) {
            $cluster = ContentCluster::create(['name' => 'Query Percorso '.$i, 'slug' => 'query-percorso-'.$count.'-'.$i, 'is_active' => true]);
            $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TrovaEntitySearchService::class)->search('Query Percorso');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }

    private function article(string $slug, string $category, string $status, $publishedAt = null): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'excerpt' => 'Test excerpt',
            'body' => '<p>Test body</p>',
            'category' => $category,
            'status' => $status,
            'read_minutes' => 1,
            'published_at' => $publishedAt ?? ($status === Article::STATUS_PUBLISHED ? now()->subMinute() : null),
        ]));
    }
}
