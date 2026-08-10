<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AnalyticsExclusionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsExclusionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    public function test_a_guest_cannot_exclude_this_browser(): void
    {
        $response = $this->post(route('admin.analytics.exclude'));

        $response->assertRedirect(route('login'));
        $response->assertCookieMissing(AnalyticsExclusionService::COOKIE_NAME);
    }

    public function test_a_guest_cannot_reactivate_analytics(): void
    {
        $response = $this->post(route('admin.analytics.reactivate'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_non_editor_author_cannot_exclude_this_browser(): void
    {
        $response = $this->actingAs($this->author())->post(route('admin.analytics.exclude'));

        $response->assertRedirect(route('redazione.dashboard'));
        $response->assertCookieMissing(AnalyticsExclusionService::COOKIE_NAME);
    }

    public function test_an_editor_can_exclude_this_browser(): void
    {
        $response = $this->actingAs($this->editor())->post(route('admin.analytics.exclude'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertCookie(AnalyticsExclusionService::COOKIE_NAME);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === AnalyticsExclusionService::COOKIE_NAME);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertNotEmpty($cookie->getValue());
    }

    public function test_an_editor_can_reactivate_analytics(): void
    {
        $response = $this->actingAs($this->editor())->post(route('admin.analytics.reactivate'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === AnalyticsExclusionService::COOKIE_NAME);

        $this->assertNotNull($cookie);
        $this->assertLessThan(time(), $cookie->getExpiresTime());
    }

    public function test_exclude_route_only_accepts_post(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.analytics.exclude'));

        $response->assertStatus(405);
    }

    public function test_exclude_and_reactivate_routes_stay_in_the_web_middleware_group(): void
    {
        // Il middleware CSRF (Illuminate\Foundation\Http\Middleware\
        // VerifyCsrfToken) e' applicato dal gruppo 'web', non da ognuna
        // di queste due rotte singolarmente: verifica che nessuna delle
        // due sia stata definita al di fuori di quel gruppo (es. dentro
        // routes/api.php, o con ->withoutMiddleware()), che le
        // priverebbe silenziosamente della protezione CSRF di tutto il
        // resto dell'area admin.
        foreach (['admin.analytics.exclude', 'admin.analytics.reactivate'] as $name) {
            $middleware = collect(app('router')->getRoutes()->getByName($name)->gatherMiddleware());

            $this->assertTrue($middleware->contains('web'), "{$name} deve restare nel gruppo web (CSRF incluso).");
            $this->assertTrue($middleware->contains('auth'), "{$name} deve restare protetta da auth.");
            $this->assertTrue($middleware->contains('editor'), "{$name} deve restare protetta da editor.");
        }
    }

    // ── Verifica visiva sulla pagina profilo ─────────────────────────

    public function test_profile_page_shows_analytics_active_when_not_excluded(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.profile'));

        $response->assertOk();
        $response->assertSee('ATTIVO', false);
        $response->assertSee('Escludi questo browser dalle statistiche');
        $response->assertDontSee('Riattiva tracciamento');
    }

    public function test_profile_page_shows_analytics_excluded_with_the_activation_date(): void
    {
        $response = $this->actingAs($this->editor())
            ->withCookie(AnalyticsExclusionService::COOKIE_NAME, '2026-08-10T09:30:00+00:00')
            ->get(route('admin.profile'));

        $response->assertOk();
        $response->assertSee('ESCLUSO', false);
        $response->assertSee('Riattiva tracciamento');
        $response->assertDontSee('Escludi questo browser dalle statistiche');
    }

    public function test_excluding_then_reactivating_round_trips_back_to_active_on_the_profile_page(): void
    {
        $editor = $this->editor();

        $excludeResponse = $this->actingAs($editor)->post(route('admin.analytics.exclude'));
        $cookie = collect($excludeResponse->headers->getCookies())
            ->first(fn ($c) => $c->getName() === AnalyticsExclusionService::COOKIE_NAME);

        $afterExclude = $this->actingAs($editor)
            ->withCookie(AnalyticsExclusionService::COOKIE_NAME, $cookie->getValue())
            ->get(route('admin.profile'));
        $afterExclude->assertSee('ESCLUSO', false);

        $reactivateResponse = $this->actingAs($editor)
            ->withCookie(AnalyticsExclusionService::COOKIE_NAME, $cookie->getValue())
            ->post(route('admin.analytics.reactivate'));
        $reactivateCookie = collect($reactivateResponse->headers->getCookies())
            ->first(fn ($c) => $c->getName() === AnalyticsExclusionService::COOKIE_NAME);

        $this->assertLessThan(time(), $reactivateCookie->getExpiresTime());
    }
}
