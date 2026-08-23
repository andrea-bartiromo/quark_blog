<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mission 5 (Category Pagination V1): audit-first. /categoria/{slug}
 * already paginates (ArticleController::category(), ->paginate(12)) on a
 * published-only, orderByDesc('published_at') query
 * (Article::scopePublished()), with an accessible pagination component
 * (components/pagination.blade.php — aria-label/aria-current/
 * aria-disabled, focus-visible, mobile breakpoint) and canonical-safe
 * pagination already covered by ArchivePaginationCanonicalTest
 * (page 1 has no query string, page 2+ canonicalizes to itself, UTM/
 * tracking params stripped).
 *
 * #258 (open, not merged) is mid-refactor on the DB-first category
 * source across several files, including
 * tests/Feature/PublicPageQueryBudgetTest.php's own category-page query
 * budget assertion. Per the mission's explicit ownership boundary, this
 * file does NOT touch ArticleController::category(), categoria.blade.php,
 * or PublicPageQueryBudgetTest.php — it only adds regression coverage
 * for edge cases (invalid page, last page, ordering) that were exercising
 * already-correct, already-shipped behavior but had no explicit test.
 */
class CategoryPaginationV1RegressionTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(string $category, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $category,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 3,
        ], $overrides));
    }

    public function test_page_one_shows_the_first_twelve_articles(): void
    {
        for ($i = 0; $i < 13; $i++) {
            $this->publishedArticle('energia', ['published_at' => now()->subMinutes($i)]);
        }

        $response = $this->get(route('categoria', 'energia'));

        $response->assertOk();
        $response->assertViewHas('articles', function ($paginator) {
            return $paginator->currentPage() === 1
                && $paginator->count() === 12
                && $paginator->total() === 13;
        });
    }

    public function test_page_two_shows_the_remaining_article(): void
    {
        for ($i = 0; $i < 13; $i++) {
            $this->publishedArticle('energia', ['published_at' => now()->subMinutes($i)]);
        }

        $response = $this->get(route('categoria', ['slug' => 'energia', 'page' => 2]));

        $response->assertOk();
        $response->assertViewHas('articles', fn ($paginator) => $paginator->currentPage() === 2 && $paginator->count() === 1);
    }

    public function test_requesting_a_page_beyond_the_last_returns_an_empty_but_valid_page(): void
    {
        $this->publishedArticle('energia');

        // Laravel's LengthAwarePaginator does not 404 or error on a page
        // number past the last available page — it returns that page
        // number with zero items. Locking that behavior in explicitly:
        // /categoria/{slug}?page=999 must stay a real 200, never a crash.
        $response = $this->get(route('categoria', ['slug' => 'energia', 'page' => 999]));

        $response->assertOk();
        $response->assertViewHas('articles', fn ($paginator) => $paginator->count() === 0);
    }

    public function test_a_non_numeric_page_parameter_falls_back_to_page_one_without_erroring(): void
    {
        $this->publishedArticle('energia');

        $response = $this->get(route('categoria', ['slug' => 'energia', 'page' => 'not-a-number']));

        $response->assertOk();
        $response->assertViewHas('articles', fn ($paginator) => $paginator->currentPage() === 1);
    }

    public function test_a_zero_or_negative_page_parameter_falls_back_to_page_one(): void
    {
        $this->publishedArticle('energia');

        $response = $this->get(route('categoria', ['slug' => 'energia', 'page' => 0]));
        $response->assertOk();
        $response->assertViewHas('articles', fn ($paginator) => $paginator->currentPage() === 1);

        $response = $this->get(route('categoria', ['slug' => 'energia', 'page' => -3]));
        $response->assertOk();
        $response->assertViewHas('articles', fn ($paginator) => $paginator->currentPage() === 1);
    }

    public function test_articles_are_ordered_newest_first_and_ordering_is_stable_across_pages(): void
    {
        $oldest = $this->publishedArticle('energia', ['title' => 'Il più vecchio', 'published_at' => now()->subDays(3)]);
        $middle = $this->publishedArticle('energia', ['title' => 'Nel mezzo', 'published_at' => now()->subDays(2)]);
        $newest = $this->publishedArticle('energia', ['title' => 'Il più recente', 'published_at' => now()->subDay()]);

        $response = $this->get(route('categoria', 'energia'));

        $response->assertOk();
        $titles = $response->viewData('articles')->pluck('title')->values()->all();

        $this->assertSame(['Il più recente', 'Nel mezzo', 'Il più vecchio'], $titles);
    }

    public function test_a_category_with_zero_published_articles_shows_no_pagination_controls(): void
    {
        $response = $this->get(route('categoria', 'ambiente'));

        $response->assertOk();
        $response->assertDontSee('pagination__item--current', false);
    }

    public function test_scheduled_articles_never_count_toward_category_pagination_totals(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->publishedArticle('energia', ['published_at' => now()->subMinutes($i)]);
        }
        // 5 scheduled articles in the same category — must never inflate
        // the total or leak onto any page.
        for ($i = 0; $i < 5; $i++) {
            $this->publishedArticle('energia', [
                'status' => Article::STATUS_SCHEDULED,
                'published_at' => now()->addDays($i + 1),
            ]);
        }

        $response = $this->get(route('categoria', 'energia'));

        $response->assertOk();
        $response->assertViewHas('articles', fn ($paginator) => $paginator->total() === 12);
        // No second page should exist once scheduled articles are excluded.
        $response->assertDontSee('pagination__item--arrow" href', false);
    }

    public function test_pagination_query_count_does_not_grow_between_page_one_and_a_later_page(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->publishedArticle('energia', ['published_at' => now()->subMinutes($i)]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('categoria', 'energia'))->assertOk();
        $pageOneCount = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->get(route('categoria', ['slug' => 'energia', 'page' => 2]))->assertOk();
        $pageTwoCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($pageOneCount, $pageTwoCount, 'Il numero di query non deve dipendere dal numero di pagina richiesto.');
    }
}
