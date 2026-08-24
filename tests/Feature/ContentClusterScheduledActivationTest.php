<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ArticlePathNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Percorsi Scheduled Activation V1: proves that every public consumer
 * (ContentClusterController index/show, HomeController, sitemap,
 * ArticlePathNavigation) now reads ContentCluster::publiclyVisible()
 * instead of the legacy active() scope, and that the admin scheduling
 * form persists/display publish_at correctly through the Europe/Rome
 * editorial timezone. The model-level policy itself (isPubliclyVisible()/
 * scopePubliclyVisible()) is already fully covered by
 * ContentClusterPubliclyVisibleScopeTest — this suite is about the wiring.
 */
class ContentClusterScheduledActivationTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editor = User::factory()->create(['role' => 'editor']);
    }

    private function article(string $title, string $status = Article::STATUS_PUBLISHED, $publishedAt = null): Article
    {
        return Article::create([
            'user_id' => $this->editor->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $publishedAt ?? ($status === Article::STATUS_PUBLISHED ? now()->subMinute() : null),
        ]);
    }

    // ── A/B/E: index + direct slug 404 for non-public clusters ──────

    public function test_a_inactive_cluster_with_no_publish_at_is_absent_from_index_and_404s_directly(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'scenario-a', 'name' => 'Scenario A', 'is_active' => false, 'publish_at' => null]);

        $this->get(route('percorsi.index'))->assertOk()->assertDontSee('Scenario A');
        $this->get(route('percorsi.show', 'scenario-a'))->assertNotFound();
    }

    public function test_b_inactive_cluster_with_a_past_publish_at_stays_private(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'scenario-b', 'name' => 'Scenario B', 'is_active' => false, 'publish_at' => now()->subDay()]);

        $this->get(route('percorsi.index'))->assertOk()->assertDontSee('Scenario B');
        $this->get(route('percorsi.show', 'scenario-b'))->assertNotFound();
    }

    public function test_e_active_cluster_scheduled_in_the_future_is_absent_from_index_and_404s_directly(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'scenario-e', 'name' => 'Scenario E', 'is_active' => true, 'publish_at' => now()->addWeek()]);

        $this->get(route('percorsi.index'))->assertOk()->assertDontSee('Scenario E');
        $this->get(route('percorsi.show', 'scenario-e'))->assertNotFound();
    }

    // ── C/D: publicly reachable ──────────────────────────────────────

    public function test_c_active_cluster_with_no_publish_at_is_public_immediately(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'scenario-c', 'name' => 'Scenario C', 'is_active' => true, 'publish_at' => null]);

        $this->get(route('percorsi.index'))->assertOk()->assertSee('Scenario C');
        $this->get(route('percorsi.show', 'scenario-c'))->assertOk();
    }

    public function test_d_active_cluster_with_a_past_publish_at_is_public(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'scenario-d', 'name' => 'Scenario D', 'is_active' => true, 'publish_at' => now()->subHour()]);

        $this->get(route('percorsi.index'))->assertOk()->assertSee('Scenario D');
        $this->get(route('percorsi.show', 'scenario-d'))->assertOk();
    }

    // ── F: time travel across the exact publish_at boundary ─────────

    public function test_f_cluster_becomes_public_automatically_when_publish_at_elapses_without_any_mutation(): void
    {
        $target = Carbon::parse('2026-09-10 12:00:00', 'UTC');
        Carbon::setTestNow($target->clone()->subSecond());

        $cluster = ContentCluster::factory()->create(['slug' => 'scenario-f', 'name' => 'Scenario F', 'is_active' => true, 'publish_at' => $target]);

        $this->get(route('percorsi.show', 'scenario-f'))->assertNotFound();

        Carbon::setTestNow($target);

        $this->get(route('percorsi.show', 'scenario-f'))->assertOk();

        Carbon::setTestNow();
    }

    // ── G: ArticlePathNavigation must not leak a scheduled cluster ──

    public function test_g_scheduled_cluster_produces_no_continuation_box_on_the_article_page(): void
    {
        $article = $this->article('Articolo in percorso programmato');
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->addDay()]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        $this->get(route('articolo', $article->slug))
            ->assertOk()
            ->assertDontSee('Continua il percorso');

        $this->assertNull(app(ArticlePathNavigation::class)->forArticle($article->fresh()));
    }

    public function test_g_inactive_cluster_still_produces_no_navigation_result_via_the_service_directly(): void
    {
        $article = $this->article('Articolo in percorso inattivo');
        $cluster = ContentCluster::factory()->create(['is_active' => false]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        $this->assertNull(app(ArticlePathNavigation::class)->forArticle($article->fresh()));
    }

    public function test_g_article_in_both_a_scheduled_and_a_public_cluster_navigates_only_the_public_one(): void
    {
        $article = $this->article('Articolo in due percorsi');
        $scheduled = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->addDay()]);
        $public = ContentCluster::factory()->create(['slug' => 'scenario-g-overlap', 'is_active' => true, 'publish_at' => null]);
        $scheduled->articles()->attach($article->id, ['position' => 10, 'is_primary' => false]);
        $public->articles()->attach($article->id, ['position' => 10, 'is_primary' => false]);

        $navigation = app(ArticlePathNavigation::class)->forArticle($article->fresh());

        $this->assertNotNull($navigation);
        $this->assertSame($public->id, $navigation['cluster']->id);
    }

    // ── H: continuous-prefix semantics still apply once activated ───

    public function test_h_continuous_prefix_still_stops_at_the_first_non_public_member_once_the_cluster_is_public(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'scenario-h', 'name' => 'Scenario H', 'is_active' => true, 'publish_at' => now()->subMinute()]);
        $first = $this->article('Primo passo', Article::STATUS_PUBLISHED, now()->subDay());
        $gap = $this->article('Passo nascosto', Article::STATUS_SCHEDULED, now()->addDay());
        $afterGap = $this->article('Passo dopo il gap', Article::STATUS_PUBLISHED, now()->subHour());
        $cluster->articles()->attach([
            $first->id => ['position' => 10],
            $gap->id => ['position' => 20],
            $afterGap->id => ['position' => 30],
        ]);

        $response = $this->get(route('percorsi.show', 'scenario-h'))->assertOk();
        // Il ticker sitewide "In evidenza" (layouts/app.blade.php, FUORI da
        // <main>) mostra qualunque Article::published() reale, incluso
        // "Passo dopo il gap": è un articolo pubblicato legittimo, non una
        // fuga di dati del gap del Percorso. L'asserzione va quindi
        // ristretta al contenuto di <main>, non all'intero body.
        preg_match('/<main id="main-content".*?<\/main>/s', $response->getContent(), $matches);
        $mainHtml = $matches[0] ?? '';
        $this->assertStringContainsString('Primo passo', $mainHtml);
        $this->assertStringNotContainsString('Passo nascosto', $mainHtml);
        $this->assertStringNotContainsString('Passo dopo il gap', $mainHtml);
    }

    // ── Sitemap + home wiring (future-scheduled must be excluded) ───

    public function test_sitemap_excludes_a_future_scheduled_cluster(): void
    {
        $public = ContentCluster::factory()->create(['slug' => 'sitemap-pubblico', 'is_active' => true, 'publish_at' => null]);
        $scheduled = ContentCluster::factory()->create(['slug' => 'sitemap-programmato', 'is_active' => true, 'publish_at' => now()->addWeek()]);
        $public->articles()->attach($this->article('Sitemap pubblico')->id, ['position' => 10]);
        $scheduled->articles()->attach($this->article('Sitemap programmato')->id, ['position' => 10]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/percorsi/sitemap-pubblico</loc>', false)
            ->assertDontSee('/percorsi/sitemap-programmato</loc>', false);
    }

    public function test_homepage_excludes_a_future_scheduled_cluster(): void
    {
        $public = ContentCluster::factory()->create(['slug' => 'home-pubblico', 'name' => 'Home Pubblico', 'is_active' => true, 'publish_at' => null, 'sort_order' => 1]);
        $scheduled = ContentCluster::factory()->create(['slug' => 'home-programmato', 'name' => 'Home Programmato', 'is_active' => true, 'publish_at' => now()->addWeek(), 'sort_order' => 2]);
        $public->articles()->attach($this->article('Home pubblico')->id, ['position' => 10]);
        $scheduled->articles()->attach($this->article('Home programmato')->id, ['position' => 10]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Home Pubblico')
            ->assertDontSee('Home Programmato');
    }

    // ── Admin form: create/update with scheduling fields ─────────────

    public function test_admin_can_create_a_cluster_with_a_future_publish_date_and_time(): void
    {
        $response = $this->actingAs($this->editor)->post(route('admin.content-clusters.store'), [
            'name' => 'Percorso programmato',
            'slug' => '',
            'is_active' => '1',
            'publish_date' => '2026-12-24',
            'publish_time' => '18:30',
            'lifecycle_status' => 'updating',
        ]);

        $cluster = ContentCluster::where('slug', 'percorso-programmato')->firstOrFail();
        $response->assertRedirect(route('admin.content-clusters.edit', $cluster));
        $this->assertNotNull($cluster->publish_at);
        $this->assertSame('2026-12-24 17:30:00', $cluster->publish_at->format('Y-m-d H:i:s'));
        $this->assertFalse($cluster->isPubliclyVisible());
    }

    public function test_admin_can_clear_publish_at_to_restore_immediate_visibility(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->addMonth()]);

        $response = $this->actingAs($this->editor)->put(route('admin.content-clusters.update', $cluster), [
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'is_active' => '1',
            'publish_date' => '',
            'publish_time' => '',
            'lifecycle_status' => 'updating',
        ]);

        $response->assertRedirect(route('admin.content-clusters.edit', $cluster));
        $this->assertNull($cluster->fresh()->publish_at);
        $this->assertTrue($cluster->fresh()->isPubliclyVisible());
    }

    public function test_admin_form_rejects_a_date_without_a_matching_time(): void
    {
        $response = $this->actingAs($this->editor)->post(route('admin.content-clusters.store'), [
            'name' => 'Percorso incompleto',
            'slug' => '',
            'is_active' => '1',
            'publish_date' => '2026-12-24',
            'publish_time' => '',
            'lifecycle_status' => 'updating',
        ]);

        $response->assertSessionHasErrors('publish_time');
    }

    public function test_admin_form_rejects_a_malformed_date(): void
    {
        $response = $this->actingAs($this->editor)->post(route('admin.content-clusters.store'), [
            'name' => 'Percorso data invalida',
            'slug' => '',
            'is_active' => '1',
            'publish_date' => 'not-a-date',
            'publish_time' => '18:30',
            'lifecycle_status' => 'updating',
        ]);

        $response->assertSessionHasErrors('publish_date');
    }

    public function test_admin_form_allows_a_past_publish_date(): void
    {
        $response = $this->actingAs($this->editor)->post(route('admin.content-clusters.store'), [
            'name' => 'Percorso data passata',
            'slug' => '',
            'is_active' => '1',
            'publish_date' => '2020-01-01',
            'publish_time' => '09:00',
            'lifecycle_status' => 'updating',
        ]);

        $cluster = ContentCluster::where('slug', 'percorso-data-passata')->firstOrFail();
        $response->assertRedirect(route('admin.content-clusters.edit', $cluster));
        $this->assertTrue($cluster->isPubliclyVisible());
    }

    public function test_admin_edit_page_shows_the_correct_visibility_badge(): void
    {
        $inactive = ContentCluster::factory()->create(['is_active' => false, 'publish_at' => null]);
        $scheduled = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->addWeek()]);
        $public = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => null]);

        $this->actingAs($this->editor)->get(route('admin.content-clusters.edit', $inactive))->assertOk()->assertSee('Inattivo');
        $this->actingAs($this->editor)->get(route('admin.content-clusters.edit', $scheduled))->assertOk()->assertSee('Programmato');
        $this->actingAs($this->editor)->get(route('admin.content-clusters.edit', $public))->assertOk()->assertSee('Pubblico');
    }

    // ── Timezone / DST regression ─────────────────────────────────────

    public function test_europe_rome_input_during_summer_dst_converts_to_the_correct_utc_instant(): void
    {
        // 2026-07-15 10:00 Europe/Rome is CEST (+02:00) => 08:00 UTC.
        $response = $this->actingAs($this->editor)->post(route('admin.content-clusters.store'), [
            'name' => 'Percorso estate DST',
            'slug' => '',
            'is_active' => '1',
            'publish_date' => '2026-07-15',
            'publish_time' => '10:00',
            'lifecycle_status' => 'updating',
        ]);

        $cluster = ContentCluster::where('slug', 'percorso-estate-dst')->firstOrFail();
        $response->assertRedirect(route('admin.content-clusters.edit', $cluster));
        $this->assertSame('2026-07-15 08:00:00', $cluster->publish_at->format('Y-m-d H:i:s'));
    }

    public function test_europe_rome_input_during_winter_standard_time_converts_to_the_correct_utc_instant(): void
    {
        // 2026-01-15 10:00 Europe/Rome is CET (+01:00) => 09:00 UTC.
        $response = $this->actingAs($this->editor)->post(route('admin.content-clusters.store'), [
            'name' => 'Percorso inverno CET',
            'slug' => '',
            'is_active' => '1',
            'publish_date' => '2026-01-15',
            'publish_time' => '10:00',
            'lifecycle_status' => 'updating',
        ]);

        $cluster = ContentCluster::where('slug', 'percorso-inverno-cet')->firstOrFail();
        $response->assertRedirect(route('admin.content-clusters.edit', $cluster));
        $this->assertSame('2026-01-15 09:00:00', $cluster->publish_at->format('Y-m-d H:i:s'));
    }

    /**
     * publishAtForEditors() round-trips back to the exact wall-clock time
     * an editor entered, independent of DST — the inverse of the two tests
     * above, proving the display path is consistent with the storage path.
     */
    public function test_publish_at_for_editors_round_trips_the_original_rome_wall_clock_time(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'publish_at' => \Carbon\Carbon::createFromFormat('Y-m-d H:i', '2026-07-15 10:00', 'Europe/Rome')->utc(),
        ]);

        $this->assertSame('2026-07-15 10:00', $cluster->publishAtForEditors()->format('Y-m-d H:i'));
    }
}
