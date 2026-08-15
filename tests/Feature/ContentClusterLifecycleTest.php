<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentClusterLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_path_defaults_to_complete_and_does_not_render_continuation_state(): void
    {
        $cluster = ContentCluster::factory()->create([
            'slug' => 'legacy-complete',
            'is_active' => true,
        ]);
        $article = $this->publishedArticle('Legacy article');
        $cluster->articles()->attach($article->id, ['position' => 10]);

        $cluster->refresh();

        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $cluster->lifecycle_status);
        $this->get(route('percorsi.show', $cluster->slug))
            ->assertOk()
            ->assertSee('Fine del percorso')
            ->assertDontSee('Il percorso continua')
            ->assertDontSee('Percorso in aggiornamento')
            ->assertDontSee('Non siamo ancora arrivati alla fine.');
    }

    public function test_updating_path_with_published_content_renders_refined_public_continuation_on_detail_and_last_article(): void
    {
        $cluster = ContentCluster::factory()->create([
            'name' => 'Percorso in aggiornamento',
            'slug' => 'percorso-in-aggiornamento',
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ]);
        $first = $this->publishedArticle('Prima tappa');
        $last = $this->publishedArticle('Ultima tappa disponibile');
        $scheduled = $this->scheduledArticle('Tappa futura');
        $cluster->articles()->attach([
            $first->id => ['position' => 10],
            $scheduled->id => ['position' => 20],
            $last->id => ['position' => 30],
        ]);

        $detailResponse = $this->get(route('percorsi.show', $cluster->slug));
        $detailResponse
            ->assertOk()
            ->assertSee('Il percorso continua')
            ->assertSee('Non siamo ancora arrivati alla fine.')
            ->assertSee('2 tappe disponibili · Percorso in aggiornamento')
            ->assertSee('Torna presto: la prossima tappa arriverà qui.')
            ->assertSee('Qui riprenderà il viaggio.')
            ->assertDontSee('3 tappe disponibili')
            ->assertDontSee('Tappa futura')
            ->assertDontSee('quando il lavoro editoriale sarà pronto')
            ->assertDontSee('Avvisami quando continua');

        $detailText = html_entity_decode(strip_tags($detailResponse->getContent()), ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString(
            "Stiamo preparando nuovi capitoli per continuare l'esplorazione.",
            $detailText
        );

        $firstArticleResponse = $this->get(route('articolo', $first->slug));
        $firstArticleResponse
            ->assertOk()
            ->assertDontSee('Il prossimo capitolo arriverà qui.');
        $firstArticleText = html_entity_decode(strip_tags($firstArticleResponse->getContent()), ENT_QUOTES | ENT_HTML5);
        $this->assertStringNotContainsString(
            "Hai raggiunto l'ultima tappa disponibile.",
            $firstArticleText
        );

        $lastArticleResponse = $this->get(route('articolo', $last->slug));
        $lastArticleResponse
            ->assertOk()
            ->assertSee('Il percorso continua')
            ->assertSee('Il prossimo capitolo arriverà qui.')
            ->assertSee('Vedi tutto il percorso')
            ->assertDontSee('Tappa futura')
            ->assertDontSee('quando il lavoro editoriale sarà pronto')
            ->assertDontSee('Avvisami quando continua');
        $lastArticleText = html_entity_decode(strip_tags($lastArticleResponse->getContent()), ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString(
            "Hai raggiunto l'ultima tappa disponibile.",
            $lastArticleText
        );
    }

    public function test_updating_path_without_public_articles_does_not_render_continuation_state(): void
    {
        $cluster = ContentCluster::factory()->create([
            'slug' => 'updating-without-public',
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ]);
        $scheduled = $this->scheduledArticle('Solo futuro');
        $cluster->articles()->attach($scheduled->id, ['position' => 10]);

        $this->get(route('percorsi.show', $cluster->slug))
            ->assertOk()
            ->assertDontSee('Percorso in aggiornamento')
            ->assertDontSee('Non siamo ancora arrivati alla fine.')
            ->assertDontSee('Solo futuro');
    }

    public function test_editor_can_set_lifecycle_status_and_admin_form_exposes_explicit_control(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $cluster = ContentCluster::factory()->create([
            'name' => 'Percorso editoriale',
            'slug' => 'percorso-editoriale',
        ]);

        $this->actingAs($editor)
            ->get(route('admin.content-clusters.edit', $cluster))
            ->assertOk()
            ->assertSee('Stato del Percorso')
            ->assertSee('name="lifecycle_status"', false);

        $this->actingAs($editor)
            ->put(route('admin.content-clusters.update', $cluster), [
                'name' => $cluster->name,
                'slug' => $cluster->slug,
                'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('content_clusters', [
            'id' => $cluster->id,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ]);
    }

    private function publishedArticle(string $title): Article
    {
        return $this->article($title, Article::STATUS_PUBLISHED, now()->subHour());
    }

    private function scheduledArticle(string $title): Article
    {
        return $this->article($title, Article::STATUS_SCHEDULED, now()->addDay());
    }

    private function article(string $title, string $status, $publishedAt): Article
    {
        $author = User::factory()->create();

        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => $status,
            'read_minutes' => 1,
            'published_at' => $publishedAt,
        ]);
    }
}
