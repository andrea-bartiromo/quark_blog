<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentClusterIndexPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_index_paginates_public_paths_in_groups_of_six(): void
    {
        foreach ([6, 7, 12, 13] as $total) {
            ContentCluster::query()->delete();
            $this->createPublicPaths($total);

            $lastPage = (int) ceil($total / 6);
            for ($page = 1; $page <= $lastPage; $page++) {
                $response = $this->get(route('percorsi.index', $page === 1 ? [] : ['page' => $page]));

                $response->assertOk();
                $this->assertSame(
                    min(6, $total - (($page - 1) * 6)),
                    substr_count($response->getContent(), 'data-percorso-id='),
                );

                if ($total === 6) {
                    $response->assertDontSee('aria-label="Paginazione Percorsi"', false);
                }
            }
        }
    }

    public function test_seventh_path_appears_only_on_the_second_page(): void
    {
        $this->createPublicPaths(7);
        $seventh = ContentCluster::query()->where('name', 'Percorso 07')->firstOrFail();

        $this->get(route('percorsi.index'))
            ->assertOk()
            ->assertDontSee('data-percorso-id="'.$seventh->id.'"', false);

        $this->get(route('percorsi.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('data-percorso-id="'.$seventh->id.'"', false);
    }

    public function test_order_is_stable_and_paths_never_repeat_between_pages(): void
    {
        $paths = collect([
            ['name' => 'Zulu', 'sort_order' => 20],
            ['name' => 'Beta', 'sort_order' => 10],
            ['name' => 'Alpha', 'sort_order' => 10],
            ['name' => 'Gamma', 'sort_order' => 10],
            ['name' => 'Delta', 'sort_order' => 20],
            ['name' => 'Epsilon', 'sort_order' => 20],
            ['name' => 'Eta', 'sort_order' => 30],
        ])->map(fn (array $attributes) => $this->createPublicPath($attributes));

        $pageOne = $this->pathIds($this->get(route('percorsi.index'))->assertOk()->getContent());
        $pageTwo = $this->pathIds($this->get(route('percorsi.index', ['page' => 2]))->assertOk()->getContent());

        $expected = $paths->sortBy([
            ['sort_order', 'asc'],
            ['name', 'asc'],
            ['id', 'asc'],
        ])->pluck('id')->all();

        $this->assertSame($expected, [...$pageOne, ...$pageTwo]);
        $this->assertSame([], array_values(array_intersect($pageOne, $pageTwo)));
    }

    public function test_future_and_inactive_paths_remain_hidden_at_a_frozen_instant(): void
    {
        CarbonImmutable::setTestNow('2026-08-30 12:00:00 UTC');

        $visible = $this->createPublicPath(['name' => 'Visibile ora']);
        $future = $this->createPublicPath([
            'name' => 'Fisica fondamentale',
            'publish_at' => '2026-09-07 13:30:00 UTC',
        ]);
        $inactive = $this->createPublicPath(['name' => 'Inattivo', 'is_active' => false]);

        $response = $this->get(route('percorsi.index'))->assertOk();

        $response->assertSee('data-percorso-id="'.$visible->id.'"', false);
        $response->assertDontSee('data-percorso-id="'.$future->id.'"', false);
        $response->assertDontSee('data-percorso-id="'.$inactive->id.'"', false);
        $response->assertDontSee('Fisica fondamentale');
        $this->get(route('percorsi.show', $future->slug))->assertNotFound();
    }

    public function test_card_keeps_article_count_and_published_pillar_without_n_plus_one_contract_changes(): void
    {
        $cluster = $this->createPublicPath(['name' => 'Percorso con pillar']);
        $author = User::factory()->create();
        $pillar = Article::create([
            'user_id' => $author->id,
            'title' => 'Il punto di partenza',
            'slug' => 'il-punto-di-partenza',
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 2,
            'published_at' => now()->subDay(),
        ]);
        $cluster->articles()->attach($pillar->id, ['position' => 10, 'is_primary' => true]);
        $cluster->update(['pillar_article_id' => $pillar->id]);

        $response = $this->get(route('percorsi.index'))->assertOk();

        $response->assertSee('1 articolo pubblicato');
        $response->assertSee('Da qui si parte');
        $response->assertSee('Il punto di partenza');

        $this->get(route('percorsi.show', $cluster->slug))
            ->assertOk()
            ->assertSee('Percorso con pillar');
    }

    public function test_pagination_has_clean_canonicals_rel_links_and_italian_accessible_navigation(): void
    {
        $this->createPublicPaths(7);

        $first = $this->get(route('percorsi.index', ['utm_source' => 'test']))->assertOk();
        $first->assertSee('<link rel="canonical" href="'.route('percorsi.index').'">', false);
        $first->assertSee('<link rel="next" href="'.route('percorsi.index', ['page' => 2]).'">', false);
        $first->assertDontSee('rel="prev"', false);
        $first->assertSee('aria-label="Paginazione Percorsi"', false);
        $first->assertSee('aria-label="Pagina successiva"', false);
        $first->assertSee('Successiva');
        $first->assertDontSee('aria-disabled="true"', false);

        $second = $this->get(route('percorsi.index', ['page' => 2, 'utm_source' => 'test']))->assertOk();
        $second->assertSee('<link rel="canonical" href="'.route('percorsi.index', ['page' => 2]).'">', false);
        $second->assertSee('<link rel="prev" href="'.route('percorsi.index').'">', false);
        $second->assertDontSee('rel="next"', false);
        $second->assertSee('aria-current="page">2</span>', false);
        $second->assertSee('href="'.route('percorsi.index').'" aria-label="Pagina precedente"', false);
        $second->assertDontSee('href="'.route('percorsi.index', ['page' => 1]).'" aria-label="Pagina precedente"', false);
        $second->assertSee('Precedente');
        $second->assertDontSee('aria-disabled="true"', false);
    }

    public function test_out_of_range_page_returns_not_found(): void
    {
        $this->createPublicPaths(7);

        $this->get(route('percorsi.index', ['page' => 3]))->assertNotFound();
    }

    private function createPublicPaths(int $count): void
    {
        foreach (range(1, $count) as $index) {
            $this->createPublicPath([
                'name' => sprintf('Percorso %02d', $index),
                'sort_order' => $index,
            ]);
        }
    }

    private function createPublicPath(array $attributes = []): ContentCluster
    {
        return ContentCluster::factory()->create(array_merge([
            'is_active' => true,
            'publish_at' => null,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ], $attributes));
    }

    /** @return list<int> */
    private function pathIds(string $html): array
    {
        preg_match_all('/data-percorso-id="(\d+)"/', $html, $matches);

        return array_map('intval', $matches[1]);
    }
}
