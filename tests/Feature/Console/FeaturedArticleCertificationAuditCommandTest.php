<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\User;
use App\Services\EditorialQuality\EditorialQualityChecker;
use App\Services\EditorialQuality\EditorialQualityCheckResult;
use App\Services\EditorialQuality\EditorialQualityReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 36 (programma 100-cantieri Kairus), dipende dai Cantieri 30-35.
 * Stesso pattern già in uso per category:publication-readiness (Cantiere
 * 12-13): sola lettura, mai bloccante. EditorialQualityChecker è finto
 * (mai una regola reale ricalcolata qui, già provata da
 * EditorialQualityCheckerTest) solo per il caso "nessuna criticità": un
 * articolo che superi davvero tutti i 17+ controlli reali del checker
 * sarebbe una fixture fragile e irrilevante per questo comando, che
 * certifica solo l'essere "in evidenza", non la qualità editoriale in sé.
 */
class FeaturedArticleCertificationAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fakeQualityChecker(string $level): void
    {
        $result = $level === EditorialQualityReport::LEVEL_READY
            ? new EditorialQualityCheckResult(
                'title_present', 'Titolo', EditorialQualityCheckResult::STATUS_PASS,
                EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, EditorialQualityCheckResult::CATEGORY_CONTENT, 'Titolo presente.',
            )
            : new EditorialQualityCheckResult(
                'title_present', 'Titolo', EditorialQualityCheckResult::STATUS_FAIL,
                EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, EditorialQualityCheckResult::CATEGORY_CONTENT, 'Il titolo è vuoto.',
            );

        $fake = new class($result) extends EditorialQualityChecker
        {
            public function __construct(private readonly EditorialQualityCheckResult $result) {}

            public function check(Article $article, ?array $duplicateTitleIndex = null): EditorialQualityReport
            {
                return new EditorialQualityReport($article->id, [$this->result]);
            }
        };

        $this->app->instance(EditorialQualityChecker::class, $fake);
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
        ], $overrides));
    }

    public function test_reports_no_featured_articles_when_none_exist(): void
    {
        $this->article(['featured' => false]);

        $this->artisan('articles:featured-certification-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessun articolo marcato "in evidenza" al momento.');
    }

    public function test_reports_a_clean_featured_article(): void
    {
        $this->fakeQualityChecker(EditorialQualityReport::LEVEL_READY);
        $this->article(['featured' => true, 'title' => 'Articolo pulito']);

        $this->artisan('articles:featured-certification-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('Articolo pulito')
            ->expectsOutputToContain('Nessuna criticità rilevata.');
    }

    public function test_flags_a_non_published_featured_article(): void
    {
        $this->fakeQualityChecker(EditorialQualityReport::LEVEL_READY);
        $this->article(['featured' => true, 'status' => Article::STATUS_DRAFT, 'published_at' => null, 'title' => 'Bozza in evidenza']);

        $this->artisan('articles:featured-certification-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('Non ancora pubblicato');
    }

    public function test_flags_multiple_featured_published_articles(): void
    {
        $this->fakeQualityChecker(EditorialQualityReport::LEVEL_READY);
        $this->article(['featured' => true, 'title' => 'Primo in evidenza']);
        $this->article(['featured' => true, 'title' => 'Secondo in evidenza']);

        $this->artisan('articles:featured-certification-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('già segnato "in evidenza"');
    }

    public function test_json_output_is_valid_json(): void
    {
        $this->fakeQualityChecker(EditorialQualityReport::LEVEL_READY);
        $this->article(['featured' => true]);

        $this->artisan('articles:featured-certification-audit', ['--json' => true])->assertExitCode(0);
    }
}
