<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendQualityPhase1Test extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function article(User $author, int $index = 1): Article
    {
        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo '.$index,
            'slug' => 'articolo-'.$index.'-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'published_at' => now()->subMinutes($index),
        ]);
    }

    public function test_newsletter_dialog_is_semantically_hidden_until_opened(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('id="newsletter-popup"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee("     hidden\n     inert>", false);
    }

    public function test_newsletter_script_contains_open_close_focus_contract(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('popup.hidden = false;', false);
        $response->assertSee('popup.inert = false;', false);
        $response->assertSee("event.key === 'Escape'", false);
        $response->assertSee("event.key !== 'Tab'", false);
        $response->assertSee('lastFocusedElement.focus();', false);
        $response->assertSee('window.kairusOpenNewsletterPopup = openPopup;', false);
    }

    public function test_cookie_bar_is_an_announced_live_region(): void
    {
        // FASE 10 audit: il banner appare in modo asincrono (setTimeout,
        // vedi components/cookie-bar.blade.php) senza alcuna regione
        // aria-live — uno screen reader non veniva mai avvisato della sua
        // comparsa (WCAG 4.1.3 Status Messages). Non e' un dialog modale
        // (nessun focus trap: l'utente puo' continuare a leggere la
        // pagina), quindi role="region" + aria-live="polite" e' corretto,
        // non role="alertdialog".
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('id="cookie-bar"', false);
        $response->assertSee('role="region"', false);
        $response->assertSee('aria-live="polite"', false);
        $response->assertSee('aria-label="Preferenze cookie"', false);
    }

    public function test_ticker_keeps_one_accessible_sequence_and_marks_loop_copy_decorative(): void
    {
        $author = $this->author();
        $this->article($author);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('class="ticker-sequence"', false);
        $response->assertSee('class="ticker-sequence" aria-hidden="true" inert', false);
        $response->assertSee('tabindex="-1"', false);
    }

    public function test_ticker_css_supports_keyboard_pause_and_reduced_motion(): void
    {
        $css = file_get_contents(public_path('css/frontend-hardening.css'));

        $this->assertStringContainsString('.ticker-track:focus-within', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('animation: none;', $css);
    }

    public function test_google_fonts_are_loaded_once_from_the_document_head(): void
    {
        $response = $this->get(route('home'));
        $response->assertOk();

        $response->assertSee('<link rel="preconnect" href="https://fonts.googleapis.com">', false);
        $response->assertSee('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>', false);
        $response->assertSee('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans', false);

        $css = file_get_contents(public_path('css/style.css'));

        $this->assertStringNotContainsString('@import url(\'https://fonts.googleapis.com/', $css);
    }

    public function test_homepage_styles_are_scoped_to_home_and_remain_versioned(): void
    {
        $home = $this->get(route('home'));
        $home->assertOk();

        foreach (['home-premium.css', 'home-fix.css'] as $file) {
            $home->assertSee('href="'.asset('css/'.$file).'?v=', false);
        }

        $author = $this->author();
        $article = $this->article($author);

        foreach ([route('articolo', $article->slug), route('autore', $author), route('notizie')] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertDontSee('home-premium.css', false);
            $response->assertDontSee('home-fix.css', false);
            $response->assertSee('public-premium.css?v=', false);
        }
    }

    public function test_author_canonical_is_clean_on_page_one_and_self_referencing_on_page_two(): void
    {
        $author = $this->author();

        for ($i = 1; $i <= 13; $i++) {
            $this->article($author, $i);
        }

        $pageOne = $this->get(route('autore', $author));
        $pageOne->assertOk();
        $pageOne->assertSee('<link rel="canonical" href="'.route('autore', $author).'">', false);
        $pageOne->assertSee('<meta property="og:url" content="'.route('autore', $author).'">', false);

        $pageTwoUrl = route('autore', ['user' => $author, 'page' => 2]);
        $pageTwo = $this->get($pageTwoUrl);
        $pageTwo->assertOk();
        $pageTwo->assertSee('<link rel="canonical" href="'.$pageTwoUrl.'">', false);
        $pageTwo->assertSee('<meta property="og:url" content="'.$pageTwoUrl.'">', false);
        $pageTwo->assertDontSee('<link rel="canonical" href="'.route('autore', $author).'">', false);
    }

    public function test_existing_archive_canonical_and_og_url_remain_aligned(): void
    {
        $author = $this->author();

        for ($i = 1; $i <= 13; $i++) {
            $this->article($author, $i);
        }

        $url = route('notizie', ['page' => 2]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="'.$url.'">', false);
        $response->assertSee('<meta property="og:url" content="'.$url.'">', false);
    }
}
