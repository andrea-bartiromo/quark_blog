<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveCategoryPublicLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_keeps_human_label_for_published_article_after_category_is_deactivated(): void
    {
        $category = Category::create([
            'name' => 'Categoria Archiviata',
            'slug' => 'categoria-archiviata',
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $author = User::factory()->create(['role' => 'author']);

        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo ancora pubblico',
            'slug' => 'articolo-ancora-pubblico',
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => $category->slug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 2,
            'views' => 999999,
            'verification_status' => 'unverified',
        ]);

        $response = $this->get(route('autore', $author));

        $response->assertOk();
        $response->assertSee('badge--categoria-archiviata', false);
        $response->assertSee('>Categoria Archiviata<', false);

        // La categoria disattivata serve ancora come label degli articoli
        // già pubblicati, ma non deve tornare tra i link di navigazione.
        $response->assertDontSee('href="'.route('categoria', $category->slug).'"', false);
    }
}
