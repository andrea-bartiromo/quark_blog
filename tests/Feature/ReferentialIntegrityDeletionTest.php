<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleContinuationEvent;
use App\Models\ArticleLinkSuggestion;
use App\Models\ArticleSlugRedirect;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\ContentClusterSubscriber;
use App\Models\ContentClusterSuggestion;
use App\Models\Project;
use App\Models\SearchConsoleQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cantiere 92 (programma "100 cantieri Kairus", dipende dal Cantiere
 * 91): "Test integrità referenziale e cancellazione sicura". Nessuna
 * migrazione nuova — un audit dedicato ha confermato che ogni foreign
 * key coinvolta usa già `cascade` o `nullOnDelete` deliberatamente (i
 * due soli casi "senza FK" — search_opportunity_decisions.article_id,
 * project_tasks.article_id — sono documentati come intenzionali nella
 * loro migrazione). Questo file chiude le lacune di copertura reali
 * trovate dall'audit: relazioni già sicure a livello DB ma mai
 * esercitate da un test di regressione, più l'unico guard applicativo
 * esistente (`Admin\CategoryController::destroy()`), anch'esso mai
 * testato prima d'ora.
 */
class ReferentialIntegrityDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function category(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Categoria Cantiere 92',
            'slug' => 'categoria-cantiere-92-'.uniqid(),
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ], $overrides));
    }

    private function article(string $categorySlug, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $categorySlug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ], $overrides));
    }

    // ── Category::featured_article_id (Cantiere 50) ─────────────

    public function test_deleting_the_featured_article_nulls_the_category_featured_article_id(): void
    {
        $category = $this->category();
        $article = $this->article($category->slug);
        $category->update(['featured_article_id' => $article->id]);

        $article->delete();

        $this->assertNull($category->fresh()->featured_article_id);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    // ── Admin\CategoryController::destroy() guard ───────────────

    public function test_a_category_with_primary_articles_cannot_be_deleted_via_admin(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $category = $this->category();
        $this->article($category->slug);

        $response = $this->actingAs($editor)->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_a_category_without_primary_articles_can_be_deleted_via_admin(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $category = $this->category();

        $response = $this->actingAs($editor)->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /**
     * Comportamento oggi accettato (confermato dall'audit di scoping):
     * il guard di CategoryController::destroy() controlla solo
     * articles() (associazione primaria via slug), mai
     * secondaryArticles() (pivot article_category). Una categoria senza
     * articoli primari ma con associazioni secondarie può quindi essere
     * eliminata dall'admin, e la cascata sul pivot rimuove silenziosamente
     * quel tag da articoli pubblicati e ancora vivi. Questo test
     * documenta il comportamento reale, non ne presume la correttezza:
     * se in futuro si deciderà di bloccare anche questo caso, questo
     * test andrà aggiornato di conseguenza.
     */
    public function test_deleting_a_category_with_only_secondary_articles_cascades_the_pivot_silently(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $primaryCategory = $this->category();
        $secondaryCategory = $this->category();
        $article = $this->article($primaryCategory->slug);
        $article->secondaryCategories()->attach($secondaryCategory->id);

        $response = $this->actingAs($editor)->delete(route('admin.categories.destroy', $secondaryCategory));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('categories', ['id' => $secondaryCategory->id]);
        $this->assertDatabaseMissing('article_category', [
            'category_id' => $secondaryCategory->id,
            'article_id' => $article->id,
        ]);
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
    }

    // ── content_cluster_suggestions / content_cluster_subscribers ─

    public function test_deleting_a_content_cluster_cascades_suggestions_and_subscribers(): void
    {
        $category = $this->category();
        $article = $this->article($category->slug);
        $cluster = ContentCluster::factory()->create();
        $suggestion = ContentClusterSuggestion::create([
            'article_id' => $article->id,
            'content_cluster_id' => $cluster->id,
            'status' => ContentClusterSuggestion::STATUS_PENDING,
            'confidence' => 80,
            'reasons' => [],
            'evidence_hash' => hash('sha256', uniqid()),
        ]);
        $subscriber = ContentClusterSubscriber::factory()->create(['content_cluster_id' => $cluster->id]);

        $cluster->delete();

        $this->assertDatabaseMissing('content_cluster_suggestions', ['id' => $suggestion->id]);
        $this->assertDatabaseMissing('content_cluster_subscribers', ['id' => $subscriber->id]);
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
    }

    public function test_deleting_an_article_cascades_its_content_cluster_suggestions(): void
    {
        $category = $this->category();
        $article = $this->article($category->slug);
        $cluster = ContentCluster::factory()->create();
        $suggestion = ContentClusterSuggestion::create([
            'article_id' => $article->id,
            'content_cluster_id' => $cluster->id,
            'status' => ContentClusterSuggestion::STATUS_PENDING,
            'confidence' => 80,
            'reasons' => [],
            'evidence_hash' => hash('sha256', uniqid()),
        ]);

        $article->delete();

        $this->assertDatabaseMissing('content_cluster_suggestions', ['id' => $suggestion->id]);
        $this->assertDatabaseHas('content_clusters', ['id' => $cluster->id]);
    }

    // ── article_link_suggestions ─────────────────────────────────

    public function test_deleting_the_source_article_cascades_its_link_suggestions(): void
    {
        $category = $this->category();
        $source = $this->article($category->slug);
        $target = $this->article($category->slug);
        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'testo ancora',
            'reason' => 'motivo di prova',
            'confidence_score' => 60,
        ]);

        $source->delete();

        $this->assertDatabaseMissing('article_link_suggestions', ['id' => $suggestion->id]);
        $this->assertDatabaseHas('articles', ['id' => $target->id]);
    }

    public function test_deleting_the_target_article_nulls_the_link_suggestion_target(): void
    {
        $category = $this->category();
        $source = $this->article($category->slug);
        $target = $this->article($category->slug);
        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'testo ancora',
            'reason' => 'motivo di prova',
            'confidence_score' => 60,
        ]);

        $target->delete();

        $this->assertNull($suggestion->fresh()->target_article_id);
        $this->assertDatabaseHas('article_link_suggestions', ['id' => $suggestion->id]);
    }

    // ── article_continuation_events ──────────────────────────────

    public function test_deleting_an_article_cascades_continuation_events_where_it_is_source_or_target(): void
    {
        $category = $this->category();
        $source = $this->article($category->slug);
        $target = $this->article($category->slug);
        $asSource = ArticleContinuationEvent::create([
            'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION,
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
        ]);
        $asTarget = ArticleContinuationEvent::create([
            'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION,
            'source_article_id' => $target->id,
            'target_article_id' => $source->id,
        ]);

        $source->delete();

        $this->assertDatabaseMissing('article_continuation_events', ['id' => $asSource->id]);
        $this->assertDatabaseMissing('article_continuation_events', ['id' => $asTarget->id]);
        $this->assertDatabaseHas('articles', ['id' => $target->id]);
    }

    // ── article_slug_redirects ────────────────────────────────────

    public function test_deleting_an_article_cascades_its_slug_redirects(): void
    {
        $category = $this->category();
        $article = $this->article($category->slug);
        $redirect = ArticleSlugRedirect::create([
            'old_slug' => 'vecchio-slug-'.uniqid(),
            'article_id' => $article->id,
        ]);

        $article->delete();

        $this->assertDatabaseMissing('article_slug_redirects', ['id' => $redirect->id]);
    }

    // ── search_console_queries ────────────────────────────────────

    public function test_deleting_an_article_nulls_its_search_console_queries(): void
    {
        $category = $this->category();
        $article = $this->article($category->slug);
        $query = SearchConsoleQuery::create([
            'query' => 'query di prova '.$article->id,
            'page_url' => '',
            'article_id' => $article->id,
            'clicks' => 2,
            'impressions' => 50,
            'ctr' => 0.04,
            'position' => 8.0,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-07',
            'import_batch' => 'fixture',
            'imported_at' => now(),
        ]);

        $article->delete();

        $this->assertNull($query->fresh()->article_id);
        $this->assertDatabaseHas('search_console_queries', ['id' => $query->id]);
    }

    // ── project_article ────────────────────────────────────────────

    /**
     * search_opportunity_decisions.article_id e project_tasks.article_id
     * sono deliberatamente SENZA foreign key (la decisione storica non
     * deve mai sparire o bloccarsi per un articolo eliminato) — ma
     * project_article, il pivot Progetti↔Articoli, HA una vera foreign
     * key con cascadeOnDelete: un articolo eliminato deve smettere di
     * comparire tra gli articoli collegati a un progetto, senza lasciare
     * righe pivot orfane.
     */
    public function test_deleting_an_article_cascades_its_project_links(): void
    {
        $category = $this->category();
        $article = $this->article($category->slug);
        $project = Project::factory()->create();
        $project->articles()->attach($article->id);

        $article->delete();

        $this->assertDatabaseMissing('project_article', ['article_id' => $article->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    // ── article_category pivot, entrambe le direzioni ────────────

    public function test_deleting_an_article_cascades_its_secondary_category_associations(): void
    {
        $primaryCategory = $this->category();
        $secondaryCategory = $this->category();
        $article = $this->article($primaryCategory->slug);
        $article->secondaryCategories()->attach($secondaryCategory->id);

        $article->delete();

        $this->assertDatabaseMissing('article_category', ['article_id' => $article->id]);
        $this->assertDatabaseHas('categories', ['id' => $secondaryCategory->id]);
    }

    /**
     * Codex (PR #612, P2): `PRAGMA foreign_key_list` e sintassi solo
     * SQLite — su MariaDB/MySQL la stessa query fallirebbe con un
     * errore SQL, non solo un'asserzione sbagliata. Oggi il pacchetto
     * di regressione MariaDB della CI esegue un elenco selettivo di
     * file che non include ancora questo, ma un'esecuzione futura
     * dell'intera suite contro MariaDB (locale o CI) romperebbe questo
     * test alla prima query. Portabile su entrambi i driver via
     * information_schema per MariaDB/MySQL.
     */
    public function test_the_article_category_pivot_table_still_cascades_on_both_sides(): void
    {
        $foreignKeys = $this->foreignKeysOf('article_category');
        $this->assertNotEmpty($foreignKeys, 'article_category dovrebbe avere foreign key reali.');

        foreach ($foreignKeys as $column => $onDelete) {
            $this->assertSame(
                'CASCADE',
                strtoupper($onDelete),
                "article_category.{$column} dovrebbe restare cascadeOnDelete."
            );
        }
    }

    /**
     * @return array<string, string> colonna => regola ON DELETE
     */
    private function foreignKeysOf(string $table): array
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA foreign_key_list('{$table}')"))
                ->mapWithKeys(fn ($fk) => [$fk->from => strtoupper($fk->on_delete)])
                ->all();
        }

        $rows = DB::select(<<<'SQL'
            select ku.column_name as `column`, rc.delete_rule as `on_delete`
            from information_schema.key_column_usage ku
            join information_schema.referential_constraints rc
                on rc.constraint_name = ku.constraint_name
                and rc.constraint_schema = ku.constraint_schema
            where ku.table_schema = database()
                and ku.table_name = ?
                and ku.referenced_table_name is not null
            SQL, [$table]);

        return collect($rows)->mapWithKeys(fn ($row) => [$row->column => strtoupper($row->on_delete)])->all();
    }
}
