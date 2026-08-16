<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Support\PathVisualSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riconciliazione PASS 2A (firma visiva, #201) + PASS 2B/Kairus Path
 * Visual Language (#202-#205): sistemi costruiti in fasi distinte, mai
 * visti tutti insieme in un'unica risposta prima di questo test. Copre
 * l'intersezione che nessuna suite isolata testava — che la firma
 * deterministica, l'ingresso atmosferico + il cambio di registro della
 * Visual Library, e le cover reali degli articoli coesistano sulla
 * stessa pagina senza spegnersi a vicenda.
 */
class PathVisualIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_atmosphere_transition_and_article_covers_all_render_together(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'lifecycle_status' => 'updating',
        ]);

        $author = User::factory()->create();
        // Tre articoli spazio + uno energia: un vero scarto tematico, che
        // giustifica sia l'ingresso atmosferico (categoria dominante:
        // spazio) sia il cambio di registro (energia).
        $categories = ['spazio', 'spazio', 'spazio', 'energia'];
        $articles = [];
        foreach (['Uno', 'Due', 'Tre', 'Quattro'] as $i => $title) {
            $article = Article::create([
                'user_id' => $author->id,
                'title' => $title,
                'slug' => str($title)->slug().'-'.uniqid(),
                'body' => '<p>Corpo.</p>',
                'excerpt' => 'Estratto.',
                'category' => $categories[$i],
                'status' => Article::STATUS_PUBLISHED,
                'cover_image' => 'articles/copertina-'.$i.'.jpg',
                'read_minutes' => 3,
                'published_at' => now()->subDays($i + 1),
            ]);
            $articles[] = $article;
            $cluster->articles()->attach($article->id, ['position' => ($i + 1) * 10, 'is_primary' => $i === 0]);
        }
        $cluster->update(['pillar_article_id' => $articles[0]->id]);

        $expectedSignature = PathVisualSignature::cssClass($cluster->fresh());

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        // Firma deterministica (Pass 2A) sulla sezione .path-detail.
        $response->assertSee($expectedSignature, false);
        // Ingresso atmosferico (sempre presente) e cambio di registro
        // (presente perché la sequenza attraversa spazio -> energia).
        $response->assertSee('path-entrance__atmosphere', false);
        $response->assertSee('path-transition__crop', false);
        // Cover reali degli articoli nella timeline, mai spostate o
        // sostituite dalla Visual Library.
        $response->assertSee('path-step__cover', false);
        $response->assertSee('copertina-0.jpg', false);
    }
}
