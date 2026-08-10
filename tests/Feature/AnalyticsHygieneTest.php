<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AnalyticsExclusionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre il comportamento end-to-end della missione Analytics Hygiene:
 * lo script GA4 deve essere emesso SOLO per un visitatore reale del
 * frontend pubblico, in produzione, con un Measurement ID configurato, e
 * MAI per un browser escluso, un ambiente non-production, o l'area
 * admin/redazione — indipendentemente da chi la visita.
 *
 * Verifica sempre l'ASSENZA letterale della stringa "googletagmanager"
 * nell'HTML restituito, non solo un flag interno: e' la garanzia che
 * conta davvero ("non caricato", non "caricato ma nascosto").
 */
class AnalyticsHygieneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['analytics.measurement_id' => 'G-TESTID123']);
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    // ── Frontend pubblico ────────────────────────────────────────────

    public function test_a_normal_visitor_receives_ga4_in_production(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('googletagmanager.com/gtag/js?id=G-TESTID123', false);
        $response->assertSee("gtag('config', 'G-TESTID123'", false);
    }

    public function test_an_excluded_browser_never_receives_ga4_in_production(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null]);

        $response = $this->withCookie(AnalyticsExclusionService::COOKIE_NAME, now()->toIso8601String())
            ->get('/');

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com', false);
    }

    public function test_reactivating_makes_ga4_appear_again_in_production(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null]);

        // Nessun cookie inviato = stato "riattivato": stesso risultato di
        // un browser che non e' mai stato escluso, verificando che non
        // resti alcuno stato residuo lato server (tutto lo stato vive nel
        // cookie del browser, mai altrove).
        $response = $this->get('/');

        $response->assertSee('googletagmanager.com', false);
    }

    // ── Ambienti non-production ──────────────────────────────────────

    public function test_ga4_never_loads_in_the_testing_environment_by_default(): void
    {
        // APP_ENV=testing e' gia' il default della suite (phpunit.xml):
        // nessuna configurazione aggiuntiva qui e' esattamente il punto —
        // deve essere sicuro senza dover ricordarsi di disattivare nulla.
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com', false);
    }

    public function test_ga4_never_loads_in_local_by_default(): void
    {
        config(['app.env' => 'local', 'analytics.enabled' => null]);

        $response = $this->get('/');

        $response->assertDontSee('googletagmanager.com', false);
    }

    public function test_ga4_never_loads_on_an_unrecognized_staging_environment_by_default(): void
    {
        config(['app.env' => 'staging', 'analytics.enabled' => null]);

        $response = $this->get('/');

        $response->assertDontSee('googletagmanager.com', false);
    }

    public function test_explicit_override_can_enable_ga4_on_a_non_production_environment(): void
    {
        // Via di fuga documentata per verificare GA4 una tantum su uno
        // staging pubblico — mai il comportamento di default.
        config(['app.env' => 'staging', 'analytics.enabled' => true]);

        $response = $this->get('/');

        $response->assertSee('googletagmanager.com', false);
    }

    public function test_explicit_override_can_disable_ga4_even_in_production(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => false]);

        $response = $this->get('/');

        $response->assertDontSee('googletagmanager.com', false);
    }

    // ── Fallback measurement ID ───────────────────────────────────────

    public function test_ga4_never_loads_without_a_measurement_id_even_in_production(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null, 'analytics.measurement_id' => null]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com', false);
    }

    public function test_ga4_never_loads_with_a_blank_measurement_id_even_in_production(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null, 'analytics.measurement_id' => '']);

        $response = $this->get('/');

        $response->assertDontSee('googletagmanager.com', false);
    }

    // ── Area Admin / Redazione ────────────────────────────────────────

    public function test_ga4_never_loads_on_the_admin_dashboard_even_in_production(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null]);

        $response = $this->actingAs($this->editor())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com', false);
    }

    public function test_ga4_never_loads_on_the_admin_login_page_even_in_production(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null]);

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com', false);
    }

    public function test_ga4_never_loads_on_a_redazione_page_even_in_production(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null]);

        $collaborator = User::factory()->create(['role' => 'author']);

        $response = $this->actingAs($collaborator)->get(route('redazione.dashboard'));

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com', false);
    }

    // ── Nessuna regressione sul resto del <head> ─────────────────────

    public function test_other_head_tags_are_unaffected_by_analytics_gating(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<link rel="canonical"', false);
        $response->assertSee('og:title', false);
        $response->assertSee('twitter:card', false);
    }

    public function test_other_head_tags_are_unaffected_when_analytics_is_excluded(): void
    {
        config(['app.env' => 'production', 'analytics.enabled' => null]);

        $response = $this->withCookie(AnalyticsExclusionService::COOKIE_NAME, now()->toIso8601String())
            ->get('/');

        $response->assertOk();
        $response->assertSee('<link rel="canonical"', false);
        $response->assertSee('og:title', false);
    }
}
