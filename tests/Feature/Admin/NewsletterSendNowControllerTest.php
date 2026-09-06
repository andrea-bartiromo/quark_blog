<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendNewsletterJob;
use App\Models\Article;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Prompt 116-120 (150-prompt program, readiness operativa): il pulsante
 * "Invia ora" (/admin/newsletter/invia-ora) e l'invio schedulato passano
 * dallo stesso comando newsletter:send — questo verifica che l'esito
 * mostrato all'editor rifletta davvero cosa e' successo (mai "Newsletter
 * inviata!" quando in realta' l'interruttore l'ha bloccato), e che
 * NEWSLETTER_SEND_ENABLED=false fermi anche questo percorso, non solo
 * lo scheduler.
 */
class NewsletterSendNowControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function confirmedSubscriber(string $email): Newsletter
    {
        return Newsletter::create([
            'email' => $email,
            'confirmed' => true,
            'token' => hash('sha256', 'confirm-'.$email),
            'unsubscribe_token' => md5('unsubscribe-'.$email),
        ]);
    }

    private function publishedArticle(): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo pubblicato '.uniqid('', true),
            'slug' => 'articolo-pubblicato-'.uniqid('', true),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_send_now_reports_success_and_dispatches_when_enabled(): void
    {
        $this->confirmedSubscriber('send-now-enabled@example.com');
        $this->publishedArticle();
        Bus::fake();

        $response = $this->actingAs($this->editor())->post(route('admin.newsletter.send-now'));

        $response->assertRedirect(route('admin.newsletter'));
        $response->assertSessionHas('success');
        $response->assertSessionMissing('warning');
        Bus::assertDispatchedTimes(SendNewsletterJob::class, 1);
    }

    public function test_send_now_reports_a_warning_and_dispatches_nothing_when_the_kill_switch_is_off(): void
    {
        config(['newsletter.send_enabled' => false]);
        $this->confirmedSubscriber('send-now-disabled@example.com');
        $this->publishedArticle();
        Bus::fake();

        $response = $this->actingAs($this->editor())->post(route('admin.newsletter.send-now'));

        $response->assertRedirect(route('admin.newsletter'));
        $response->assertSessionHas('warning');
        $response->assertSessionMissing('success');
        Bus::assertNotDispatched(SendNewsletterJob::class);
    }

    public function test_send_now_requires_an_authenticated_editor(): void
    {
        $response = $this->post(route('admin.newsletter.send-now'));

        $response->assertRedirect(route('login'));
    }
}
