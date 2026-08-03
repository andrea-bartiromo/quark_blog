<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre il canonical auto-referenziale su /notizie e /categoria/{slug}:
 * pagina 1 sempre senza query string, pagina N>1 su se stessa (mai su
 * pagina 1), costruito da route() e mai da request()->fullUrl() — nessun
 * parametro estraneo (UTM, tracking) deve finire nel canonical.
 */
class ArchivePaginationCanonicalTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function seedPublishedArticles(int $count, array $overrides = []): void
    {
        $author = $this->author();

        for ($i = 0; $i < $count; $i++) {
            Article::create(array_merge([
                'user_id' => $author->id,
                'title' => 'Articolo di prova '.$i,
                'slug' => 'articolo-di-prova-'.uniqid(),
                'excerpt' => 'Sommario di prova',
                'body' => '<p>Corpo articolo di prova.</p>',
                'category' => 'intelligenza-artificiale',
                'cover_image' => 'copertina.jpg',
                'status' => 'published',
                'published_at' => now(),
            ], $overrides));
        }
    }

    private function canonicalHrefFrom(string $html): ?string
    {
        preg_match('#<link rel="canonical" href="(.*?)">#', $html, $matches);

        return $matches[1] ?? null;
    }

    public function test_notizie_page_one_canonical_has_no_page_query_string(): void
    {
        $this->seedPublishedArticles(13);

        $html = $this->get(route('notizie'))->assertOk()->getContent();

        $this->assertSame(route('notizie'), $this->canonicalHrefFrom($html));
        $this->assertStringNotContainsString('?page=1', $html);
    }

    public function test_notizie_page_two_canonicalizes_to_itself_not_to_page_one(): void
    {
        $this->seedPublishedArticles(13);

        $html = $this->get(route('notizie', ['page' => 2]))->assertOk()->getContent();

        $this->assertSame(route('notizie', ['page' => 2]), $this->canonicalHrefFrom($html));
        $this->assertNotSame(route('notizie'), $this->canonicalHrefFrom($html));
    }

    public function test_notizie_canonical_strips_utm_and_tracking_parameters(): void
    {
        $this->seedPublishedArticles(13);

        $html = $this->get(route('notizie', [
            'page' => 2,
            'utm_source' => 'newsletter',
            'utm_campaign' => 'test',
            'fbclid' => 'abc123',
        ]))->assertOk()->getContent();

        $canonical = $this->canonicalHrefFrom($html);

        $this->assertSame(route('notizie', ['page' => 2]), $canonical);
        $this->assertStringNotContainsString('utm_', $canonical);
        $this->assertStringNotContainsString('fbclid', $canonical);
    }

    public function test_category_page_one_canonical_has_no_page_query_string(): void
    {
        $this->seedPublishedArticles(13, ['category' => 'energia']);

        $html = $this->get(route('categoria', 'energia'))->assertOk()->getContent();

        $this->assertSame(route('categoria', 'energia'), $this->canonicalHrefFrom($html));
        $this->assertStringNotContainsString('?page=1', $html);
    }

    public function test_category_page_two_canonicalizes_to_itself_not_to_page_one(): void
    {
        $this->seedPublishedArticles(13, ['category' => 'energia']);

        $html = $this->get(route('categoria', ['slug' => 'energia', 'page' => 2]))
            ->assertOk()
            ->getContent();

        $expected = route('categoria', ['slug' => 'energia', 'page' => 2]);

        $this->assertSame($expected, $this->canonicalHrefFrom($html));
        $this->assertNotSame(route('categoria', 'energia'), $this->canonicalHrefFrom($html));
    }

    public function test_only_one_canonical_link_is_present_in_the_response(): void
    {
        $this->seedPublishedArticles(13);

        $html = $this->get(route('notizie', ['page' => 2]))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<link rel="canonical"'));
    }
}
