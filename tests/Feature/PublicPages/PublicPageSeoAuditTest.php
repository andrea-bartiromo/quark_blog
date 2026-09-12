<?php

namespace Tests\Feature\PublicPages;

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\User;
use App\Services\PublicPages\PublicPageSeoAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 22 (programma 100-cantieri Kairus). PublicPageSeoAudit itera
 * il catalogo di App\Services\PublicPages\PublicPageInventory (Cantiere
 * 21) e verifica, con un vero GET in-process per ciascun tipo di
 * pagina, gli stessi fatti HTTP-level che finora si controllavano solo
 * pagina per pagina in test separati (ArchivePaginationCanonicalTest,
 * HomeStructuredDataTest, ecc.).
 */
class PublicPageSeoAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_page_type_without_any_sample_is_skipped_not_failed(): void
    {
        // Ambiente appena migrato: nessun Articolo/Percorso esiste ancora,
        // quindi articolo/autore/percorso non hanno un sample_url (vedi
        // PublicPageInventoryTest).
        $pages = collect(app(PublicPageSeoAudit::class)->audit())->keyBy('key');

        foreach (['articolo', 'autore', 'percorso'] as $key) {
            $this->assertFalse($pages[$key]['checked']);
            $this->assertSame([], $pages[$key]['findings']);
            $this->assertNull($pages[$key]['http_status']);
        }
    }

    public function test_home_page_passes_every_check_with_no_findings(): void
    {
        $pages = collect(app(PublicPageSeoAudit::class)->audit())->keyBy('key');
        $home = $pages['home'];

        $this->assertTrue($home['checked']);
        $this->assertSame(200, $home['http_status']);
        $this->assertTrue($home['title_present']);
        $this->assertTrue($home['description_present']);
        // La home normalizza deliberatamente il proprio canonical con una
        // barra finale (resources/views/home.blade.php), a differenza di
        // route('home'): un confronto solo a meno della barra finale,
        // stessa tolleranza applicata da PublicPageSeoAudit stesso.
        $this->assertSame(rtrim(route('home'), '/'), rtrim($home['canonical'], '/'));
        $this->assertGreaterThan(0, $home['json_ld_blocks']);
        $this->assertSame([], $home['findings']);
    }

    public function test_categoria_sample_passes_every_check_with_no_findings(): void
    {
        $pages = collect(app(PublicPageSeoAudit::class)->audit())->keyBy('key');
        $categoria = $pages['categoria'];

        $this->assertTrue($categoria['checked']);
        $this->assertSame(200, $categoria['http_status']);
        $this->assertSame($categoria['sample_url'], $categoria['canonical']);
        $this->assertGreaterThan(0, $categoria['json_ld_blocks']);
        $this->assertSame([], $categoria['findings']);
    }

    public function test_articolo_and_autore_samples_pass_every_check_once_a_published_article_exists(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Un articolo pubblicato per audit SEO',
            'slug' => 'un-articolo-pubblicato-per-audit-seo',
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
            'views' => 0,
        ]);

        $pages = collect(app(PublicPageSeoAudit::class)->audit())->keyBy('key');

        foreach (['articolo', 'autore'] as $key) {
            $this->assertTrue($pages[$key]['checked'], "expected {$key} to be checked");
            $this->assertSame(200, $pages[$key]['http_status']);
            $this->assertSame($pages[$key]['sample_url'], $pages[$key]['canonical']);
            $this->assertSame([], $pages[$key]['findings'], "expected no findings for {$key}");
        }

        $this->assertGreaterThan(0, $pages['articolo']['json_ld_blocks']);
    }

    /**
     * Finding Codex (P1, PR #570): senza un modo per
     * ArticleController::show() di distinguere il GET in-process
     * dell'audit da una visita reale, ogni esecuzione dell'audit (pensato
     * per essere di sola lettura e ripetibile a piacere) avrebbe
     * silenziosamente incrementato le analytics reali dell'articolo
     * campione — sia il contatore lifetime sia il log per-pageview.
     */
    public function test_auditing_an_article_never_increments_its_real_view_analytics(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo per verifica non contaminazione analytics',
            'slug' => 'articolo-verifica-non-contaminazione-analytics',
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
            'views' => 0,
        ]);

        app(PublicPageSeoAudit::class)->audit();
        // Un secondo giro (l'audit è pensato per essere eseguito quante
        // volte si vuole): se il marcatore non funzionasse, ogni
        // esecuzione aggiungerebbe un'altra view.
        app(PublicPageSeoAudit::class)->audit();

        $this->assertSame(0, $article->fresh()->views);
        $this->assertSame(0, ArticleView::where('article_id', $article->id)->count());
    }

    /**
     * Scoperta reale, non fabbricata per questo test: resources/views/
     * ricerca.blade.php non imposta mai @section('canonical', ...), a
     * differenza di ogni altra pagina statica. L'audit deve segnalarlo
     * come finding genuino, non ignorarlo.
     */
    public function test_a_real_page_missing_the_canonical_section_is_flagged(): void
    {
        $pages = collect(app(PublicPageSeoAudit::class)->audit())->keyBy('key');
        $ricerca = $pages['ricerca'];

        $this->assertTrue($ricerca['checked']);
        $this->assertSame(200, $ricerca['http_status']);
        $this->assertNull($ricerca['canonical']);
        $this->assertContains('Tag <link rel="canonical"> assente.', $ricerca['findings']);
    }

    /**
     * Le pagine statiche/legali (privacy, cookie, termini, ecc.) non
     * hanno mai avuto un partial JSON-LD dedicato: l'audit non deve
     * inventare un'aspettativa che il repository non ha mai avuto,
     * altrimenti il rumore nasconderebbe le lacune reali sulle pagine
     * per cui i dati strutturati sono davvero attesi.
     */
    public function test_static_legal_pages_are_never_flagged_for_missing_json_ld(): void
    {
        $pages = collect(app(PublicPageSeoAudit::class)->audit())->keyBy('key');

        foreach (['privacy', 'termini', 'contatti', 'metodologia'] as $key) {
            $this->assertTrue($pages[$key]['checked']);
            $findingsMentioningJsonLd = array_filter(
                $pages[$key]['findings'],
                fn (string $finding) => str_contains($finding, 'JSON-LD')
            );
            $this->assertSame([], $findingsMentioningJsonLd, "expected no JSON-LD finding for {$key}");
        }
    }

    public function test_percorsi_index_has_json_ld_and_no_finding(): void
    {
        $pages = collect(app(PublicPageSeoAudit::class)->audit())->keyBy('key');
        $percorsiIndex = $pages['percorsi_index'];

        $this->assertTrue($percorsiIndex['checked']);
        $this->assertGreaterThan(0, $percorsiIndex['json_ld_blocks']);
        $this->assertSame([], array_filter(
            $percorsiIndex['findings'],
            fn (string $finding) => str_contains($finding, 'JSON-LD')
        ));
    }
}
