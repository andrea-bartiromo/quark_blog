<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\EditorialQuality\SeoMetadataQualityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoMetadataQualityAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_fallback_usage_and_duplicate_effective_metadata(): void
    {
        $this->article('Titolo condiviso', ['seo_title' => null, 'seo_description' => 'Descrizione SEO condivisa abbastanza lunga per il test.']);
        $this->article('Titolo condiviso', ['seo_title' => null, 'seo_description' => 'Descrizione SEO condivisa abbastanza lunga per il test.']);

        $report = app(SeoMetadataQualityAuditService::class)->audit();

        $this->assertSame(2, $report['summary']['duplicate_effective_titles']);
        $this->assertSame(2, $report['summary']['duplicate_effective_descriptions']);
        $this->assertTrue($report['articles'][0]['uses_seo_title_fallback']);
    }

    public function test_it_warns_for_canonical_with_query_string(): void
    {
        $article = $this->article('Canonical anomalo', ['canonical_url' => 'https://kairus.it/articolo/canonical-anomalo?utm_source=test']);

        $row = collect(app(SeoMetadataQualityAuditService::class)->audit()['articles'])->firstWhere('article_id', $article->id);

        $this->assertSame('WARNING', $row['canonical']['status']);
    }

    /**
     * Missione 38 (Fase E — Editorial Quality & Readiness): un canonical
     * override che punta a un dominio diverso dal sito dice ai motori di
     * ricerca "il contenuto vero vive altrove" — de-indicizza di fatto la
     * pagina reale. Stesso confronto host case-insensitive già usato da
     * ArticleLinkInsertionService::isSafeInternalUrl().
     */
    public function test_it_warns_when_canonical_points_to_a_different_domain(): void
    {
        $article = $this->article('Canonical fuori dominio', ['canonical_url' => 'https://example.com/altrove']);

        $row = collect(app(SeoMetadataQualityAuditService::class)->audit()['articles'])->firstWhere('article_id', $article->id);

        $this->assertSame('WARNING', $row['canonical']['status']);
        $this->assertSame('Canonical effettivo punta a un dominio diverso dal sito.', $row['canonical']['reason']);
    }

    public function test_the_default_canonical_falling_back_to_the_articles_own_route_is_never_flagged(): void
    {
        $article = $this->article('Canonical di fallback');

        $row = collect(app(SeoMetadataQualityAuditService::class)->audit()['articles'])->firstWhere('article_id', $article->id);

        $this->assertSame('OK', $row['canonical']['status']);
    }

    /**
     * Missione 38: il canonical di fallback è la route dello slug proprio
     * dell'articolo (sempre unico grazie al vincolo UNIQUE su
     * articles.slug) — un duplicato può nascere SOLO da un override
     * esplicito che collide con un altro articolo, un vero conflitto di
     * canonicalizzazione, non un falso positivo.
     */
    public function test_two_articles_sharing_the_same_overridden_canonical_are_flagged_as_duplicates(): void
    {
        $first = $this->article('Primo canonical condiviso', ['canonical_url' => 'https://kairus.it/articolo/pagina-unica']);
        $second = $this->article('Secondo canonical condiviso', ['canonical_url' => 'https://kairus.it/articolo/pagina-unica']);

        $report = app(SeoMetadataQualityAuditService::class)->audit();
        $rows = collect($report['articles']);

        $this->assertTrue($rows->firstWhere('article_id', $first->id)['duplicate_canonical_url']);
        $this->assertTrue($rows->firstWhere('article_id', $second->id)['duplicate_canonical_url']);
        $this->assertSame(2, $report['summary']['duplicate_canonical_urls']);
    }

    public function test_it_reuses_existing_quality_gate_seo_checks(): void
    {
        $article = $this->article(str_repeat('Titolo molto lungo ', 8), ['status' => Article::STATUS_PUBLISHED, 'published_at' => now()->subDay()]);

        $row = collect(app(SeoMetadataQualityAuditService::class)->audit()['articles'])->firstWhere('article_id', $article->id);
        $codes = array_column($row['existing_quality_checks'], 'code');

        $this->assertContains('seo_title', $codes);
        $this->assertContains('meta_description', $codes);
        $this->assertContains('indexability', $codes);
    }

    public function test_social_metadata_uses_existing_fallback_chain_instead_of_generating_copy(): void
    {
        $article = $this->article('Fallback social', ['og_title' => null, 'twitter_title' => null]);

        $row = collect(app(SeoMetadataQualityAuditService::class)->audit()['articles'])->firstWhere('article_id', $article->id);

        $this->assertTrue($row['social_metadata']['og_title_present']);
        $this->assertTrue($row['social_metadata']['twitter_title_present']);
        $this->assertTrue($row['social_metadata']['og_image_present']);
    }

    public function test_it_does_not_invent_a_short_seo_title_threshold_or_claim_route_leakage_is_checked(): void
    {
        $this->article('Breve');

        $report = app(SeoMetadataQualityAuditService::class)->audit();

        $this->assertSame('NOT_DEFINED_IN_REPOSITORY', $report['policy_notes']['short_seo_title_threshold']);
        $this->assertSame('REQUIRES_ROUTE_RENDERING_REGRESSION_TESTS', $report['policy_notes']['scheduled_surface_leakage']);
    }

    private function article(string $title, array $overrides = []): Article
    {
        $author = User::factory()->create(['role' => 'editor']);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => 'seo-audit-'.uniqid(),
            'excerpt' => 'Descrizione editoriale sufficientemente lunga per alimentare il fallback SEO.',
            'body' => '<p>Corpo articolo con contenuto sufficiente per i controlli.</p>',
            'category' => 'spazio',
            'status' => Article::STATUS_DRAFT,
            'published_at' => null,
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ], $overrides));
    }
}
