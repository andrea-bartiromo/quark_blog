<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missione Frontend Performance & Quality — Safe Hardening: regressioni per
 * i bug reali corretti (falso negativo campo email newsletter senza label
 * accessibile, cache busting assente su quasi tutto il CSS pubblico, lazy
 * loading mancante sulle immagini nel corpo articolo).
 */
class FrontendPerformanceHardeningTest extends TestCase
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
            'excerpt' => 'Sommario di prova per il test.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    // ── Newsletter — campo email con label accessibile ──────────────────

    public function test_the_homepage_newsletter_email_field_has_an_accessible_label(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<label class="sr-only" for="home-newsletter-email">La tua email</label>', false);
        $response->assertSee('id="home-newsletter-email"', false);
    }

    public function test_the_article_page_newsletter_email_field_has_an_accessible_label(): void
    {
        $article = $this->publishedArticle();

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('<label class="sr-only" for="article-newsletter-email">La tua email</label>', false);
        $response->assertSee('id="article-newsletter-email"', false);
    }

    // ── Cache busting CSS pubblico ───────────────────────────────────────

    public function test_public_css_links_are_all_versioned_with_a_cache_busting_query_string(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();

        foreach (['style.css', 'home-premium.css', 'home-fix.css', 'public-premium.css', 'public-unified.css', 'premium-fixes.css'] as $file) {
            $response->assertSee('href="'.asset('css/'.$file).'?v=', false);
        }
    }

    public function test_article_lightbox_css_is_versioned_too(): void
    {
        $article = $this->publishedArticle();

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('href="'.asset('css/media-lightbox.css').'?v=', false);
    }

    // ── Lazy loading immagini nel corpo articolo ─────────────────────────

    public function test_body_images_without_a_loading_attribute_get_lazy_loading_applied(): void
    {
        $article = $this->publishedArticle([
            'body' => '<p>Testo introduttivo.</p><img src="/assets/img/foto.jpg" alt="Una foto"><p>Altro testo.</p>',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('<img src="/assets/img/foto.jpg" alt="Una foto" loading="lazy" decoding="async">', false);
    }

    public function test_body_images_that_already_declare_loading_are_left_untouched(): void
    {
        $article = $this->publishedArticle([
            'body' => '<img src="/assets/img/foto-body-unica.jpg" alt="Una foto" loading="eager">',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        // Scoped al tag esatto di questa immagine (non a "loading=\"lazy\""
        // in generale sulla pagina: altre immagini, come le card correlate,
        // lo usano già legittimamente altrove). decoding="async" viene
        // comunque aggiunto: non era già dichiarato, a differenza di loading.
        $response->assertSee('<img src="/assets/img/foto-body-unica.jpg" alt="Una foto" loading="eager" decoding="async">', false);
    }

    // ── Newsletter popup: apertura accessibile condivisa da header e timer ──

    public function test_the_header_newsletter_button_uses_the_shared_accessible_open_function(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('type="button" class="btn-subscribe"', false);
        $response->assertSee('window.kairusOpenNewsletterPopup', false);
    }
}
