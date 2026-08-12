<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganicGrowthSeoRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pageview_does_not_change_article_sitemap_lastmod(): void
    {
        $article = $this->publishedArticle();
        $oldUpdatedAt = now()->subDays(10)->startOfDay();

        DB::table('articles')
            ->where('id', $article->id)
            ->update(['updated_at' => $oldUpdatedAt]);

        $before = $this->get(route('sitemap'))->assertOk()->getContent();
        $beforeLastmod = $this->articleLastmod($before, $article);

        $this->assertSame($oldUpdatedAt->toDateString(), $beforeLastmod);

        $this->get(route('articolo', $article->slug))->assertOk();

        $after = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertSame(
            $beforeLastmod,
            $this->articleLastmod($after, $article),
            'Una semplice pageview non deve segnalare ai crawler una modifica editoriale dell’articolo.'
        );
    }

    public function test_collectionpage_schema_uses_clean_page_aware_canonical_instead_of_tracking_url(): void
    {
        for ($i = 0; $i < 13; $i++) {
            $this->publishedArticle(['title' => 'Articolo '.$i]);
        }

        $expected = route('notizie', ['page' => 2]);
        $html = $this->get(route('notizie', [
            'page' => 2,
            'utm_source' => 'newsletter',
            'fbclid' => 'tracking-token',
        ]))->assertOk()->getContent();

        $schema = $this->collectionPageSchemaFrom($html);

        $this->assertSame($expected, $schema['url']);
        $this->assertSame($expected.'#collectionpage', $schema['@id']);
        $this->assertStringNotContainsString('utm_', $schema['url']);
        $this->assertStringNotContainsString('fbclid', $schema['url']);
    }

    private function publishedArticle(array $overrides = []): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo SEO',
            'slug' => 'articolo-seo-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function articleLastmod(string $xml, Article $article): ?string
    {
        $url = preg_quote(route('articolo', $article->slug), '#');
        preg_match('#<loc>'.$url.'</loc><lastmod>([^<]+)</lastmod>#', $xml, $matches);

        return $matches[1] ?? null;
    }

    private function collectionPageSchemaFrom(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            $graph = is_array($data) ? ($data['@graph'] ?? []) : [];

            foreach ($graph as $node) {
                if (($node['@type'] ?? null) === 'CollectionPage') {
                    return $node;
                }
            }
        }

        $this->fail('Nessun nodo CollectionPage JSON-LD valido trovato.');
    }
}
