<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\Search\TrovaEntitySearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Cantiere 48 (programma "100 cantieri Kairus", dipende dai Cantieri
 * 46-47): anteprima admin di sola lettura per un Percorso non ancora
 * pubblico (Admin\ContentClusterController::preview(), via
 * ContentClusterShowPageData) — stesso pattern già stabilito da
 * Admin\CategoryController::preview() (Cantiere 11) — più l'audit
 * end-to-end che il pacchetto "Mente e comportamento" (Cantiere 46)
 * resti escluso da sitemap e ricerca interna mentre è previewabile in
 * admin, e che il suo canonical punti alla vera URL pubblica futura.
 */
class ContentClusterAdminPreviewTest extends TestCase
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

    public function test_editor_can_preview_an_inactive_cluster_that_404s_publicly(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Percorso Da Rivedere',
            'slug' => 'percorso-da-rivedere',
            'is_active' => false,
        ]);
        $article = Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Articolo del percorso in bozza',
            'slug' => 'articolo-del-percorso-in-bozza',
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        // La pagina pubblica reale continua a rispondere 404.
        $this->get(route('percorsi.show', $cluster->slug))->assertNotFound();

        $response = $this->actingAs($this->editor())->get(route('admin.content-clusters.preview', $cluster));

        $response->assertOk();
        $response->assertSee('Anteprima amministrativa', false);
        $response->assertSee('Articolo del percorso in bozza');
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    public function test_guest_cannot_reach_the_preview_route(): void
    {
        $cluster = ContentCluster::create(['name' => 'Percorso Protetto', 'slug' => 'percorso-protetto', 'is_active' => false]);

        $this->get(route('admin.content-clusters.preview', $cluster))->assertRedirect(route('login'));
    }

    public function test_public_percorso_page_is_unaffected_by_the_preview_route_existing(): void
    {
        $article = Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Articolo percorso pubblico invariato',
            'slug' => 'articolo-percorso-pubblico-invariato',
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);
        $cluster = ContentCluster::create([
            'name' => 'Percorso Pubblico Invariato',
            'slug' => 'percorso-pubblico-invariato',
            'is_active' => true,
            'short_description' => 'Breve',
            'description' => 'Descrizione',
        ]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        $cluster->update(['pillar_article_id' => $article->id]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertDontSee('Anteprima amministrativa', false);
        $response->assertDontSee('name="robots" content="noindex,nofollow"', false);
    }

    /**
     * Audit end-to-end (il cuore del Cantiere 48): il pacchetto "Mente e
     * comportamento" provisionato dal Cantiere 46 deve restare escluso
     * da sitemap E ricerca interna mentre resta is_active=false, pur
     * essendo previewabile in admin con un canonical che punta già alla
     * sua vera URL pubblica futura — mai un URL diverso o admin-only.
     *
     * Un articolo pubblicato reale viene collegato deliberatamente: un
     * pacchetto vuoto sarebbe escluso da entrambe le superfici comunque
     * (zero membri pubblici), senza provare che l'esclusione dipenda
     * davvero da is_active=false — stessa correzione già applicata al
     * test equivalente del Cantiere 46
     * (ProvisionNonPublicContentClusterPackageTest).
     */
    public function test_the_provisioned_mente_e_comportamento_package_stays_excluded_from_sitemap_and_search_while_previewable(): void
    {
        Artisan::call('content-clusters:provision-non-public-package mente-e-comportamento "Mente e comportamento" --apply');
        $cluster = ContentCluster::where('slug', 'mente-e-comportamento')->firstOrFail();

        $article = Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Articolo mente e comportamento',
            'slug' => 'articolo-mente-e-comportamento',
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'salute',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/percorsi/mente-e-comportamento</loc>', false);

        $results = app(TrovaEntitySearchService::class)->search('Mente e comportamento');
        $this->assertTrue($results['percorsi']->where('slug', 'mente-e-comportamento')->isEmpty());

        $response = $this->actingAs($this->editor())->get(route('admin.content-clusters.preview', $cluster));
        $response->assertOk();
        $response->assertSee('Anteprima amministrativa', false);
        $response->assertSee(route('percorsi.show', 'mente-e-comportamento'), false);
    }

    /**
     * Codex, PR #606 (P1): l'anteprima admin non deve mai registrare
     * traffico editoriale come traffico pubblico reale in GA4, anche
     * sotto una configurazione production-like in cui una pagina
     * pubblica comparabile caricherebbe regolarmente lo script gtag.js
     * (AnalyticsExclusionService::shouldLoadAnalytics(), $previewMode).
     */
    public function test_the_preview_response_never_loads_analytics_even_under_production_like_config(): void
    {
        Config::set('app.env', 'production');
        Config::set('analytics.enabled', null);
        Config::set('analytics.measurement_id', 'G-TESTID123');

        $article = Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Articolo per audit analytics anteprima',
            'slug' => 'articolo-audit-analytics-anteprima',
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);
        $publicCluster = ContentCluster::create([
            'name' => 'Percorso Pubblico Per Analytics',
            'slug' => 'percorso-pubblico-per-analytics',
            'is_active' => true,
            'short_description' => 'Breve',
            'description' => 'Descrizione',
        ]);
        $publicCluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        $publicCluster->update(['pillar_article_id' => $article->id]);

        $draftCluster = ContentCluster::create([
            'name' => 'Percorso Non Pubblico Per Analytics',
            'slug' => 'percorso-non-pubblico-per-analytics',
            'is_active' => false,
        ]);
        $draftCluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        $publicResponse = $this->get(route('percorsi.show', $publicCluster->slug));
        $publicResponse->assertOk();
        $publicResponse->assertSee('googletagmanager.com/gtag/js', false);

        $previewResponse = $this->actingAs($this->editor())
            ->get(route('admin.content-clusters.preview', $draftCluster));
        $previewResponse->assertOk();
        $previewResponse->assertDontSee('googletagmanager.com/gtag/js', false);
    }

    /**
     * Codex, PR #606 (P2): l'anteprima admin usa route model binding
     * semplice, quindi raggiunge anche un Percorso già "in aggiornamento"
     * (non solo un pacchetto non pubblico) — in quel ramo la pagina
     * pubblica reale mostra un vero form di iscrizione (POST verso
     * percorsi.subscribe). L'anteprima, di sola lettura, deve sempre
     * sostituirlo con un avviso statico, mai renderizzare il form live.
     */
    public function test_the_preview_response_never_renders_the_live_subscribe_form(): void
    {
        $article = Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Articolo percorso in aggiornamento',
            'slug' => 'articolo-percorso-in-aggiornamento',
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);
        $cluster = ContentCluster::create([
            'name' => 'Percorso In Aggiornamento',
            'slug' => 'percorso-in-aggiornamento',
            'is_active' => true,
            'short_description' => 'Breve',
            'description' => 'Descrizione',
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        $cluster->update(['pillar_article_id' => $article->id]);

        $publicResponse = $this->get(route('percorsi.show', $cluster->slug));
        $publicResponse->assertOk();
        $publicResponse->assertSee('path-subscribe__form', false);
        $publicResponse->assertSee(route('percorsi.subscribe', $cluster->slug), false);

        $previewResponse = $this->actingAs($this->editor())
            ->get(route('admin.content-clusters.preview', $cluster));
        $previewResponse->assertOk();
        $previewResponse->assertSee('Iscrizione disabilitata in anteprima amministrativa.');
        $previewResponse->assertDontSee('path-subscribe__form', false);
        $previewResponse->assertDontSee(route('percorsi.subscribe', $cluster->slug), false);
    }
}
