<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 12 (programma 100-cantieri Kairus): checklist di attivazione
 * visibile direttamente nell'elenco admin delle categorie (non solo dopo
 * "Modifica" su una singola categoria) — riusa
 * CategoryPublicationReadiness::evaluate(), mai bloccante, calcolata solo
 * per le categorie non ancora pubblicamente visibili.
 */
class CategoryAdminActivationChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_an_incomplete_draft_category_shows_its_finding_count_in_the_list(): void
    {
        Category::create([
            'name' => 'Bozza Incompleta Lista',
            'slug' => 'bozza-incompleta-lista',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.categories'));

        $response->assertOk();
        $response->assertSee('da verificare');
        $response->assertSee('Manca la descrizione', false);
    }

    public function test_a_fully_ready_draft_category_shows_ready_in_the_list(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $category = Category::create([
            'name' => 'Bozza Pronta Lista',
            'slug' => 'bozza-pronta-lista',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
            'description' => 'Descrizione completa.',
            'image' => 'placeholder.jpg',
            'color' => '#0d9488',
        ]);

        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo della bozza pronta',
            'slug' => 'articolo-bozza-pronta-lista',
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $category->slug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.categories'));

        $response->assertOk();
        $response->assertSee('Pronta');
    }

    public function test_an_already_public_category_shows_no_checklist_in_the_list(): void
    {
        Category::create([
            'name' => 'Già Pubblica Lista',
            'slug' => 'gia-pubblica-lista',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.categories'));

        $response->assertOk();
        $response->assertDontSee('da verificare');
        $response->assertDontSee('Pronta');
    }
}
