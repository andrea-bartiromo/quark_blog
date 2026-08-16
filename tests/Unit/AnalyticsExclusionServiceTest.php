<?php

namespace Tests\Unit;

use App\Services\AnalyticsExclusionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AnalyticsExclusionServiceTest extends TestCase
{
    private function service(): AnalyticsExclusionService
    {
        return new AnalyticsExclusionService;
    }

    private function request(array $cookies = [], bool $secure = false): Request
    {
        $request = Request::create($secure ? 'https://kairus.test/' : 'http://kairus.test/', 'GET', [], $cookies);

        return $request;
    }

    // ── isEnabledForEnvironment() ───────────────────────────────────

    public function test_enabled_by_default_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('analytics.enabled', null);

        $this->assertTrue($this->service()->isEnabledForEnvironment());
    }

    public function test_disabled_by_default_outside_production(): void
    {
        Config::set('app.env', 'local');
        Config::set('analytics.enabled', null);
        $this->assertFalse($this->service()->isEnabledForEnvironment());

        Config::set('app.env', 'testing');
        $this->assertFalse($this->service()->isEnabledForEnvironment());

        Config::set('app.env', 'staging');
        $this->assertFalse($this->service()->isEnabledForEnvironment());
    }

    public function test_explicit_override_true_wins_outside_production(): void
    {
        Config::set('app.env', 'local');
        Config::set('analytics.enabled', true);

        $this->assertTrue($this->service()->isEnabledForEnvironment());
    }

    public function test_explicit_override_false_wins_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('analytics.enabled', false);

        $this->assertFalse($this->service()->isEnabledForEnvironment());
    }

    public function test_explicit_override_accepts_string_booleans_from_env(): void
    {
        // config('analytics.enabled') legge env(), che restituisce sempre
        // stringhe per valori non nativamente booleani in .env — deve
        // essere interpretato correttamente, non solo un bool nativo.
        Config::set('app.env', 'local');

        Config::set('analytics.enabled', 'true');
        $this->assertTrue($this->service()->isEnabledForEnvironment());

        Config::set('analytics.enabled', 'false');
        $this->assertFalse($this->service()->isEnabledForEnvironment());
    }

    // ── isExcluded() / excludedSince() ──────────────────────────────

    public function test_not_excluded_without_the_cookie(): void
    {
        $request = $this->request();

        $this->assertFalse($this->service()->isExcluded($request));
        $this->assertNull($this->service()->excludedSince($request));
    }

    public function test_excluded_when_the_cookie_is_present(): void
    {
        $request = $this->request([AnalyticsExclusionService::COOKIE_NAME => '2026-08-10T12:00:00+00:00']);

        $this->assertTrue($this->service()->isExcluded($request));
        $this->assertSame('2026-08-10T12:00:00+00:00', $this->service()->excludedSince($request));
    }

    public function test_not_excluded_when_the_cookie_is_present_but_empty(): void
    {
        $request = $this->request([AnalyticsExclusionService::COOKIE_NAME => '']);

        $this->assertFalse($this->service()->isExcluded($request));
    }

    // ── shouldLoadAnalytics() ────────────────────────────────────────

    public function test_should_load_analytics_for_a_normal_visitor_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('analytics.enabled', null);
        Config::set('analytics.measurement_id', 'G-TESTID123');

        $this->assertTrue($this->service()->shouldLoadAnalytics($this->request()));
    }

    public function test_should_not_load_analytics_for_an_excluded_browser_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('analytics.enabled', null);
        Config::set('analytics.measurement_id', 'G-TESTID123');

        $request = $this->request([AnalyticsExclusionService::COOKIE_NAME => now()->toIso8601String()]);

        $this->assertFalse($this->service()->shouldLoadAnalytics($request));
    }

    public function test_should_not_load_analytics_outside_production_even_for_a_non_excluded_browser(): void
    {
        Config::set('app.env', 'testing');
        Config::set('analytics.enabled', null);
        Config::set('analytics.measurement_id', 'G-TESTID123');

        $this->assertFalse($this->service()->shouldLoadAnalytics($this->request()));
    }

    public function test_should_not_load_analytics_without_a_measurement_id_even_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('analytics.enabled', null);
        Config::set('analytics.measurement_id', null);

        $this->assertFalse($this->service()->shouldLoadAnalytics($this->request()));

        Config::set('analytics.measurement_id', '');
        $this->assertFalse($this->service()->shouldLoadAnalytics($this->request()));
    }

    public function test_explicit_enabled_override_loads_analytics_outside_production(): void
    {
        Config::set('app.env', 'local');
        Config::set('analytics.enabled', true);
        Config::set('analytics.measurement_id', 'G-TESTID123');

        $this->assertTrue($this->service()->shouldLoadAnalytics($this->request()));
    }

    // ── exclude() / reactivate() cookie attributes ──────────────────

    public function test_exclude_returns_a_secure_httponly_cookie_matching_the_request_scheme(): void
    {
        Config::set('analytics.exclusion_cookie_days', 730);

        $httpCookie = $this->service()->exclude($this->request(secure: false));
        $this->assertSame(AnalyticsExclusionService::COOKIE_NAME, $httpCookie->getName());
        $this->assertFalse($httpCookie->isSecure());
        $this->assertTrue($httpCookie->isHttpOnly());
        $this->assertSame('lax', $httpCookie->getSameSite());
        $this->assertNotEmpty($httpCookie->getValue());

        $httpsCookie = $this->service()->exclude($this->request(secure: true));
        $this->assertTrue($httpsCookie->isSecure());
    }

    public function test_exclude_cookie_duration_matches_configuration(): void
    {
        Config::set('analytics.exclusion_cookie_days', 30);

        $cookie = $this->service()->exclude($this->request());
        $expectedMinutes = 30 * 24 * 60;
        $actualMinutes = ($cookie->getExpiresTime() - time()) / 60;

        $this->assertEqualsWithDelta($expectedMinutes, $actualMinutes, 2);
    }

    public function test_exclude_cookie_value_is_a_timestamp_not_an_admin_identifier(): void
    {
        $cookie = $this->service()->exclude($this->request());

        $this->assertNotFalse(strtotime($cookie->getValue()), 'il valore del cookie deve essere un timestamp valido.');
    }

    public function test_reactivate_returns_a_cookie_that_expires_in_the_past(): void
    {
        $cookie = $this->service()->reactivate();

        $this->assertSame(AnalyticsExclusionService::COOKIE_NAME, $cookie->getName());
        $this->assertLessThan(time(), $cookie->getExpiresTime());
    }
}
