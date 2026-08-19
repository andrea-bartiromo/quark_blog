<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S6 FASE 4 — Admin\ArticleController::duplicate() derivava lo slug della
 * copia con Str::slug($title).'-'.time(): granularità di 1 secondo, quindi
 * due doppi-click ravvicinati (double-submit) sullo stesso "Duplica" entro
 * lo stesso secondo collidevano sulla colonna UNIQUE articles.slug con una
 * QueryException non gestita.
 */
class ArticleDuplicateSlugCollisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicating_the_same_article_twice_in_quick_succession_does_not_crash_with_an_unhandled_500(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo originale',
            'slug' => 'articolo-originale-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->actingAs($editor)
            ->post(route('admin.articles.duplicate', $article))
            ->assertRedirect();

        $response = $this->actingAs($editor)
            ->post(route('admin.articles.duplicate', $article));

        $this->assertNotSame(500, $response->getStatusCode(), 'La seconda duplicazione ravvicinata ha prodotto un 500 non gestito (violazione UNIQUE su articles.slug, suffisso time() a granularità di 1 secondo).');

        $copies = Article::where('title', 'Copia di — '.$article->title)->get();
        $this->assertSame(2, $copies->count());
        $this->assertSame($copies->pluck('slug')->count(), $copies->pluck('slug')->unique()->count());
    }
}
