<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\User;
use App\Services\ContentGraph\ContentGraphOrphanAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission 23 — Orphan Health: la listing per-item read-only che
 * ContentGraphCoverageService (Mission 19) espone solo come conteggio.
 * Stessa forma (id/titolo/slug/stato) del precedente
 * PercorsoCoverageAuditService::audit()['published_without_path'].
 */
class ContentGraphOrphanAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ContentGraphOrphanAuditService
    {
        return app(ContentGraphOrphanAuditService::class);
    }

    private function article(string $title, string $status = Article::STATUS_PUBLISHED): Article
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return Article::create([
            'user_id' => $user->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $status === Article::STATUS_PUBLISHED ? now()->subDay() : null,
        ]);
    }

    public function test_a_published_article_with_no_concept_link_is_listed_as_orphan(): void
    {
        $article = $this->article('Termodinamica base');

        $orphans = $this->service()->orphanArticles();

        $this->assertCount(1, $orphans);
        $this->assertSame([
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'status' => Article::STATUS_PUBLISHED,
        ], $orphans[0]);
    }

    public function test_a_published_article_with_a_concept_link_is_not_listed(): void
    {
        $article = $this->article('Termodinamica base');
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $article->contentConcepts()->create(['concept_id' => $concept->id, 'relation_type' => 'supporting', 'weight' => 50]);

        $this->assertSame([], $this->service()->orphanArticles());
    }

    public function test_a_draft_article_with_no_concept_link_is_never_listed(): void
    {
        $this->article('Bozza', Article::STATUS_DRAFT);

        $this->assertSame([], $this->service()->orphanArticles());
    }

    public function test_an_active_concept_with_no_article_link_is_listed_as_orphan(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);

        $orphans = $this->service()->orphanConcepts();

        $this->assertCount(1, $orphans);
        $this->assertSame([
            'id' => $concept->id,
            'name' => $concept->name,
            'slug' => $concept->slug,
            'status' => 'active',
        ], $orphans[0]);
    }

    public function test_an_active_concept_with_an_article_link_is_not_listed(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $article = $this->article('Termodinamica base');
        $concept->articleLinks()->create(['article_id' => $article->id, 'relation_type' => 'supporting', 'weight' => 50]);

        $this->assertSame([], $this->service()->orphanConcepts());
    }

    public function test_a_draft_concept_with_no_article_link_is_never_listed(): void
    {
        Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'draft']);

        $this->assertSame([], $this->service()->orphanConcepts());
    }

    public function test_orphan_listings_are_ordered_alphabetically(): void
    {
        $this->article('Zeta articolo');
        $this->article('Alfa articolo');
        Concept::create(['name' => 'Zeta concetto', 'slug' => 'zeta-concetto', 'status' => 'active']);
        Concept::create(['name' => 'Alfa concetto', 'slug' => 'alfa-concetto', 'status' => 'active']);

        $orphanArticles = $this->service()->orphanArticles();
        $orphanConcepts = $this->service()->orphanConcepts();

        $this->assertSame(['Alfa articolo', 'Zeta articolo'], array_column($orphanArticles, 'title'));
        $this->assertSame(['Alfa concetto', 'Zeta concetto'], array_column($orphanConcepts, 'name'));
    }
}
