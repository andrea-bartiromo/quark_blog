<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ArticlePathNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticlePathNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create();
    }

    public function test_article_without_cluster_has_no_continuation_box(): void
    {
        $article = $this->article('Senza percorso');

        $this->get(route('articolo', $article->slug))
            ->assertOk()
            ->assertDontSee('Continua il percorso');
    }

    public function test_inactive_cluster_has_no_continuation_box(): void
    {
        $article = $this->article('Cluster inattivo');
        $cluster = ContentCluster::factory()->create(['is_active' => false]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        $this->get(route('articolo', $article->slug))
            ->assertOk()
            ->assertDontSee('Continua il percorso');
    }

    public function test_primary_cluster_wins_over_secondary_cluster(): void
    {
        $article = $this->article('Articolo condiviso');
        $secondary = ContentCluster::factory()->create(['name' => 'Secondario', 'slug' => 'secondario', 'is_active' => true, 'sort_order' => 1]);
        $primary = ContentCluster::factory()->create(['name' => 'Primario', 'slug' => 'primario', 'is_active' => true, 'sort_order' => 99]);
        $secondary->articles()->attach($article->id, ['position' => 10, 'is_primary' => false]);
        $primary->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        $this->get(route('articolo', $article->slug))
            ->assertOk()
            ->assertSee('Continua il percorso')
            ->assertSee('Primario')
            ->assertDontSee('Secondario');
    }

    public function test_secondary_only_membership_uses_deterministic_cluster_fallback(): void
    {
        $article = $this->article('Fallback deterministico');
        $later = ContentCluster::factory()->create(['name' => 'Zeta', 'slug' => 'zeta', 'is_active' => true, 'sort_order' => 20]);
        $first = ContentCluster::factory()->create(['name' => 'Alfa', 'slug' => 'alfa', 'is_active' => true, 'sort_order' => 10]);
        $later->articles()->attach($article->id, ['position' => 10, 'is_primary' => false]);
        $first->articles()->attach($article->id, ['position' => 10, 'is_primary' => false]);

        $navigation = app(ArticlePathNavigation::class)->forArticle($article);

        $this->assertNotNull($navigation);
        $this->assertSame($first->id, $navigation['cluster']->id);
    }

    public function test_progress_and_previous_next_use_only_published_members_in_manual_order(): void
    {
        // I membri non pubblicati vanno DOPO il prefisso pubblico contiguo,
        // non interspersi prima di `current`: dal contratto continuous-
        // published-prefix (ContentClusterPublicSequence — STOP al primo
        // membro non pubblico, mai riprendere dopo un gap), un gap PRIMA di
        // `current` lo renderebbe esso stesso irraggiungibile (navigazione
        // null), un caso diverso e già coperto altrove
        // (ContentClusterContinuousPublishedPrefixTest::
        // test_navigation_never_jumps_over_a_gap_to_a_later_published_member).
        // Qui l'obiettivo resta dimostrare che i membri non pubblicati sono
        // esclusi dal conteggio/progress, non la semantica del gap.
        $cluster = ContentCluster::factory()->create(['name' => 'IA spiegata', 'slug' => 'ia-spiegata', 'is_active' => true]);
        $first = $this->article('Primo pubblico');
        $current = $this->article('Secondo pubblico');
        $last = $this->article('Terzo pubblico');
        $scheduled = $this->article('Scheduled segreto', Article::STATUS_SCHEDULED, now()->addDay());
        $draft = $this->article('Draft segreto', Article::STATUS_DRAFT, null);
        $review = $this->article('Review segreto', Article::STATUS_REVIEW, null);

        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => false],
            $current->id => ['position' => 20, 'is_primary' => true],
            $last->id => ['position' => 30, 'is_primary' => false],
            $scheduled->id => ['position' => 40, 'is_primary' => false],
            $draft->id => ['position' => 50, 'is_primary' => false],
            $review->id => ['position' => 60, 'is_primary' => false],
        ]);

        $response = $this->get(route('articolo', $current->slug));

        $response->assertOk()
            ->assertSee('2 di 3')
            ->assertSee('Primo pubblico')
            ->assertSee('Terzo pubblico')
            ->assertDontSee('Scheduled segreto')
            ->assertDontSee('Draft segreto')
            ->assertDontSee('Review segreto')
            ->assertSee(route('percorsi.show', $cluster->slug), false);
    }

    public function test_first_last_and_single_item_navigation_do_not_render_empty_placeholders(): void
    {
        $cluster = ContentCluster::factory()->create(['name' => 'Sequenza', 'slug' => 'sequenza', 'is_active' => true]);
        $first = $this->article('Primo');
        $last = $this->article('Ultimo');
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true],
            $last->id => ['position' => 20, 'is_primary' => false],
        ]);

        $this->get(route('articolo', $first->slug))
            ->assertOk()
            ->assertSee('1 di 2')
            ->assertDontSee('Precedente')
            ->assertSee('Successivo')
            ->assertSee('Ultimo');

        $this->get(route('articolo', $last->slug))
            ->assertOk()
            ->assertSee('2 di 2')
            ->assertSee('Precedente')
            ->assertDontSee('Successivo');

        $singleCluster = ContentCluster::factory()->create(['name' => 'Singolo', 'slug' => 'singolo', 'is_active' => true]);
        $single = $this->article('Solo articolo');
        $singleCluster->articles()->attach($single->id, ['position' => 10, 'is_primary' => true]);

        $this->get(route('articolo', $single->slug))
            ->assertOk()
            ->assertSee('1 di 1')
            ->assertDontSee('Precedente')
            ->assertDontSee('Successivo')
            ->assertSee('Vedi tutto il percorso');
    }

    public function test_removed_membership_or_deleted_cluster_removes_continuation(): void
    {
        $article = $this->article('Membership rimossa');
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        $cluster->articles()->detach($article->id);
        $this->assertNull(app(ArticlePathNavigation::class)->forArticle($article));

        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        $cluster->delete();
        $this->assertNull(app(ArticlePathNavigation::class)->forArticle($article));
    }

    public function test_navigation_query_count_is_constant_for_cluster_size(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $current = $this->article('Query corrente');
        $cluster->articles()->attach($current->id, ['position' => 10, 'is_primary' => true]);

        foreach (range(1, 12) as $index) {
            $member = $this->article('Membro '.$index);
            $cluster->articles()->attach($member->id, ['position' => ($index + 1) * 10, 'is_primary' => false]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $navigation = app(ArticlePathNavigation::class)->forArticle($current);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Budget +1 rispetto al precedente 2: ContentClusterPublicSequence
        // (continuous-published-prefix) verifica esplicitamente
        // Article::published() con una query bounded dedicata invece di
        // ricostruire l'eligibilità dallo stato già caricato — costo
        // costante (1 query indipendentemente dal numero di membri), non un
        // N+1: cluster lookup (1) + members ordinati (1) + published check
        // bounded (1) = 3, invariato al crescere del corpus.
        $this->assertNotNull($navigation);
        $this->assertLessThanOrEqual(3, $queryCount);
    }

    private function article(string $title, string $status = Article::STATUS_PUBLISHED, $publishedAt = null): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo articolo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'intelligenza-artificiale',
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $publishedAt ?? ($status === Article::STATUS_PUBLISHED ? now()->subMinute() : null),
        ]);
    }
}
