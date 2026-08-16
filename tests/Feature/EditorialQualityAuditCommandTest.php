<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\EditorialQuality\EditorialQualityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EditorialQualityAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova sufficientemente completo',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Un sommario abbastanza lungo da superare la soglia minima richiesta.',
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso per il corpo. ', 10).'</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'cover_image' => 'cover.webp',
            'cover_alt' => 'Descrizione della cover',
            'primary_sources' => 'https://example.com/fonte',
        ], $overrides));
    }

    public function test_a_ready_article_is_counted_as_ready(): void
    {
        $this->article(['body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso per il corpo. ', 10).'</p><a href="/articolo/altro">altro</a>']);

        $summary = app(EditorialQualityAuditService::class)->audit();

        $this->assertSame(1, $summary->analyzed);
        $this->assertSame(1, $summary->readyCount);
        $this->assertSame(0, $summary->incompleteCount);
    }

    public function test_an_incomplete_article_is_counted_as_incomplete(): void
    {
        $this->article(['cover_image' => null]);

        $summary = app(EditorialQualityAuditService::class)->audit();

        $this->assertSame(1, $summary->incompleteCount);
        $this->assertSame(0, $summary->readyCount);
    }

    public function test_most_frequent_issues_are_aggregated_across_articles(): void
    {
        $this->article(['primary_sources' => null]);
        $this->article(['primary_sources' => null]);
        $this->article(['primary_sources' => 'https://esempio.it/fonte']);

        $summary = app(EditorialQualityAuditService::class)->audit();

        $sourcesIssue = collect($summary->mostFrequentIssues)->firstWhere('code', 'sources_present');

        $this->assertNotNull($sourcesIssue);
        $this->assertSame(2, $sourcesIssue['count']);
    }

    public function test_status_filter_limits_the_analyzed_articles(): void
    {
        $this->article(['status' => Article::STATUS_PUBLISHED]);
        $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $summary = app(EditorialQualityAuditService::class)->audit(status: Article::STATUS_DRAFT);

        $this->assertSame(1, $summary->analyzed);
        $this->assertSame(Article::STATUS_DRAFT, $summary->entries[0]['article']->status);
    }

    public function test_article_filter_limits_to_a_single_article(): void
    {
        $target = $this->article();
        $this->article();

        $summary = app(EditorialQualityAuditService::class)->audit(articleId: $target->id);

        $this->assertSame(1, $summary->analyzed);
        $this->assertSame($target->id, $summary->entries[0]['article']->id);
    }

    // ── Comando: read-only, JSON, testo ──

    public function test_the_command_never_modifies_any_article(): void
    {
        $article = $this->article(['cover_image' => null, 'primary_sources' => null]);
        $originalUpdatedAt = $article->updated_at;
        $originalStatus = $article->status;
        $originalBody = $article->body;

        $this->artisan('content:quality-audit')->assertExitCode(0);

        $article->refresh();

        $this->assertEquals($originalUpdatedAt, $article->updated_at);
        $this->assertSame($originalStatus, $article->status);
        $this->assertSame($originalBody, $article->body);
    }

    public function test_the_command_prints_a_text_report_by_default(): void
    {
        $this->article();

        $this->artisan('content:quality-audit')
            ->expectsOutputToContain('EDITORIAL QUALITY AUDIT — KAIRUS')
            ->assertExitCode(0);
    }

    public function test_the_json_output_is_valid_and_contains_the_expected_shape(): void
    {
        $this->article();

        $exitCode = Artisan::call('content:quality-audit', ['--json' => true]);
        $output = Artisan::output();
        $decoded = json_decode($output, true);

        $this->assertSame(0, $exitCode);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('summary', $decoded);
        $this->assertArrayHasKey('articles', $decoded);
        $this->assertArrayHasKey('checks', $decoded['articles'][0]);
        $this->assertArrayNotHasKey('body', $decoded['articles'][0]);
    }

    public function test_a_legacy_article_missing_many_optional_fields_never_crashes_the_command(): void
    {
        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo storico',
            'slug' => 'articolo-storico-'.uniqid(),
            'body' => '<p>'.str_repeat('parola ', 60).'</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subYear(),
        ]);

        $this->artisan('content:quality-audit')->assertExitCode(0);
    }
}
