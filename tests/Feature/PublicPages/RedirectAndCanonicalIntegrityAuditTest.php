<?php

namespace Tests\Feature\PublicPages;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\PublicPages\InProcessPageFetcher;
use App\Services\PublicPages\RedirectAndCanonicalIntegrityAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Cantiere 23 (programma 100-cantieri Kairus). RedirectAndCanonicalIntegrityAudit
 * estende il controllo di auto-riferimento del canonical già fatto da
 * PublicPageSeoAudit (Cantiere 22) — un solo campione per tipo — a OGNI
 * istanza reale raggiungibile, e verifica in più il meccanismo di
 * redirect esistente per i vecchi slug articolo.
 */
class RedirectAndCanonicalIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    public function test_reports_no_redirects_when_none_are_registered(): void
    {
        $redirects = app(RedirectAndCanonicalIntegrityAudit::class)->auditRedirects();

        $this->assertSame([], $redirects);
    }

    public function test_a_redirect_to_a_still_published_article_has_no_findings(): void
    {
        $article = $this->publishedArticle(['slug' => 'slug-vecchio']);
        $article->update(['slug' => 'slug-nuovo']);

        $redirects = app(RedirectAndCanonicalIntegrityAudit::class)->auditRedirects();

        $this->assertCount(1, $redirects);
        $this->assertSame('slug-vecchio', $redirects[0]['old_slug']);
        $this->assertSame(301, $redirects[0]['http_status']);
        $this->assertSame([], $redirects[0]['findings']);
    }

    /**
     * Un vecchio slug il cui articolo non è più pubblicato (bozza) risponde
     * correttamente 404 — ArticleController::show() non reindirizza mai
     * verso un articolo non pubblicamente visibile. Stato atteso e
     * documentato, mai un finding.
     */
    public function test_a_redirect_to_an_unpublished_article_reports_404_without_findings(): void
    {
        $article = $this->publishedArticle(['slug' => 'slug-vecchio-2']);
        $article->update(['slug' => 'slug-nuovo-2']);
        $article->update(['status' => Article::STATUS_DRAFT]);

        $redirects = app(RedirectAndCanonicalIntegrityAudit::class)->auditRedirects();

        $this->assertCount(1, $redirects);
        $this->assertSame(404, $redirects[0]['http_status']);
        $this->assertSame([], $redirects[0]['findings']);
    }

    public function test_a_redirect_with_an_unexpected_target_status_is_flagged(): void
    {
        $article = $this->publishedArticle(['slug' => 'slug-vecchio-3']);
        $article->update(['slug' => 'slug-nuovo-3']);

        $fake = new class extends InProcessPageFetcher
        {
            public function fetch(string $url): Response
            {
                if (str_contains($url, 'slug-vecchio-3')) {
                    return new Response('', 301, ['Location' => 'https://kairus.it/articolo/slug-nuovo-3']);
                }

                // La destinazione del redirect risponde in modo inatteso.
                return new Response('errore', 500);
            }
        };
        $this->app->instance(InProcessPageFetcher::class, $fake);

        $redirects = app(RedirectAndCanonicalIntegrityAudit::class)->auditRedirects();

        $this->assertCount(1, $redirects);
        $this->assertNotSame([], $redirects[0]['findings']);
        $this->assertStringContainsString('500', $redirects[0]['findings'][0]);
    }

    public function test_a_redirect_landing_on_a_mismatched_canonical_is_flagged(): void
    {
        $article = $this->publishedArticle(['slug' => 'slug-vecchio-4']);
        $article->update(['slug' => 'slug-nuovo-4']);

        $fake = new class extends InProcessPageFetcher
        {
            public function fetch(string $url): Response
            {
                if (str_contains($url, 'slug-vecchio-4')) {
                    return new Response('', 301, ['Location' => 'https://kairus.it/articolo/slug-nuovo-4']);
                }

                return new Response('<link rel="canonical" href="https://kairus.it/articolo/un-altro-slug">', 200);
            }
        };
        $this->app->instance(InProcessPageFetcher::class, $fake);

        $redirects = app(RedirectAndCanonicalIntegrityAudit::class)->auditRedirects();

        $this->assertCount(1, $redirects);
        $this->assertNotSame([], $redirects[0]['findings']);
        $this->assertStringContainsString('canonical', $redirects[0]['findings'][0]);
    }

    public function test_canonical_consistency_checks_every_reachable_category_and_percorso(): void
    {
        // publicOptions() include anche il fallback legacy solo-config
        // (Cantiere 21): dopo aver svuotato la tabella, le 7 categorie di
        // config restano comunque raggiungibili, oltre a quella creata
        // qui — nessun conteggio fisso da assumere, solo che ENTRAMBE
        // risultino verificate senza finding.
        Category::query()->delete();
        Category::create(['name' => 'Fisica', 'slug' => 'fisica-test', 'is_active' => true, 'status' => Category::STATUS_PUBLISHED]);
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => null]);

        $results = app(RedirectAndCanonicalIntegrityAudit::class)->auditCanonicalConsistency();
        $byType = collect($results)->groupBy('type');
        $categorie = collect($byType['categoria'])->keyBy('url');

        $this->assertArrayHasKey(route('categoria', ['slug' => 'fisica-test']), $categorie);
        $this->assertSame([], $categorie[route('categoria', ['slug' => 'fisica-test'])]['findings']);

        $this->assertCount(1, $byType['percorso']);
        $this->assertSame(route('percorsi.show', ['slug' => $cluster->slug]), $byType['percorso'][0]['url']);
        $this->assertSame([], $byType['percorso'][0]['findings']);
    }

    public function test_article_canonical_check_respects_the_configured_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->publishedArticle();
        }

        $results = app(RedirectAndCanonicalIntegrityAudit::class)->auditCanonicalConsistency(articleLimit: 2);

        $this->assertCount(2, collect($results)->where('type', 'articolo'));
    }
}
