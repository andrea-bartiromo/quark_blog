<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSecondaryCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    // La migrazione delle categorie ne seed gia' alcune di default (dallo
    // stesso config('laboratorio.categories') usato come fallback altrove):
    // usa updateOrCreate cosi' i test possono regolarne lo stato senza
    // collidere con lo slug unique gia' popolato da RefreshDatabase (stessa
    // convenzione di HomeCategoriesTest::category()).
    private function category(string $name, string $slug, bool $active = true): Category
    {
        return Category::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'is_active' => $active,
                'sort_order' => 0,
            ]
        );
    }

    private function formData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Articolo multicategoria',
            'excerpt' => 'Sommario di prova',
            'body' => 'Corpo articolo di prova.',
            'category' => 'spazio',
            'status' => Article::STATUS_DRAFT,
        ], $overrides);
    }

    public function test_editor_can_create_article_with_two_secondary_categories(): void
    {
        $editor = $this->editor();
        $this->category('Spazio', 'spazio');
        $physics = $this->category('Fisica', 'fisica');
        $technology = $this->category('Tecnologia & Società', 'societa');

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), $this->formData([
            'secondary_categories' => [$physics->id, $technology->id],
        ]));

        $response->assertRedirect(route('admin.articles'));

        $article = Article::where('title', 'Articolo multicategoria')->firstOrFail();
        $this->assertSame('spazio', $article->category);
        $this->assertDatabaseHas('article_category', ['article_id' => $article->id, 'category_id' => $physics->id]);
        $this->assertDatabaseHas('article_category', ['article_id' => $article->id, 'category_id' => $technology->id]);
        $this->assertDatabaseCount('article_category', 2);
    }

    public function test_update_replaces_secondary_category_snapshot_and_can_clear_it(): void
    {
        $editor = $this->editor();
        $this->category('Spazio', 'spazio');
        $physics = $this->category('Fisica', 'fisica');
        $technology = $this->category('Tecnologia & Società', 'societa');

        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo da aggiornare',
            'slug' => 'articolo-da-aggiornare',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_DRAFT,
        ]);

        $article->belongsToMany(Category::class, 'article_category')->attach($physics->id);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $article), $this->formData([
            'title' => $article->title,
            'secondary_categories' => [$technology->id],
        ]));

        $response->assertRedirect(route('admin.articles'));
        $this->assertDatabaseMissing('article_category', ['article_id' => $article->id, 'category_id' => $physics->id]);
        $this->assertDatabaseHas('article_category', ['article_id' => $article->id, 'category_id' => $technology->id]);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $article), $this->formData([
            'title' => $article->title,
        ]));

        $response->assertRedirect(route('admin.articles'));
        $this->assertDatabaseMissing('article_category', ['article_id' => $article->id]);
    }

    public function test_more_than_two_secondary_categories_are_rejected(): void
    {
        $editor = $this->editor();
        $this->category('Spazio', 'spazio');
        $physics = $this->category('Fisica', 'fisica');
        $technology = $this->category('Tecnologia', 'societa');
        $energy = $this->category('Energia', 'energia');

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), $this->formData([
            'secondary_categories' => [$physics->id, $technology->id, $energy->id],
        ]));

        $response->assertSessionHasErrors('secondary_categories');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo multicategoria']);
    }

    public function test_primary_category_cannot_also_be_secondary(): void
    {
        $editor = $this->editor();
        $space = $this->category('Spazio', 'spazio');
        $this->category('Fisica', 'fisica');

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), $this->formData([
            'secondary_categories' => [$space->id],
        ]));

        $response->assertSessionHasErrors('secondary_categories');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo multicategoria']);
    }

    public function test_inactive_category_cannot_be_selected_as_secondary(): void
    {
        $editor = $this->editor();
        $this->category('Spazio', 'spazio');
        $inactive = $this->category('Archiviata', 'archiviata', false);

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), $this->formData([
            'secondary_categories' => [$inactive->id],
        ]));

        $response->assertSessionHasErrors('secondary_categories.0');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo multicategoria']);
    }

    public function test_duplicate_article_copies_secondary_categories(): void
    {
        $editor = $this->editor();
        $this->category('Spazio', 'spazio');
        $physics = $this->category('Fisica', 'fisica');

        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Originale multicategoria',
            'slug' => 'originale-multicategoria',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_DRAFT,
        ]);

        $article->belongsToMany(Category::class, 'article_category')->attach($physics->id);

        $response = $this->actingAs($editor)->post(route('admin.articles.duplicate', $article));
        $response->assertRedirect();

        $copy = Article::where('title', 'Copia di — Originale multicategoria')->firstOrFail();
        $this->assertDatabaseHas('article_category', ['article_id' => $copy->id, 'category_id' => $physics->id]);
    }

    public function test_edit_form_shows_secondary_category_controls_and_existing_selection(): void
    {
        $editor = $this->editor();
        $this->category('Spazio', 'spazio');
        $physics = $this->category('Fisica', 'fisica');

        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo con fisica',
            'slug' => 'articolo-con-fisica',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_DRAFT,
        ]);
        $article->belongsToMany(Category::class, 'article_category')->attach($physics->id);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('Categoria principale');
        $response->assertSee('Categorie secondarie');
        $response->assertSee('Fisica');
        $response->assertSee('name="secondary_categories[]"', false);
        $response->assertSee('value="'.$physics->id.'"', false);
        $response->assertSee('checked', false);
    }
}
