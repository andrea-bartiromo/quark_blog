<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 46 (programma "100 cantieri Kairus", dipende dal Cantiere 45).
 *
 * `content-clusters:provision-non-public-package` prepara il contenitore
 * di un tema editoriale ("Mente e comportamento", Cantiere 46; "Scienza e
 * metodo", Cantiere 51) prima che esista un solo articolo reale — mai
 * contenuto editoriale generato qui, solo nome/slug passati esplicitamente
 * e `is_active=false`. Questi test verificano in modo end-to-end che il
 * pacchetto resti davvero escluso da ogni superficie pubblica appena
 * creato, non solo che la riga esista nel database.
 */
class ProvisionNonPublicContentClusterPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_makes_no_database_changes(): void
    {
        $this->artisan('content-clusters:provision-non-public-package mente-e-comportamento "Mente e comportamento"')
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('CREATE NON-PUBLIC PACKAGE mente-e-comportamento')
            ->assertSuccessful();

        $this->assertDatabaseMissing('content_clusters', ['slug' => 'mente-e-comportamento']);
    }

    public function test_apply_creates_an_inactive_package_with_no_articles(): void
    {
        $this->artisan('content-clusters:provision-non-public-package mente-e-comportamento "Mente e comportamento" --apply')
            ->expectsOutputToContain('APPLY mode')
            ->assertSuccessful();

        $cluster = ContentCluster::where('slug', 'mente-e-comportamento')->firstOrFail();

        $this->assertSame('Mente e comportamento', $cluster->name);
        $this->assertFalse($cluster->is_active);
        $this->assertNull($cluster->pillar_article_id);
        $this->assertSame(0, $cluster->articles()->count());
        $this->assertFalse($cluster->isPubliclyVisible());
    }

    public function test_running_it_twice_never_modifies_the_existing_package(): void
    {
        $this->artisan('content-clusters:provision-non-public-package mente-e-comportamento "Mente e comportamento" --apply')->assertSuccessful();
        $original = ContentCluster::where('slug', 'mente-e-comportamento')->firstOrFail();

        $this->artisan('content-clusters:provision-non-public-package mente-e-comportamento "Nome diverso" --apply')
            ->expectsOutputToContain('SKIP mente-e-comportamento')
            ->assertSuccessful();

        $this->assertSame(1, ContentCluster::where('slug', 'mente-e-comportamento')->count());
        $this->assertSame($original->name, ContentCluster::where('slug', 'mente-e-comportamento')->value('name'));
    }

    /**
     * Garanzia end-to-end, non solo sul flag `is_active`: un pacchetto
     * appena creato deve restare davvero irraggiungibile dalle superfici
     * pubbliche reali anche quando un editore inizia ad allegargli
     * articoli reali già pubblicati — a differenza di un pacchetto
     * ancora vuoto (escluso comunque dalla sitemap per mancanza di
     * articoli), questo è l'unico modo di provare che l'esclusione
     * dipende davvero da `is_active=false` e non da un effetto
     * collaterale del pacchetto essere vuoto — stessa verifica già in
     * uso per i Percorsi ordinari (ContentClusterPublicTest).
     */
    public function test_a_freshly_provisioned_package_stays_excluded_from_the_sitemap_even_with_a_published_article_attached(): void
    {
        $this->artisan('content-clusters:provision-non-public-package mente-e-comportamento "Mente e comportamento" --apply')->assertSuccessful();

        $cluster = ContentCluster::where('slug', 'mente-e-comportamento')->firstOrFail();
        $editor = User::factory()->create(['role' => 'editor']);
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo pubblicato di prova',
            'slug' => 'articolo-pubblicato-di-prova',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/percorsi/mente-e-comportamento</loc>', false);
    }

    public function test_the_second_package_reuses_the_same_command(): void
    {
        $this->artisan('content-clusters:provision-non-public-package scienza-e-metodo "Scienza e metodo" --apply')
            ->assertSuccessful();

        $this->assertDatabaseHas('content_clusters', [
            'slug' => 'scienza-e-metodo',
            'name' => 'Scienza e metodo',
            'is_active' => false,
        ]);
    }
}
