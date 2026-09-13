<?php

namespace Tests\Feature\PublicPages;

use App\Models\PublicHealthBaseline;
use App\Services\PublicPages\PublicHealthBaselineService;
use App\Services\PublicPages\PublicHealthDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 35 (programma 100-cantieri Kairus), dipende dal Cantiere 30.
 * PublicHealthBaselineService non ricalcola mai i conteggi di un dominio
 * (quello resta compito esclusivo di PublicHealthDashboardService): qui
 * si prova solo che li persista/confronti correttamente, un mese/dominio
 * alla volta, senza mai combinare denominatori tra domini diversi.
 */
class PublicHealthBaselineServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PublicHealthBaselineService
    {
        return app(PublicHealthBaselineService::class);
    }

    /** @return array<string, array<string, mixed>> */
    private function fakeSnapshotDomains(array $overrides = []): array
    {
        $base = fn (array $extra = []) => array_merge([
            'finding_count' => 3,
            'open_count' => 2,
            'dismissed_count' => 1,
            'high_open_count' => 1,
            'checked_count' => 10,
            'total_count' => 12,
        ], $extra);

        $domains = [];
        foreach (PublicHealthDashboardService::REAL_DOMAIN_KEYS as $key) {
            $domains[$key] = $base($overrides[$key] ?? []);
        }

        return $domains;
    }

    public function test_records_a_baseline_row_for_each_of_the_six_real_domains(): void
    {
        $recorded = $this->service()->recordMonth($this->fakeSnapshotDomains(), '2026-08');

        $this->assertCount(6, $recorded);
        $this->assertSame(6, PublicHealthBaseline::query()->where('period', '2026-08')->count());

        $seo = PublicHealthBaseline::query()->where(['domain' => 'seo', 'period' => '2026-08'])->first();
        $this->assertNotNull($seo);
        $this->assertSame(2, $seo->open_count);
        $this->assertSame(1, $seo->dismissed_count);
        $this->assertSame(1, $seo->high_open_count);
        $this->assertSame(3, $seo->finding_count);
        $this->assertSame(10, $seo->checked_count);
        $this->assertSame(12, $seo->total_count);
    }

    public function test_a_domain_without_a_checked_or_total_count_is_stored_as_null_not_zero(): void
    {
        $domains = $this->fakeSnapshotDomains([
            'not_found' => ['checked_count' => null, 'total_count' => null],
        ]);
        // not_found non espone affatto queste chiavi nello snapshot reale.
        unset($domains['not_found']['checked_count'], $domains['not_found']['total_count']);

        $this->service()->recordMonth($domains, '2026-08');

        $notFound = PublicHealthBaseline::query()->where(['domain' => 'not_found', 'period' => '2026-08'])->first();
        $this->assertNull($notFound->checked_count);
        $this->assertNull($notFound->total_count);
    }

    public function test_recording_twice_in_the_same_month_updates_instead_of_duplicating(): void
    {
        $this->service()->recordMonth($this->fakeSnapshotDomains(['seo' => ['open_count' => 2]]), '2026-08');
        $this->service()->recordMonth($this->fakeSnapshotDomains(['seo' => ['open_count' => 5]]), '2026-08');

        $this->assertSame(6, PublicHealthBaseline::query()->where('period', '2026-08')->count());

        $seo = PublicHealthBaseline::query()->where(['domain' => 'seo', 'period' => '2026-08'])->first();
        $this->assertSame(5, $seo->open_count);
    }

    public function test_trend_for_returns_null_when_no_previous_baseline_exists(): void
    {
        $trend = $this->service()->trendFor('seo', ['open_count' => 4, 'high_open_count' => 1], '2026-09');

        $this->assertNull($trend);
    }

    public function test_trend_for_computes_the_delta_against_the_most_recent_prior_month(): void
    {
        $this->service()->recordMonth($this->fakeSnapshotDomains([
            'seo' => ['open_count' => 10, 'high_open_count' => 4, 'checked_count' => 40, 'total_count' => 50],
        ]), '2026-07');
        $this->service()->recordMonth($this->fakeSnapshotDomains([
            'seo' => ['open_count' => 6, 'high_open_count' => 2, 'checked_count' => 45, 'total_count' => 50],
        ]), '2026-08');

        $trend = $this->service()->trendFor('seo', ['open_count' => 3, 'high_open_count' => 1], '2026-09');

        $this->assertSame('2026-08', $trend['period']);
        $this->assertSame(-3, $trend['open_count_delta']);
        $this->assertSame(-1, $trend['high_open_count_delta']);
        $this->assertSame(6, $trend['previous_open_count']);
        $this->assertSame(45, $trend['previous_checked_count']);
        $this->assertSame(50, $trend['previous_total_count']);
    }

    public function test_trend_for_never_compares_against_the_current_month_itself(): void
    {
        $this->service()->recordMonth($this->fakeSnapshotDomains(['seo' => ['open_count' => 99]]), '2026-09');

        $trend = $this->service()->trendFor('seo', ['open_count' => 3, 'high_open_count' => 1], '2026-09');

        $this->assertNull($trend);
    }

    public function test_trend_for_never_mixes_counts_from_a_different_domain(): void
    {
        $this->service()->recordMonth($this->fakeSnapshotDomains([
            'seo' => ['open_count' => 100, 'checked_count' => 1000, 'total_count' => 1000],
            'wcag' => ['open_count' => 1, 'checked_count' => 5, 'total_count' => 5],
        ]), '2026-08');

        $trend = $this->service()->trendFor('wcag', ['open_count' => 1, 'high_open_count' => 0], '2026-09');

        $this->assertSame(1, $trend['previous_open_count']);
        $this->assertSame(5, $trend['previous_checked_count']);
        $this->assertSame(5, $trend['previous_total_count']);
    }
}
