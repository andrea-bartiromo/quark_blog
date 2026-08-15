<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Support\PathVisualSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riconciliazione PASS 2A (firma visiva, #201) + PASS 2B (Visual Library,
 * #202): entrambi i sistemi sono stati costruiti in modo indipendente su
 * rami sibling e non si sono mai visti in un'unica risposta prima di
 * questo merge. Copre esattamente l'intersezione che nessuno dei due
 * suite originali testava — che la firma deterministica, le pause della
 * Visual Library e le cover reali degli articoli coesistano sulla stessa
 * pagina senza spegnersi a vicenda.
 */
class PathVisualIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_visual_breaks_and_article_covers_all_render_together(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'lifecycle_status' => 'updating',
        ]);

        $author = User::factory()->create();
        $articles = [];
        foreach (['Uno', 'Due', 'Tre', 'Quattro'] as $i => $title) {
            $article = Article::create([
                'user_id' => $author->id,
                'title' => $title,
                'slug' => str($title)->slug().'-'.uniqid(),
                'body' => '<p>Corpo.</p>',
                'excerpt' => 'Estratto.',
                'category' => 'spazio',
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
        // Pause della Visual Library (Pass 2B) — 4 articoli pubblicati,
        // quindi due break attesi.
        $count = substr_count($response->getContent(), 'path-visual-break');
        $this->assertSame(2, $count);
        // Cover reali degli articoli nella timeline, mai spostate o
        // sostituite dalla Visual Library.
        $response->assertSee('path-step__cover', false);
        $response->assertSee('copertina-0.jpg', false);
    }
}
