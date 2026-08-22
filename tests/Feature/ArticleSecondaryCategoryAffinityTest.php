<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\Category;
use App\Models\User;
use App\Services\ArticleRelatedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSecondaryCategoryAffinityTest extends TestCase
{
    use RefreshDatabase;

    // La migrazione delle categorie ne seed gia' alcune di default (dallo
    // stesso config('laboratorio.categories') usato come fallback altrove):
    // usa updateOrCreate cosi' i test possono regolarne lo stato senza
    // collidere con lo slug unique gia' popolato da RefreshDatabase (stessa
    // convenzione di HomeCategoriesTest::category()).
    private function category(string $name, string $slug): Category
    {
        return Category::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'is_active' => true,
                'sort_order' => 0,
            ]
        );
    }

    private function article(User $author, string $title, string $category): Article
    {
        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo editoriale di prova con relatività quantistica e gravitazione.</p>',
            'category' => $category,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 2,
            'verification_status' => 'unverified',
        ]);
    }

    public function test_related_articles_can_match_through_a_shared_secondary_category(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $fisica = $this->category('Fisica', 'fisica');
        $this->category('Tecnologia & Società', 'societa');
        $this->category('Spazio', 'spazio');
        $this->category('Energia & Clima', 'energia');

        $source = $this->article($author, 'GPS e relatività', 'societa');
        $target = $this->article($author, 'Gravità nello spazio', 'spazio');
        $unrelated = $this->article($author, 'Reti elettriche', 'energia');

        $source->secondaryCategories()->attach($fisica->id);
        $target->secondaryCategories()->attach($fisica->id);

        $relatedIds = app(ArticleRelatedService::class)
            ->forArticle($source, 10)
            ->pluck('id');

        $this->assertTrue($relatedIds->contains($target->id));
        $this->assertFalse($relatedIds->contains($unrelated->id));
        $this->assertFalse($relatedIds->contains($source->id));
    }

    public function test_secondary_category_affinity_adds_the_same_ten_point_bonus_to_a_proposed_link(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $fisica = $this->category('Fisica', 'fisica');
        $this->category('Tecnologia & Società', 'societa');
        $this->category('Spazio', 'spazio');

        $source = $this->article($author, 'GPS relativistico', 'societa');
        $target = $this->article($author, 'Relatività nello spazio', 'spazio');

        $source->secondaryCategories()->attach($fisica->id);
        $target->secondaryCategories()->attach($fisica->id);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => $target->slug,
            'anchor_text' => 'relatività',
            'context_excerpt' => '... relatività ...',
            'reason' => 'Termini in comune: relatività',
            'confidence_score' => 50,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
        ])->fresh();

        $this->assertSame(60, $suggestion->confidence_score);
        $this->assertStringContainsString('categoria condivisa: Fisica', $suggestion->reason);
    }

    public function test_secondary_category_bonus_is_idempotent_and_does_not_double_primary_bonus(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $fisica = $this->category('Fisica', 'fisica');
        $this->category('Spazio', 'spazio');

        $source = $this->article($author, 'Onde gravitazionali', 'spazio');
        $target = $this->article($author, 'Buchi neri', 'spazio');
        $source->secondaryCategories()->attach($fisica->id);
        $target->secondaryCategories()->attach($fisica->id);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => $target->slug,
            'anchor_text' => 'gravitazione',
            'context_excerpt' => '... gravitazione ...',
            'reason' => 'Stessa categoria: spazio',
            'confidence_score' => 60,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
        ])->fresh();

        $this->assertSame(60, $suggestion->confidence_score);
        $this->assertStringNotContainsString('categoria condivisa:', $suggestion->reason);

        $suggestion->touch();
        $this->assertSame(60, $suggestion->fresh()->confidence_score);
    }
}
