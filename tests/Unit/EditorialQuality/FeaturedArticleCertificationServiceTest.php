<?php

namespace Tests\Unit\EditorialQuality;

use App\Models\Article;
use App\Models\User;
use App\Services\EditorialQuality\EditorialQualityCheckResult;
use App\Services\EditorialQuality\EditorialQualityReport;
use App\Services\EditorialQuality\FeaturedArticleCertificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 36 (programma 100-cantieri Kairus). FeaturedArticleCertificationService
 * non ricalcola mai le regole di EditorialQualityChecker: qui si passa un
 * EditorialQualityReport costruito a mano (stessi DTO pubblici usati dal
 * checker reale) per isolare completamente la logica di certificazione
 * dal comportamento dei singoli controlli, già provato altrove.
 */
class FeaturedArticleCertificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FeaturedArticleCertificationService
    {
        return app(FeaturedArticleCertificationService::class);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => '<p>Corpo di prova.</p>',
            'category' => 'scienza',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
            'featured' => true,
        ], $overrides));
    }

    private function reportAtLevel(int $articleId, string $level): EditorialQualityReport
    {
        $result = match ($level) {
            EditorialQualityReport::LEVEL_INCOMPLETE => new EditorialQualityCheckResult(
                'title_present', 'Titolo', EditorialQualityCheckResult::STATUS_FAIL,
                EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, EditorialQualityCheckResult::CATEGORY_CONTENT, 'Il titolo è vuoto.',
            ),
            EditorialQualityReport::LEVEL_ATTENTION => new EditorialQualityCheckResult(
                'cover_present', 'Cover', EditorialQualityCheckResult::STATUS_WARNING,
                EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, EditorialQualityCheckResult::CATEGORY_MEDIA, 'Cover non ottimale.',
            ),
            default => new EditorialQualityCheckResult(
                'title_present', 'Titolo', EditorialQualityCheckResult::STATUS_PASS,
                EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, EditorialQualityCheckResult::CATEGORY_CONTENT, 'Titolo presente.',
            ),
        };

        return new EditorialQualityReport($articleId, [$result]);
    }

    public function test_a_published_article_with_no_quality_issues_and_no_other_featured_article_is_ready(): void
    {
        $article = $this->article();

        $result = $this->service()->evaluate($article, $this->reportAtLevel($article->id, EditorialQualityReport::LEVEL_READY));

        $this->assertTrue($result['ready']);
        $this->assertSame([], $result['findings']);
    }

    public function test_a_non_published_featured_article_is_flagged_not_published(): void
    {
        $article = $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $result = $this->service()->evaluate($article, $this->reportAtLevel($article->id, EditorialQualityReport::LEVEL_READY));

        $this->assertFalse($result['ready']);
        $this->assertContains(FeaturedArticleCertificationService::FINDING_NOT_PUBLISHED, $result['findings']);
    }

    public function test_a_published_but_future_dated_featured_article_is_flagged_not_published(): void
    {
        // Codex (PR #583, P2): status="published" da solo non basta —
        // HomeController::index() usa Article::published(), che richiede
        // ANCHE published_at <= now(). Un articolo "pubblicato" con una
        // data futura non è ancora visibile, quindi non è davvero pronto
        // per il primo piano.
        $article = $this->article(['published_at' => now()->addDay()]);

        $result = $this->service()->evaluate($article, $this->reportAtLevel($article->id, EditorialQualityReport::LEVEL_READY));

        $this->assertFalse($result['ready']);
        $this->assertContains(FeaturedArticleCertificationService::FINDING_NOT_PUBLISHED, $result['findings']);
    }

    public function test_an_incomplete_quality_report_is_flagged(): void
    {
        $article = $this->article();

        $result = $this->service()->evaluate($article, $this->reportAtLevel($article->id, EditorialQualityReport::LEVEL_INCOMPLETE));

        $this->assertFalse($result['ready']);
        $this->assertContains(FeaturedArticleCertificationService::FINDING_QUALITY_INCOMPLETE, $result['findings']);
        $this->assertNotContains(FeaturedArticleCertificationService::FINDING_QUALITY_ATTENTION, $result['findings']);
    }

    public function test_an_attention_level_quality_report_is_flagged_but_not_as_incomplete(): void
    {
        $article = $this->article();

        $result = $this->service()->evaluate($article, $this->reportAtLevel($article->id, EditorialQualityReport::LEVEL_ATTENTION));

        $this->assertFalse($result['ready']);
        $this->assertContains(FeaturedArticleCertificationService::FINDING_QUALITY_ATTENTION, $result['findings']);
        $this->assertNotContains(FeaturedArticleCertificationService::FINDING_QUALITY_INCOMPLETE, $result['findings']);
    }

    public function test_a_second_published_featured_article_triggers_multiple_featured_finding(): void
    {
        $article = $this->article();
        $this->article(['title' => 'Un secondo articolo in evidenza']);

        $result = $this->service()->evaluate($article, $this->reportAtLevel($article->id, EditorialQualityReport::LEVEL_READY));

        $this->assertFalse($result['ready']);
        $this->assertContains(FeaturedArticleCertificationService::FINDING_MULTIPLE_FEATURED, $result['findings']);
    }

    public function test_a_draft_featured_article_does_not_count_toward_multiple_featured(): void
    {
        $article = $this->article();
        $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null, 'title' => 'Bozza in evidenza']);

        $result = $this->service()->evaluate($article, $this->reportAtLevel($article->id, EditorialQualityReport::LEVEL_READY));

        $this->assertTrue($result['ready']);
        $this->assertNotContains(FeaturedArticleCertificationService::FINDING_MULTIPLE_FEATURED, $result['findings']);
    }

    public function test_a_non_featured_published_article_does_not_count_toward_multiple_featured(): void
    {
        $article = $this->article();
        $this->article(['featured' => false, 'title' => 'Non in evidenza']);

        $result = $this->service()->evaluate($article, $this->reportAtLevel($article->id, EditorialQualityReport::LEVEL_READY));

        $this->assertTrue($result['ready']);
        $this->assertNotContains(FeaturedArticleCertificationService::FINDING_MULTIPLE_FEATURED, $result['findings']);
    }

    public function test_a_future_dated_published_featured_article_does_not_count_toward_multiple_featured(): void
    {
        $article = $this->article();
        $this->article(['title' => 'In evidenza ma non ancora visibile', 'published_at' => now()->addDay()]);

        $result = $this->service()->evaluate($article, $this->reportAtLevel($article->id, EditorialQualityReport::LEVEL_READY));

        $this->assertTrue($result['ready']);
        $this->assertNotContains(FeaturedArticleCertificationService::FINDING_MULTIPLE_FEATURED, $result['findings']);
    }

    public function test_a_precomputed_another_featured_flag_is_used_instead_of_querying(): void
    {
        $article = $this->article();

        $readyResult = $this->service()->evaluate(
            $article,
            $this->reportAtLevel($article->id, EditorialQualityReport::LEVEL_READY),
            anotherPublishedFeaturedArticleExists: false,
        );
        $this->assertTrue($readyResult['ready']);

        $flaggedResult = $this->service()->evaluate(
            $article,
            $this->reportAtLevel($article->id, EditorialQualityReport::LEVEL_READY),
            anotherPublishedFeaturedArticleExists: true,
        );
        $this->assertFalse($flaggedResult['ready']);
        $this->assertContains(FeaturedArticleCertificationService::FINDING_MULTIPLE_FEATURED, $flaggedResult['findings']);
    }
}
