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
