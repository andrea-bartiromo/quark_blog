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

    public function test_profile_page_shows_analytics_active_when_not_excluded_and_analytics_is_enabled(): void
    {
        // "ATTIVO" e' un'affermazione sullo stato EFFETTIVO (env +
        // Measurement ID + cookie insieme), non solo sull'assenza del
        // cookie: va simulato un ambiente in cui GA4 si carica davvero,
        // altrimenti il default di test (APP_ENV=testing, GA4 disattivo
        // per ambiente) mostrerebbe correttamente "DISATTIVATO" — vedi
        // test_profile_page_shows_analytics_disabled_by_environment_even_without_the_cookie.
        config(['app.env' => 'production', 'analytics.enabled' => null]);

        $response = $this->actingAs($this->editor())->get(route('admin.profile'));

        $response->assertOk();
        $response->assertSee('ATTIVO', false);
        $response->assertSee('Escludi questo browser dalle statistiche');
        $response->assertDontSee('Riattiva tracciamento');
        $response->assertDontSee('DISATTIVATO', false);
    }

    public function test_profile_page_shows_analytics_excluded_with_the_activation_date_in_editorial_timezone(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null]);

        // 10 agosto, in piena ora legale: Europe/Rome e' UTC+2, quindi
        // 09:30 UTC deve comparire come 11:30, non 09:30 (bug corretto
        // dopo la review: config('app.timezone') e' UTC, un Carbon senza
        // conversione esplicita mostrerebbe l'orario UTC crudo).
        $response = $this->actingAs($this->editor())
            ->withCookie(AnalyticsExclusionService::COOKIE_NAME, '2026-08-10T09:30:00+00:00')
            ->get(route('admin.profile'));

        $response->assertOk();
        $response->assertSee('ESCLUSO', false);
        $response->assertSee('Riattiva tracciamento');
        $response->assertDontSee('Escludi questo browser dalle statistiche');
        $response->assertSee('Escluso dal 10/08/2026 11:30', false);
        $response->assertDontSee('09:30', false);
    }

    public function test_profile_page_shows_analytics_disabled_by_environment_even_without_the_cookie(): void
    {
        // Nessun cookie di esclusione, ma l'ambiente non e' production:
        // deve mostrare "DISATTIVATO", MAI "ATTIVO" — altrimenti la
        // pagina pensata per verificare visivamente lo stato darebbe
        // un'informazione scorretta (finding di review).
        config(['app.env' => 'testing', 'analytics.enabled' => null]);

        $response = $this->actingAs($this->editor())->get(route('admin.profile'));

        $response->assertOk();
        $response->assertSee('DISATTIVATO', false);
        $response->assertDontSee('<strong>ATTIVO</strong>', false);
        $response->assertDontSee('Escludi questo browser dalle statistiche');
        $response->assertDontSee('Riattiva tracciamento');
    }

    public function test_profile_page_shows_analytics_disabled_when_the_kill_switch_is_active(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => false]);

        $response = $this->actingAs($this->editor())->get(route('admin.profile'));

        $response->assertOk();
        $response->assertSee('DISATTIVATO', false);
    }

    public function test_profile_page_shows_analytics_disabled_without_a_measurement_id(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null, 'analytics.measurement_id' => '']);

        $response = $this->actingAs($this->editor())->get(route('admin.profile'));

        $response->assertOk();
        $response->assertSee('DISATTIVATO', false);
    }

    public function test_profile_page_prioritizes_excluded_over_disabled_when_both_apply(): void
    {
        // Il cookie e' presente E l'ambiente disattiva comunque GA4:
        // "ESCLUSO" resta il messaggio corretto (l'esclusione e' un dato
        // che l'amministratore ha impostato esplicitamente, piu'
        // specifico del semplice "l'ambiente non traccia nessuno").
        config(['app.env' => 'testing', 'analytics.enabled' => null]);

        $response = $this->actingAs($this->editor())
            ->withCookie(AnalyticsExclusionService::COOKIE_NAME, now()->toIso8601String())
            ->get(route('admin.profile'));

        $response->assertOk();
        $response->assertSee('ESCLUSO', false);
        $response->assertDontSee('DISATTIVATO', false);
    }

    public function test_excluding_then_reactivating_round_trips_back_to_active_on_the_profile_page(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null]);

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

        $afterReactivate = $this->actingAs($editor)->get(route('admin.profile'));
        $afterReactivate->assertSee('ATTIVO', false);
    }
}
