<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentClusterPublicTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editor = User::factory()->create(['role' => 'editor']);
    }

    public function test_index_shows_only_active_clusters_and_counts_only_published_articles(): void
    {
        $cluster = ContentCluster::factory()->create([
            'name' => 'IA spiegata',
            'slug' => 'ia-spiegata',
            'short_description' => 'Un percorso pubblico.',
            'is_active' => true,
        ]);
        ContentCluster::factory()->create([
            'name' => 'Percorso segreto',
            'slug' => 'segreto',
            'is_active' => false,
        ]);

        $published = $this->article('Pubblicato', Article::STATUS_PUBLISHED, now()->subDay());
        $scheduled = $this->article('Futuro segreto', Article::STATUS_SCHEDULED, now()->addDay());
        $draft = $this->article('Bozza segreta', Article::STATUS_DRAFT);
        $cluster->articles()->attach([
            $published->id => ['position' => 10],
            $scheduled->id => ['position' => 20],
            $draft->id => ['position' => 30],
        ]);
        $cluster->update(['pillar_article_id' => $scheduled->id]);

        $this->get(route('percorsi.index'))
            ->assertOk()
            ->assertSee('IA spiegata')
            ->assertSee('1 articolo pubblicato')
            ->assertDontSee('Percorso segreto')
            ->assertDontSee('Futuro segreto')
            ->assertDontSee('Bozza segreta');
    }

    public function test_cluster_cover_uses_public_media_root_thumbnail_class_and_social_metadata(): void
    {
        $cluster = ContentCluster::factory()->create([
            'name' => 'Percorso con cover',
            'slug' => 'percorso-cover',
            'cover_image' => 'articles/covers/percorso.webp',
            'is_active' => true,
        ]);
        $cluster->articles()->attach($this->article('Articolo cover', Article::STATUS_PUBLISHED, now()->subHour())->id, ['position' => 10]);

        $expected = asset('assets/img/articles/covers/percorso.webp');

        $this->get(route('percorsi.index'))
            ->assertOk()
            ->assertSee('class="article-card__thumb"', false)
            ->assertSee('src="'.$expected.'"', false)
            ->assertDontSee('src="'.asset('articles/covers/percorso.webp').'"', false);

        $this->get(route('percorsi.show', $cluster->slug))
            ->assertOk()
            ->assertSee('property="og:image" content="'.$expected.'"', false)
            ->assertSee('name="twitter:image" content="'.$expected.'"', false);
    }

    public function test_detail_filters_non_public_articles_preserves_manual_order_and_hides_non_public_pillar(): void
    {
        $cluster = ContentCluster::factory()->create([
            'name' => 'IA spiegata',
            'slug' => 'ia-spiegata',
            'description' => 'Percorso editoriale.',
            'seo_title' => 'IA spiegata bene',
            'seo_description' => 'Descrizione SEO del percorso.',
            'is_active' => true,
        ]);
        $second = $this->article('Secondo articolo', Article::STATUS_PUBLISHED, now()->subDay());
        $first = $this->article('Primo articolo', Article::STATUS_PUBLISHED, now()->subDays(2));
        $scheduled = $this->article('Articolo programmato segreto', Article::STATUS_SCHEDULED, now()->addDay());
        $draft = $this->article('Bozza segreta', Article::STATUS_DRAFT);
        $cluster->articles()->attach([
            $first->id => ['position' => 10],
            $scheduled->id => ['position' => 20],
            $second->id => ['position' => 30],
            $draft->id => ['position' => 40],
        ]);
        $cluster->update(['pillar_article_id' => $scheduled->id]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk()
            ->assertSee('IA spiegata bene')
            ->assertSee('Descrizione SEO del percorso.')
            ->assertSee(route('percorsi.show', $cluster->slug), false)
            ->assertSee('CollectionPage')
            ->assertSee('ItemList')
            ->assertSee('BreadcrumbList')
            ->assertDontSee('Articolo programmato segreto')
            ->assertDontSee('Bozza segreta')
            ->assertDontSee('Da dove iniziare');

        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Secondo articolo'), strpos($html, 'Primo articolo'));
    }

    public function test_unknown_and_inactive_clusters_return_404_without_metadata_leakage(): void
    {
        $inactive = ContentCluster::factory()->create([
            'name' => 'Titolo privato',
            'slug' => 'privato',
            'seo_description' => 'Metadata privato',
            'is_active' => false,
        ]);

        $this->get(route('percorsi.show', 'missing'))->assertNotFound();
        $this->get(route('percorsi.show', $inactive->slug))
            ->assertNotFound()
            ->assertDontSee('Metadata privato');
    }

    public function test_sitemap_includes_only_active_clusters_with_public_articles(): void
    {
        $public = ContentCluster::factory()->create(['slug' => 'pubblico', 'is_active' => true]);
        $scheduledOnly = ContentCluster::factory()->create(['slug' => 'solo-futuro', 'is_active' => true]);
        $inactive = ContentCluster::factory()->create(['slug' => 'inattivo', 'is_active' => false]);

        $public->articles()->attach($this->article('Pubblico', Article::STATUS_PUBLISHED, now()->subHour())->id, ['position' => 10]);
        $scheduledOnly->articles()->attach($this->article('Futuro', Article::STATUS_SCHEDULED, now()->addHour())->id, ['position' => 10]);
        $inactive->articles()->attach($this->article('Inattivo', Article::STATUS_PUBLISHED, now()->subHour())->id, ['position' => 10]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/percorsi</loc>', false)
            ->assertSee('/percorsi/pubblico</loc>', false)
            ->assertDontSee('/percorsi/solo-futuro</loc>', false)
            ->assertDontSee('/percorsi/inattivo</loc>', false);
    }

    private function article(string $title, string $status, $publishedAt = null): Article
    {
        return Article::create([
            'user_id' => $this->editor->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }
}
