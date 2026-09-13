<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cantiere 37 (programma 100-cantieri Kairus). Superficie HTTP per
 * ScheduledArticlesCertificationService, finora raggiungibile solo dal
 * comando editorial:scheduled-certification (con --days=30 esplicito).
 * Usa il servizio reale (nessun fake): la logica di certificazione ha
 * già una copertura completa in CertifyScheduledArticlesCommandTest, qui
 * si prova solo che la pagina applichi la finestra di 30 giorni di
 * default e lo stesso controllo di accesso delle altre pagine di analisi.
 */
class ScheduledPublicationsReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    /** @param array<string, mixed> $overrides */
    private function scheduledArticle(string $title, Carbon $publishedAt, array $overrides = []): Article
    {
        return Article::withoutEvents(fn () => Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo di prova.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => $publishedAt,
        ], $overrides)));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.scheduled-publications-report'))->assertRedirect(route('login'));
    }

    public function test_author_role_cannot_reach_the_page(): void
    {
        $this->actingAs($this->author())
            ->get(route('admin.scheduled-publications-report'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_sees_the_report(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.scheduled-publications-report'));

        $response->assertOk();
        $response->assertSee('Pubblicazioni programmate');
    }

    public function test_the_default_window_is_thirty_days(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00 UTC');
        $withinWindow = $this->scheduledArticle('Entro 30 giorni', now()->addDays(10));
        $outsideWindow = $this->scheduledArticle('Oltre 30 giorni', now()->addDays(45));

        $response = $this->actingAs($this->editor())->get(route('admin.scheduled-publications-report'));

        $response->assertOk();
        $response->assertSee('Entro 30 giorni');
        $response->assertDontSee('Oltre 30 giorni');
    }

    public function test_shows_content_health_warnings_and_collisions(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00 UTC');
        $this->scheduledArticle('Primo articolo', now()->addDay());
        $this->scheduledArticle('Secondo articolo', now()->addDay());

        $response = $this->actingAs($this->editor())->get(route('admin.scheduled-publications-report'));

        $response->assertOk();
        $response->assertSee('2 stesso orario');
    }

    public function test_shows_an_empty_state_when_nothing_is_scheduled(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.scheduled-publications-report'));

        $response->assertOk();
        $response->assertSee('Nessun articolo programmato nei prossimi 30 giorni.');
    }

    public function test_viewing_the_page_performs_no_mutation(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00 UTC');
        $article = $this->scheduledArticle('Immutabile', now()->addDay());
        $before = $article->refresh()->getAttributes();

        $this->actingAs($this->editor())->get(route('admin.scheduled-publications-report'));

        $this->assertSame($before, Article::find($article->id)->getAttributes());
    }
}
