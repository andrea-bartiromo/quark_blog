<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GROWTH S2 — FASE 9 (newsletter conversion audit): components/newsletter-alert
 * is included once globally by layouts/app.blade.php, ma
 * components/newsletter-popup.blade.php (incluso separatamente sulla stessa
 * pagina) conteneva una copia identica dello stesso markup, con lo stesso
 * id="newsletter-alert". Risultato: su qualunque pagina pubblica raggiunta
 * con ?newsletter=ok (il redirect di successo di NewsletterController), o
 * con un errore di validazione su 'email', l'HTML renderizzato conteneva due
 * elementi con lo stesso id — markup non valido, e
 * layouts/partials/newsletter-scripts.blade.php (che usa
 * document.getElementById('newsletter-alert') per il fade-out automatico)
 * agiva solo sul primo, lasciando il secondo visibile a tempo indefinito.
 */
class NewsletterAlertDuplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_alert_is_rendered_only_once_on_the_homepage(): void
    {
        $response = $this->get('/?newsletter=ok');

        $response->assertOk();
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'id="newsletter-alert"'),
            'id="newsletter-alert" must appear exactly once in the rendered HTML.'
        );
        $response->assertSee('Iscrizione ricevuta', false);
    }

    public function test_success_alert_is_rendered_only_once_on_an_article_page(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);

        $response = $this->get('/articolo/'.$article->slug.'?newsletter=ok');

        $response->assertOk();
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'id="newsletter-alert"'),
            'id="newsletter-alert" must appear exactly once in the rendered HTML.'
        );
    }

    public function test_validation_error_alert_is_rendered_only_once(): void
    {
        $response = $this->from('/')->post('/newsletter/subscribe', [
            'email' => 'non-un-indirizzo-valido',
        ]);

        $response->assertRedirect('/');

        $followUp = $this->get('/');

        $followUp->assertOk();
        $this->assertSame(
            1,
            substr_count($followUp->getContent(), 'id="newsletter-alert"'),
            'id="newsletter-alert" must appear exactly once in the rendered HTML.'
        );
    }

    public function test_no_alert_markup_is_rendered_without_the_query_parameter_or_errors(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('id="newsletter-alert"', false);
    }
}
