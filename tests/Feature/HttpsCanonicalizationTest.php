<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre la diagnosi e la correzione dei problemi di canonicalizzazione
 * segnalati da Search Console (08/08/2026):
 *
 * - homepage senza canonical ("Pagina duplicata senza URL canonico
 *   selezionato dall'utente");
 * - canonical (dove gia' presenti, es. sull'articolo) generati con lo
 *   schema della richiesta corrente invece che sempre https, quindi
 *   potenzialmente http se la richiesta arriva su http prima che un
 *   redirect lato server la intercetti;
 * - vecchio slug articolo risolto in 404 invece di un redirect permanente.
 *
 * Il redirect http -> https vero e proprio e' implementato in
 * public/.htaccess (mod_rewrite, lato Apache): non e' testabile da qui,
 * Laravel non vede mai una richiesta http reale in produzione una volta
 * che il redirect Apache e' attivo. Va verificato in produzione con:
 *   curl -I http://kairus.it/qualsiasi/path?con=query
 * (atteso: 301 verso https://kairus.it/qualsiasi/path?con=query).
 */
class HttpsCanonicalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Riproduce la configurazione di produzione (APP_URL su https):
        // di default in ambiente di test APP_URL e' http://localhost (vedi
        // .env), quindi URL::forceScheme('https') in AppServiceProvider
        // resta inattivo finche' non lo impostiamo esplicitamente qui.
        config(['app.url' => 'https://kairus.it']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'cover_image' => 'copertina.jpg',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    // 1. Canonical homepage corretto
    public function test_homepage_has_an_absolute_https_canonical(): void
    {
        $response = $this->get('https://kairus.it/');

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="https://kairus.it/">', false);
    }

    // 2. Canonical articolo corretto
    public function test_article_canonical_is_its_absolute_https_public_url(): void
    {
        $article = $this->publishedArticle($this->author());

        $response = $this->get('https://kairus.it/articolo/'.$article->slug);

        $response->assertOk();
        $response->assertSee(
            '<link rel="canonical" href="https://kairus.it/articolo/'.$article->slug.'">',
            false
        );
    }

    // 3. Assenza di canonical HTTP: anche una richiesta che arriva su http
    // (es. prima che un redirect lato server la intercetti) non deve mai
    // produrre un canonical http — questo è esattamente il fix di
    // AppServiceProvider (URL::forceScheme('https')).
    public function test_canonical_is_never_http_even_when_the_request_itself_arrives_over_http(): void
    {
        $article = $this->publishedArticle($this->author());

        $home = $this->get('http://kairus.it/');
        $home->assertOk();
        $home->assertDontSee('rel="canonical" href="http://', false);
        $home->assertSee('<link rel="canonical" href="https://kairus.it/">', false);

        $articolo = $this->get('http://kairus.it/articolo/'.$article->slug);
        $articolo->assertOk();
        $articolo->assertDontSee('rel="canonical" href="http://', false);
        $articolo->assertSee(
            '<link rel="canonical" href="https://kairus.it/articolo/'.$article->slug.'">',
            false
        );
    }

    // 4. Sitemap e feed: stesso principio del canonical, non devono mai
    // dipendere da un APP_URL configurato senza schema https (vedi
    // SeoController, prima leggeva config('app.url') a mano).
    public function test_sitemap_and_feed_urls_are_absolute_https(): void
    {
        $article = $this->publishedArticle($this->author());

        $sitemap = $this->get('https://kairus.it/sitemap.xml');
        $sitemap->assertOk();
        $sitemap->assertSee('https://kairus.it/articolo/'.$article->slug, false);
        $sitemap->assertDontSee('http://kairus.it', false);

        $feed = $this->get('https://kairus.it/feed.xml');
        $feed->assertOk();
        $feed->assertSee('https://kairus.it/articolo/'.$article->slug, false);
        $feed->assertDontSee('http://kairus.it', false);
    }

    // 5. Redirect del vecchio slug al nuovo, status permanente
    public function test_old_article_slug_redirects_permanently_to_the_new_one(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'slug' => 'chirurgia-robotica-e-intelligenza-artificiale-come-sta-cambiando-la-medicina',
        ]);

        ArticleSlugRedirect::create([
            'old_slug' => 'chirurgia-robotica-e-intelligenza-artificiale-il-futuro-e-sempre-piu-vicino',
            'article_id' => $article->id,
        ]);

        $response = $this->get('https://kairus.it/articolo/chirurgia-robotica-e-intelligenza-artificiale-il-futuro-e-sempre-piu-vicino');

        $response->assertStatus(301);
        $response->assertRedirect(route('articolo', $article->slug));
    }

    // 5bis. Il meccanismo è generale: registrato automaticamente da
    // Article::booted() a ogni cambio di slug, non solo per il caso
    // specifico segnalato da Search Console.
    public function test_renaming_an_article_slug_automatically_creates_a_redirect_from_the_old_one(): void
    {
        $article = $this->publishedArticle($this->author(), ['slug' => 'slug-originale']);

        $article->update(['slug' => 'slug-nuovo']);

        $this->assertDatabaseHas('article_slug_redirects', [
            'old_slug' => 'slug-originale',
            'article_id' => $article->id,
        ]);

        $response = $this->get('https://kairus.it/articolo/slug-originale');
        $response->assertStatus(301);
        $response->assertRedirect(route('articolo', 'slug-nuovo'));
    }

    // 5ter. Uno slug mai esistito continua a restituire un normale 404,
    // non un redirect (nessun falso positivo).
    public function test_a_slug_that_never_existed_still_returns_404(): void
    {
        $response = $this->get('https://kairus.it/articolo/questo-slug-non-e-mai-esistito');

        $response->assertNotFound();
    }

    // 5quater. Un vecchio slug non deve reindirizzare verso un articolo che
    // nel frattempo è stato spostato fuori pubblicazione (draft/rimosso):
    // stesso comportamento di uno slug mai esistito.
    public function test_old_slug_does_not_redirect_to_an_article_that_is_no_longer_published(): void
    {
        $article = $this->publishedArticle($this->author(), ['slug' => 'slug-attuale']);
        ArticleSlugRedirect::create([
            'old_slug' => 'slug-vecchio',
            'article_id' => $article->id,
        ]);

        $article->update(['status' => 'draft']);

        $response = $this->get('https://kairus.it/articolo/slug-vecchio');
        $response->assertNotFound();
    }

    // 6. /ricerca: la policy noindex,follow esiste già nel codice
    // (ricerca.blade.php) ed è già coperta da
    // ArticleSeoMetaTest::test_search_page_explicit_noindex_is_not_altered_by_the_new_default().
    // Qui verifichiamo in aggiunta che non venga usato l'header
    // X-Robots-Tag (il progetto usa solo il meta tag): la sua assenza in
    // produzione, segnalata nel task, è quindi attesa e corretta, non un
    // sintomo da correggere.
    public function test_search_page_uses_meta_robots_not_a_response_header(): void
    {
        $response = $this->get(route('ricerca'));

        $response->assertOk();
        $response->assertHeaderMissing('X-Robots-Tag');
        $response->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    // 7. Nessuna regressione sulle route pubbliche principali.
    public function test_main_public_routes_still_respond_with_200(): void
    {
        $author = $this->author();
        $article = $this->publishedArticle($author);

        $this->get(route('home'))->assertOk();
        $this->get(route('notizie'))->assertOk();
        $this->get(route('categoria', 'intelligenza-artificiale'))->assertOk();
        $this->get(route('articolo', $article->slug))->assertOk();
        $this->get(route('ricerca'))->assertOk();
    }
}
