<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S4 Performance: guardia di regressione per gli N+1 misurati e corretti
 * nell'audit (baseline con DB::getQueryLog() su /notizie, /articolo,
 * /categoria a 6/100/1000 articoli — vedi report finale). Non un budget
 * assoluto arbitrario: ogni soglia qui riflette il conteggio query
 * realmente misurato dopo la correzione, con un margine minimo per
 * assestamenti futuri legittimi (es. un nuovo widget), non per assorbire
 * un N+1 reintrodotto.
 */
class PublicPageQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'cover_image' => 'copertina.jpg',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ], $overrides));
    }

    private function queryCountFor(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * Prima della correzione: Category::options(false) veniva rieseguita
     * una volta per ciascun articolo della pagina (1 query "select name,
     * slug from categories" per card) — il conteggio cresceva linearmente
     * con gli articoli mostrati. Dopo la correzione (hoisted fuori dal
     * loop in notizie.blade.php) il conteggio resta identico da 3 a 15
     * articoli pubblicati (oltre la pagina da 12).
     */
    public function test_notizie_query_count_does_not_grow_with_article_count_on_page(): void
    {
        $this->publishedArticle();
        $this->publishedArticle();
        $this->publishedArticle();
        $countAt3 = $this->queryCountFor(route('notizie'));

        for ($i = 0; $i < 12; $i++) {
            $this->publishedArticle();
        }
        $countAt15 = $this->queryCountFor(route('notizie'));

        $this->assertSame(
            $countAt3,
            $countAt15,
            'Il conteggio query di /notizie cresce con il numero di articoli: possibile N+1 reintrodotto (es. Category::options() dentro il loop delle card).'
        );
        $this->assertLessThanOrEqual(7, $countAt15);
    }

    /**
     * Prima della correzione: 16 query, di cui 4 identiche a
     * "select name, slug from categories" (una per ciascuno dei 4 punti
     * che leggevano la categoria — meta tag, breadcrumb, JSON-LD, header
     * variabile locale) più 1 eager load di 'comments' mai renderizzato
     * pubblicamente e 1 variabile 'mostRead' mai consumata dalla vista.
     */
    public function test_article_page_query_count_is_within_the_post_fix_budget(): void
    {
        $article = $this->publishedArticle();

        $count = $this->queryCountFor(route('articolo', $article->slug));

        // Budget +1 rispetto agli 11 storici: ArticleContinuationService
        // ("Continua da qui", Growth S2) riusa il pathNavigation già
        // caricato dal controller (nessuna query duplicata — vedi
        // ArticleContinuationServiceTest::
        // test_passing_a_real_null_navigation_never_triggers_a_second_lookup)
        // e aggiunge esattamente 1 query bounded per il fallback di
        // categoria quando non esiste un Percorso attivo. Non un N+1: il
        // costo resta costante indipendentemente dal numero di articoli.
        //
        // Budget +2 ulteriori (Mission 24/25, Content Graph Public
        // Consumer): ArticleController::show() ora chiama
        // ContentGraphService::discoverableConceptsForArticle() per i dati
        // strutturati `about` — 2 query bounded (il check
        // Article::published()->whereKey()->exists() + la query
        // ArticleConcept con eager load concept.aliases, che resta 3 query
        // fisse — article_concepts + concepts IN(...) + concept_aliases
        // IN(...) — indipendentemente da quanti concetti sono collegati,
        // stesso identico shape con 1 o con 5 righe, verificato manualmente
        // con DB::getQueryLog(); l'invarianza O(1) è già certificata a
        // livello di servizio da ContentGraphPublicSafetyContractTest, non
        // riverificata qui end-to-end perché la pagina articolo ha già
        // un'altra sorgente di query non deterministica indipendente dai
        // concetti — ArticleContinuationService::recordImpression(), che
        // scrive article_continuation_events solo quando showContinuation
        // risolve true — che renderebbe un confronto "N concetti vs M
        // concetti" a livello di pagina intera inaffidabile per ragioni
        // del tutto estranee a questo consumer.
        $this->assertLessThanOrEqual(14, $count);
    }

    /**
     * Prima della correzione: ArticleController::category() calcolava una
     * variabile 'mostRead' mai referenziata da categoria.blade.php (il
     * widget "più letti" realmente mostrato vive in components/sidebar.
     * blade.php e fa la propria query indipendente).
     */
    public function test_category_page_query_count_is_within_the_post_fix_budget(): void
    {
        $this->publishedArticle(['category' => 'energia']);

        $count = $this->queryCountFor(route('categoria', 'energia'));

        $this->assertLessThanOrEqual(7, $count);
    }

    public function test_notizie_page_still_shows_the_correct_category_label_per_article(): void
    {
        $this->publishedArticle(['category' => 'energia']);

        $this->get(route('notizie'))->assertOk()->assertSee('Energia &amp; Clima', false);
    }
}
