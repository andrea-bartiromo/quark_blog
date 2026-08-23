<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\EditorialQuality\SourceImageAttributionHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceImageAttributionHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_media_attribution_separate_from_editorial_sources(): void
    {
        $article = $this->article([
            'cover_image' => 'cover.webp',
            'cover_alt' => 'Descrizione',
            'cover_credit' => 'ESA',
            'cover_source' => 'ESA/Hubble',
            'cover_license' => 'CC BY 4.0',
            'primary_sources' => '',
        ]);

        $checks = collect(app(SourceImageAttributionHealthService::class)->evaluate($article))->keyBy('id');

        $this->assertSame('OK', $checks['cover_credit_source']['status']);
        $this->assertSame('WARNING', $checks['editorial_sources']['status']);
    }

    public function test_it_warns_for_credit_source_inconsistency_and_missing_license(): void
    {
        $article = $this->article([
            'cover_image' => 'cover.webp',
            'cover_alt' => '',
            'cover_credit' => 'Autore foto',
            'cover_source' => '',
            'cover_license' => '',
        ]);

        $checks = collect(app(SourceImageAttributionHealthService::class)->evaluate($article))->keyBy('id');

        $this->assertSame('WARNING', $checks['cover_alt']['status']);
        $this->assertSame('WARNING', $checks['cover_credit_source']['status']);
        $this->assertSame('WARNING', $checks['cover_license']['status']);
    }

    public function test_it_warns_for_malformed_source_url_but_does_not_invent_a_url_requirement(): void
    {
        $withoutUrl = $this->article(['cover_image' => 'a.webp', 'cover_source_url' => null]);
        $badUrl = $this->article(['cover_image' => 'b.webp', 'cover_source_url' => 'javascript:alert(1)']);

        $service = app(SourceImageAttributionHealthService::class);
        $first = collect($service->evaluate($withoutUrl))->firstWhere('id', 'cover_source_url');
        $second = collect($service->evaluate($badUrl))->firstWhere('id', 'cover_source_url');

        $this->assertSame('NOT_APPLICABLE', $first['status']);
        $this->assertSame('WARNING', $second['status']);
    }

    public function test_it_flags_external_body_images_without_claiming_to_know_their_license(): void
    {
        $article = $this->article(['body' => '<p>Testo</p><img src="https://example.org/photo.jpg" alt="Foto">']);

        $check = collect(app(SourceImageAttributionHealthService::class)->evaluate($article))->firstWhere('id', 'external_body_images');

        $this->assertSame('WARNING', $check['status']);
        $this->assertStringContainsString('modello per-image', $check['reason']);
    }

    public function test_evaluation_is_read_only(): void
    {
        $article = $this->article(['cover_image' => 'cover.webp']);
        // Confronto riga-persistita-contro-riga-persistita: getAttributes()
        // su un modello appena creato riflette solo le chiavi assegnate
        // esplicitamente (mai i default di colonna non toccati, es.
        // 'views', 'featured', i campi SEO), quindi non è un prima
        // affidabile per un test "sola lettura" — un confronto contro il
        // fresh() successivo fallirebbe per colonne mai scritte dal
        // servizio, non per una mutazione reale.
        $before = $article->fresh()->getAttributes();

        app(SourceImageAttributionHealthService::class)->evaluate($article);

        $this->assertSame($before, $article->fresh()->getAttributes());
    }

    private function article(array $overrides = []): Article
    {
        $author = User::factory()->create(['role' => 'editor']);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo attribution '.uniqid(),
            'slug' => 'articolo-attribution-'.uniqid(),
            'excerpt' => 'Sommario sufficientemente lungo per questo test.',
            'body' => '<p>Corpo editoriale del test.</p>',
            'category' => 'spazio',
            'status' => Article::STATUS_DRAFT,
            'read_minutes' => 3,
            'verification_status' => 'unverified',
            'cover_image' => null,
            'cover_alt' => null,
            'cover_credit' => null,
            'cover_source' => null,
            'cover_source_url' => null,
            'cover_license' => null,
            'primary_sources' => null,
        ], $overrides));
    }
}
