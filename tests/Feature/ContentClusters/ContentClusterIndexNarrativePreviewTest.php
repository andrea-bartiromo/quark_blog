<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentClusterIndexNarrativePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_one_two_and_three_step_previews_render_only_the_available_prefix(): void
    {
        $empty = $this->cluster('Zero tappe', 10);
        $one = $this->cluster('Una tappa', 20, [$this->article('Uno')]);
        $two = $this->cluster('Due tappe', 30, [$this->article('Due A'), $this->article('Due B')]);
        $four = $this->cluster('Quattro tappe', 40, [
            $this->article('Tre A'),
            $this->article('Tre B'),
            $this->article('Tre C'),
            $this->article('Quarta non mostrata'),
        ]);

        $html = $this->get(route('percorsi.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('path-preview-'.$empty->id, $html);
        $this->assertPreviewTitles($html, $one, ['Uno']);
        $this->assertPreviewTitles($html, $two, ['Due A', 'Due B']);
        $this->assertPreviewTitles($html, $four, ['Tre A', 'Tre B', 'Tre C']);
        $this->assertStringNotContainsString('Quarta non mostrata', $this->previewHtml($html, $four));
    }

    public function test_preview_uses_narrative_order_and_stops_at_scheduled_draft_review_or_a_gap(): void
    {
        foreach ([Article::STATUS_SCHEDULED, Article::STATUS_DRAFT, Article::STATUS_REVIEW] as $offset => $status) {
            $before = $this->article("Pubblico {$status}");
            $blocker = $this->article("Titolo riservato {$status}", $status);
            $beyond = $this->article("Oltre il gap {$status}");
            $cluster = $this->cluster("Gap {$status}", 10 + $offset, [$before, $blocker, $beyond]);

            $html = $this->get(route('percorsi.index'))->assertOk()->getContent();
            $preview = $this->previewHtml($html, $cluster);

            $this->assertStringContainsString("Pubblico {$status}", $preview);
            $this->assertStringNotContainsString("Titolo riservato {$status}", $html);
            $this->assertStringNotContainsString("Oltre il gap {$status}", $preview);
        }
    }

    public function test_preview_markup_is_progressive_accessible_and_keeps_article_and_path_links_distinct(): void
    {
        $article = $this->article('Tappa collegata');
        $cluster = $this->cluster('Percorso accessibile', 10, [$article]);

        $response = $this->get(route('percorsi.index'))->assertOk();

        $response
            ->assertSee('type="button" aria-expanded="false" aria-controls="path-preview-'.$cluster->id.'" hidden', false)
            ->assertSee('id="path-preview-'.$cluster->id.'"', false)
            ->assertSee('href="'.route('articolo', $article->slug).'"', false)
            ->assertSee('href="'.route('percorsi.show', $cluster->slug).'"', false)
            ->assertSee('Anteprima delle tappe');

        $this->get(route('percorsi.show', $cluster->slug))
            ->assertOk()
            ->assertSee('Tappa collegata');
    }

    public function test_batch_resolution_is_constant_query_cost_for_one_or_six_paths(): void
    {
        $one = $this->indexQueryCount(1);

        ContentCluster::query()->delete();
        Article::query()->delete();

        $six = $this->indexQueryCount(6);

        $this->assertSame($one, $six);
    }

    public function test_seventh_path_preview_is_loaded_only_on_page_two(): void
    {
        $clusters = collect(range(1, 7))->map(function (int $index): ContentCluster {
            return $this->cluster(
                sprintf('Percorso %02d', $index),
                $index,
                [$this->article(sprintf('Tappa pagina %02d', $index))],
            );
        });

        $first = $this->get(route('percorsi.index'))->assertOk();
        $second = $this->get(route('percorsi.index', ['page' => 2]))->assertOk();
        $seventh = $clusters->last();

        $first->assertDontSee('path-preview-'.$seventh->id, false);
        $second->assertSee('Tappa pagina 07');
        $second->assertSee('path-preview-'.$seventh->id, false);
    }

    private function indexQueryCount(int $paths): int
    {
        foreach (range(1, $paths) as $index) {
            $this->cluster("Query {$index}", $index, [$this->article("Query tappa {$index}")]);
        }

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('percorsi.index'))->assertOk();

        return count($queries);
    }

    /** @param list<Article> $articles */
    private function cluster(string $name, int $sortOrder, array $articles = []): ContentCluster
    {
        $cluster = ContentCluster::factory()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'publish_at' => null,
            'sort_order' => $sortOrder,
        ]);

        foreach ($articles as $index => $article) {
            $cluster->articles()->attach($article->id, [
                'position' => ($index + 1) * 10,
                'is_primary' => $index === 0,
            ]);
        }

        return $cluster;
    }

    private function article(string $title, string $status = Article::STATUS_PUBLISHED): Article
    {
        $author = User::query()->first() ?? User::factory()->create();

        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => $status,
            'read_minutes' => 1,
            'published_at' => $status === Article::STATUS_SCHEDULED ? now()->addDay() : now()->subDay(),
        ]);
    }

    /** @param list<string> $titles */
    private function assertPreviewTitles(string $html, ContentCluster $cluster, array $titles): void
    {
        $preview = $this->previewHtml($html, $cluster);

        foreach ($titles as $title) {
            $this->assertStringContainsString($title, $preview);
        }
    }

    private function previewHtml(string $html, ContentCluster $cluster): string
    {
        preg_match('/<div class="path-card__preview" id="path-preview-'.$cluster->id.'".*?<\/div>/s', $html, $matches);

        return $matches[0] ?? '';
    }
}
