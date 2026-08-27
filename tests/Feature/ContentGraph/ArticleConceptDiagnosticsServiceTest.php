<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\User;
use App\Services\ContentGraph\ArticleConceptDiagnosticsService;
use App\Services\ContentGraph\ContentGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticleConceptDiagnosticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $status, string $slug): Article
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return Article::create([
            'user_id' => $user->id,
            'title' => 'Articolo '.$slug,
            'slug' => $slug,
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $status === Article::STATUS_PUBLISHED ? now()->subDay() : null,
        ]);
    }

    private function concept(string $name, string $status): Concept
    {
        return Concept::create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'status' => $status,
        ]);
    }

    public function test_reuses_published_article_orphan_audit(): void
    {
        $article = $this->article(Article::STATUS_PUBLISHED, 'orfano');

        $audit = app(ArticleConceptDiagnosticsService::class)->audit();

        $this->assertSame(
            [$article->id],
            collect($audit['published_articles_without_concept'])->pluck('id')->all(),
        );
    }

    public function test_flags_published_article_relation_to_inactive_concept(): void
    {
        $article = $this->article(Article::STATUS_PUBLISHED, 'pubblico');
        $concept = $this->concept('Inattivo', Concept::STATUS_INACTIVE);
        app(ContentGraphService::class)->linkArticle($article, $concept);

        $findings = collect(app(ArticleConceptDiagnosticsService::class)->audit()['findings']);

        $finding = $findings->firstWhere(
            'code',
            ArticleConceptDiagnosticsService::PUBLISHED_ARTICLE_WITH_INACTIVE_CONCEPT,
        );
        $this->assertSame($article->id, $finding['article_id']);
        $this->assertSame($concept->id, $finding['concept_id']);
    }

    public function test_flags_active_concept_linked_only_to_non_public_articles(): void
    {
        $concept = $this->concept('Solo bozza', Concept::STATUS_ACTIVE);
        app(ContentGraphService::class)->linkArticle(
            $this->article(Article::STATUS_DRAFT, 'bozza'),
            $concept,
        );

        $findings = collect(app(ArticleConceptDiagnosticsService::class)->audit()['findings']);

        $finding = $findings->firstWhere(
            'code',
            ArticleConceptDiagnosticsService::ACTIVE_CONCEPT_ONLY_NON_PUBLIC_ARTICLES,
        );
        $this->assertSame($concept->id, $finding['concept_id']);
        $this->assertSame(1, $finding['article_links_count']);
    }

    public function test_active_concept_with_a_published_article_is_not_flagged(): void
    {
        $concept = $this->concept('Pubblico', Concept::STATUS_ACTIVE);
        app(ContentGraphService::class)->linkArticle(
            $this->article(Article::STATUS_PUBLISHED, 'pubblicato'),
            $concept,
        );

        $findings = collect(app(ArticleConceptDiagnosticsService::class)->audit()['findings']);

        $this->assertFalse($findings->contains(
            fn (array $finding) => $finding['code'] === ArticleConceptDiagnosticsService::ACTIVE_CONCEPT_ONLY_NON_PUBLIC_ARTICLES
        ));
    }

    public function test_audit_query_count_is_bounded(): void
    {
        $concept = $this->concept('Bounded', Concept::STATUS_ACTIVE);
        app(ContentGraphService::class)->linkArticle(
            $this->article(Article::STATUS_DRAFT, 'bounded'),
            $concept,
        );

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(ArticleConceptDiagnosticsService::class)->audit();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertGreaterThanOrEqual(3, count($queries));
        $this->assertLessThanOrEqual(5, count($queries));
    }

    public function test_audit_declares_that_no_relation_policy_was_invented(): void
    {
        $audit = app(ArticleConceptDiagnosticsService::class)->audit();

        $this->assertCount(2, $audit['policy_notes']);
        $this->assertStringContainsString('primary/supporting', $audit['policy_notes'][0]);
        $this->assertStringContainsString('0–255', $audit['policy_notes'][1]);
    }
}
