<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\CategoryPublicationReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prompt 4 (pianificazione categorie): audit di sola lettura, mai
 * bloccante, che segnala una categoria programmata a rischio di aprirsi
 * incompleta — stesso pattern già in uso per i Percorsi
 * (ContentClusterHealth). Copre sia il servizio (riusato anche
 * dall'anteprima nell'editor admin, Prompt 3) sia il comando Artisan
 * category:publication-readiness.
 */
class CategoryPublicationReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function scheduledCategory(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Categoria Programmata',
            'slug' => 'categoria-programmata',
            'is_active' => true,
            'status' => Category::STATUS_SCHEDULED,
            'published_at' => now()->addWeek(),
        ], $overrides));
    }

    public function test_a_fully_incomplete_scheduled_category_reports_every_finding(): void
    {
        $category = $this->scheduledCategory();

        $result = app(CategoryPublicationReadiness::class)->evaluate($category);

        $this->assertFalse($result['ready']);
        $this->assertEqualsCanonicalizing([
            'MISSING_DESCRIPTION',
            'MISSING_IMAGE',
            'MISSING_COLOR',
            'NO_SCHEDULED_OR_PUBLISHED_ARTICLES',
            'NO_RELATED_PERCORSO',
        ], $result['findings']);
    }

    public function test_a_complete_scheduled_category_reports_no_findings(): void
    {
        $category = $this->scheduledCategory([
            'description' => 'Una descrizione editoriale completa.',
            'image' => 'categoria.jpg',
            'color' => '#0d9488',
        ]);

        $author = $this->author();
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo programmato per la categoria',
            'slug' => 'articolo-programmato-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => $category->slug,
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDays(2),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach($article->id, ['position' => 10]);

        $result = app(CategoryPublicationReadiness::class)->evaluate($category->fresh());

        $this->assertTrue($result['ready']);
        $this->assertSame([], $result['findings']);
    }

    public function test_artisan_command_lists_only_scheduled_categories_and_their_findings(): void
    {
        $this->scheduledCategory(['name' => 'Radar', 'slug' => 'radar']);
        Category::create([
            'name' => 'Già Pubblica',
            'slug' => 'gia-pubblica',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ]);

        $this->artisan('category:publication-readiness')
            ->assertExitCode(0)
            ->expectsOutputToContain('Radar')
            ->doesntExpectOutputToContain('Già Pubblica');
    }

    public function test_artisan_command_json_output_is_valid_and_read_only(): void
    {
        $category = $this->scheduledCategory();

        $this->artisan('category:publication-readiness --json')->assertExitCode(0);

        // Sola lettura: la categoria non deve essere stata toccata.
        $this->assertSame(Category::STATUS_SCHEDULED, $category->fresh()->status);
    }
}
