<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\CategoryPublicationReadiness;
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

    /**
     * Finding Codex (P2, PR #559): la fixture originale si chiamava
     * "Bozza Pronta Lista" — assertSee('Pronta') passava anche solo
     * grazie al NOME della categoria, non al badge reale, e senza un
     * Percorso collegato la categoria non era affatto "pronta"
     * (NO_RELATED_PERCORSO). Corretto: nome senza la parola "Pronta",
     * un Percorso collegato come nel test analogo di
     * CategoryPublicationReadinessTest, e un'asserzione scoperta sul
     * markup del badge, non su una stringa generica.
     */
    public function test_a_fully_ready_draft_category_shows_ready_in_the_list(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $category = Category::create([
            'name' => 'Bozza Completa In Lista',
            'slug' => 'bozza-completa-in-lista',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
            'description' => 'Descrizione completa.',
            'image' => 'placeholder.jpg',
            'color' => '#0d9488',
        ]);

        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo della categoria completa',
            'slug' => 'articolo-categoria-completa-in-lista',
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $category->slug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach($article->id, ['position' => 10]);

        // Prova indipendente che la fixture sia davvero "pronta" prima di
        // verificare cosa mostra la vista — altrimenti un futuro cambio a
        // CategoryPublicationReadiness potrebbe far tornare 'ready' => false
        // e questo test continuerebbe comunque a cercare la stringa giusta
        // senza accorgersi che il presupposto è cambiato.
        $readiness = app(CategoryPublicationReadiness::class)->evaluate($category->fresh());
        $this->assertTrue($readiness['ready'], 'Fixture non pronta: '.implode(', ', $readiness['findings']));

        $response = $this->actingAs($this->editor())->get(route('admin.categories'));

        $response->assertOk();
        $response->assertSee('<span class="status status--published" title="Nessuna criticità rilevata.">Pronta</span>', false);
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
