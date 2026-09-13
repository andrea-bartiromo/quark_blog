<?php

namespace Tests\Feature\OrganicDiscovery;

use App\Models\Article;
use App\Models\ArticleSearchProfile;
use App\Models\Category;
use App\Models\User;
use App\Services\OrganicDiscovery\EditorialOpportunityDecisionService;
use App\Services\SearchConsole\SearchOpportunity;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialOpportunityDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_brand_low_ctr_opportunity_for_a_public_article_is_editorial_priority(): void
    {
        $article = $this->article();
        $row = app(EditorialOpportunityDecisionService::class)->decide(collect([$this->opportunity($article)]))->first();

        $this->assertSame(EditorialOpportunityDecisionService::HIGH, $row['decision']);
        $this->assertSame($article->id, $row['article']->id);
        $this->assertStringContainsString('CTR', $row['reason']);
    }

    public function test_brand_queries_are_deprioritized_without_writing_anything(): void
    {
        $article = $this->article();
        $before = [Article::count(), ArticleSearchProfile::count()];
        $opportunity = new SearchOpportunity(SearchOpportunityScoringService::TYPE_HIGH_IMPRESSION_LOW_CTR, 'kairus lavoro remoto', $article, 100, 0, 0.0, 4.0, 10, 'fixture');

        $row = app(EditorialOpportunityDecisionService::class)->decide(collect([$opportunity]))->first();

        $this->assertSame(EditorialOpportunityDecisionService::NO_ACTION, $row['decision']);
        $this->assertSame($before, [Article::count(), ArticleSearchProfile::count()]);
    }

    public function test_a_query_without_a_reliable_public_article_is_left_for_verification(): void
    {
        $row = app(EditorialOpportunityDecisionService::class)->decide(collect([
            new SearchOpportunity(SearchOpportunityScoringService::TYPE_NO_STRONG_LANDING_PAGE, 'domanda senza articolo', null, 80, 0, null, 12.0, 8, 'fixture'),
        ]))->first();

        $this->assertSame(EditorialOpportunityDecisionService::VERIFY, $row['decision']);
        $this->assertNull($row['article']);
    }

    public function test_a_matching_primary_query_on_another_public_article_is_a_collision_to_verify(): void
    {
        $article = $this->article(['slug' => 'prima']);
        $other = $this->article(['slug' => 'seconda']);
        ArticleSearchProfile::create(['article_id' => $other->id, 'primary_query' => 'lavoro da remoto']);

        $row = app(EditorialOpportunityDecisionService::class)->decide(collect([$this->opportunity($article)]))->first();

        $this->assertSame(EditorialOpportunityDecisionService::VERIFY, $row['decision']);
        $this->assertContains($other->id, $row['possible_collision_article_ids']);
    }

    public function test_draft_articles_are_never_treated_as_reliable_public_matches(): void
    {
        $draft = $this->article(['slug' => 'bozza', 'status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $row = app(EditorialOpportunityDecisionService::class)->decide(collect([$this->opportunity($draft)]))->first();

        $this->assertSame(EditorialOpportunityDecisionService::VERIFY, $row['decision']);
        $this->assertNull($row['article']);
    }

    private function article(array $overrides = []): Article
    {
        $category = Category::firstOrCreate(['slug' => 'decision-category'], ['name' => 'Decision category']);

        return Article::withoutEvents(fn () => Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Lavoro da remoto e attenzione',
            'slug' => 'lavoro-remoto-'.uniqid(),
            'excerpt' => 'Un testo editoriale.',
            'body' => '<p>Un testo editoriale con contenuto sufficiente.</p>',
            'category' => $category->slug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ], $overrides)));
    }

    private function opportunity(Article $article): SearchOpportunity
    {
        return new SearchOpportunity(SearchOpportunityScoringService::TYPE_HIGH_IMPRESSION_LOW_CTR, 'lavoro da remoto', $article, 100, 1, 0.01, 5.0, 12, 'fixture');
    }
}
