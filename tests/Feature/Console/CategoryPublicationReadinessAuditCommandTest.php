<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Cantiere 14 (programma 100-cantieri Kairus, dipende dal Cantiere 13):
 * copertura dedicata del comando Artisan `category:publication-readiness`,
 * separata da `tests/Feature/CategoryPublicationReadinessTest.php` (che
 * copre soprattutto il servizio `CategoryPublicationReadiness` e solo
 * superficialmente il comando) — stessa convenzione di
 * `tests/Feature/Console/*CommandTest.php` già in uso per gli altri
 * comandi di audit di sola lettura (es. EditorialCalendarAuditCommandTest).
 *
 * Gap coperto: la struttura precisa dell'output --json non era mai stata
 * verificata campo per campo, il caso di una categoria PROGRAMMATA ma
 * disattivata non aveva un test dedicato, e il messaggio di stato vuoto
 * non era mai stato asserito.
 */
class CategoryPublicationReadinessAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_non_public_categories_shows_the_empty_state_message(): void
    {
        Category::create([
            'name' => 'Pubblica',
            'slug' => 'pubblica',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ]);

        $this->artisan('category:publication-readiness')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessuna categoria non pubblica al momento.');
    }

    public function test_json_output_reports_the_exact_fields_for_a_draft_category(): void
    {
        $category = Category::create([
            'name' => 'Bozza JSON',
            'slug' => 'bozza-json',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);

        Artisan::call('category:publication-readiness --json');
        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertSame([
            'category_id' => $category->id,
            'name' => 'Bozza JSON',
            'slug' => 'bozza-json',
            'status' => Category::STATUS_DRAFT,
            'is_active' => true,
            'visibility_label' => 'Bozza',
            'scheduled_at' => null,
            'findings' => [
                'MISSING_DESCRIPTION',
                'MISSING_IMAGE',
                'MISSING_COLOR',
                'NO_SCHEDULED_OR_PUBLISHED_ARTICLES',
                'NO_RELATED_PERCORSO',
            ],
        ], $decoded[0]);
    }

    public function test_a_disabled_scheduled_category_is_reported_as_disabled_not_scheduled(): void
    {
        Category::create([
            'name' => 'Programmata Spenta',
            'slug' => 'programmata-spenta',
            'is_active' => false,
            'status' => Category::STATUS_SCHEDULED,
            'published_at' => now()->addWeek(),
        ]);

        Artisan::call('category:publication-readiness --json');
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame('Disattivata', $decoded[0]['visibility_label']);
        $this->assertFalse($decoded[0]['is_active']);

        $this->artisan('category:publication-readiness')
            ->assertExitCode(0)
            ->expectsOutputToContain('Stato: Disattivata')
            ->doesntExpectOutputToContain('Programmata per:');
    }

    public function test_categories_with_the_same_sort_order_fall_back_to_name(): void
    {
        Category::create([
            'name' => 'Zeta Bozza',
            'slug' => 'zeta-bozza',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
            'sort_order' => 0,
        ]);
        Category::create([
            'name' => 'Alfa Bozza',
            'slug' => 'alfa-bozza',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
            'sort_order' => 0,
        ]);

        Artisan::call('category:publication-readiness --json');
        $decoded = json_decode(Artisan::output(), true);

        // Category::scopeOrdered(): orderBy(sort_order) poi orderBy(name) —
        // a parità di sort_order, "Alfa Bozza" precede "Zeta Bozza".
        $this->assertSame(['Alfa Bozza', 'Zeta Bozza'], array_column($decoded, 'name'));
    }

    /**
     * Finding Codex (P2, PR #561): il test sopra usa lo stesso sort_order
     * per entrambe le fixture, quindi verifica solo lo spareggio per nome —
     * se il comando regredisse a ordinare per solo nome, ignorando
     * sort_order, questo test resterebbe comunque verde. Qui sort_order e
     * nome sono in conflitto deliberato: "Zeta Bozza" ha sort_order più
     * basso di "Alfa Bozza", quindi deve comparire prima nonostante l'ordine
     * alfabetico inverso.
     */
    public function test_sort_order_takes_precedence_over_name(): void
    {
        Category::create([
            'name' => 'Zeta Bozza Priorita',
            'slug' => 'zeta-bozza-priorita',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
            'sort_order' => 0,
        ]);
        Category::create([
            'name' => 'Alfa Bozza Priorita',
            'slug' => 'alfa-bozza-priorita',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        Artisan::call('category:publication-readiness --json');
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(['Zeta Bozza Priorita', 'Alfa Bozza Priorita'], array_column($decoded, 'name'));
    }

    public function test_the_summary_counts_only_categories_with_findings(): void
    {
        Category::create([
            'name' => 'Bozza Incompleta Riepilogo',
            'slug' => 'bozza-incompleta-riepilogo',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);

        $readyCategory = Category::create([
            'name' => 'Bozza Completa Riepilogo',
            'slug' => 'bozza-completa-riepilogo',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
            'description' => 'Descrizione.',
            'image' => 'placeholder.jpg',
            'color' => '#0d9488',
        ]);

        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo riepilogo comando',
            'slug' => 'articolo-riepilogo-comando',
            'excerpt' => 'Sommario.',
            'body' => '<p>Corpo.</p>',
            'category' => $readyCategory->slug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach($article->id, ['position' => 10]);

        $this->artisan('category:publication-readiness')
            ->assertExitCode(0)
            ->expectsOutputToContain('Categorie non pubbliche: 2')
            ->expectsOutputToContain('Con almeno una criticità: 1');
    }
}
