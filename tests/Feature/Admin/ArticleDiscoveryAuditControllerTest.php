<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Services\Discovery\ArticleDiscoveryAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ArticleDiscoveryAuditControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_the_discovery_audit(): void
    {
        $this->get(route('admin.article-discovery-audit'))->assertRedirect(route('login'));
    }

    public function test_editor_sees_all_three_discovery_classes_and_article_detail(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $article = $this->publishedArticle($editor);

        $response = $this->actingAs($editor)->get(route('admin.article-discovery-audit'));

        $response->assertOk();
        $response->assertSee('Discovery articoli');
        $response->assertSee('Zero percorsi');
        $response->assertSee('Un percorso');
        $response->assertSee('Percorsi multipli');
        $response->assertSee($article->title);
        $response->assertSee('MULTIPLE_PATHS');
    }

    public function test_controller_executes_the_expensive_audit_exactly_once(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->mock(ArticleDiscoveryAuditService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('audit')->once()->andReturn(collect());
        });

        $this->actingAs($editor)
            ->get(route('admin.article-discovery-audit'))
            ->assertOk()
            ->assertSee('Nessun articolo pubblico da analizzare.');
    }

    private function publishedArticle(User $author): Article
    {
        Category::create(['name' => 'Discovery Admin', 'slug' => 'discovery-admin']);

        return Article::withoutEvents(fn () => Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo discovery admin',
            'slug' => 'articolo-discovery-admin',
            'excerpt' => 'Excerpt',
            'body' => '<p>Body</p>',
            'category' => 'discovery-admin',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 1,
        ]));
    }
}
