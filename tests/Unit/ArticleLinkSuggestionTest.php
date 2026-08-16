<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArticleLinkSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    // 1. Un suggerimento appartiene ai due articoli coinvolti
    public function test_a_suggestion_belongs_to_its_source_and_target_article(): void
    {
        $source = $this->article(['title' => 'Sorgente']);
        $target = $this->article(['title' => 'Destinazione']);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari',
            'reason' => 'Categoria condivisa: Energia & Clima',
            'confidence_score' => 70,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
        ]);

        $this->assertSame('Sorgente', $suggestion->sourceArticle->title);
        $this->assertSame('Destinazione', $suggestion->targetArticle->title);
        $this->assertTrue($suggestion->isActionable());
    }

    // 2. Vincolo unique (source, target): un solo suggerimento per coppia
    public function test_unique_constraint_prevents_duplicate_suggestions_for_the_same_pair(): void
    {
        $source = $this->article();
        $target = $this->article();

        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'termine',
            'reason' => 'motivo',
            'confidence_score' => 50,
        ]);

        $this->expectException(QueryException::class);
        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'altro termine',
            'reason' => 'altro motivo',
            'confidence_score' => 60,
        ]);
    }

    // 3. Cancellare l'articolo sorgente cancella il suggerimento collegato
    // (il body dell'articolo sorgente scompare con esso — nulla da ripulire).
    public function test_deleting_the_source_article_cascades_to_the_suggestion(): void
    {
        $source = $this->article();
        $target = $this->article();
        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'termine',
            'reason' => 'motivo',
            'confidence_score' => 50,
        ]);

        $source->delete();

        $this->assertSame(0, ArticleLinkSuggestion::count());
    }

    // 3b. Codex (PR #165, round 12): cancellare l'articolo TARGET non cancella più
    // il suggerimento (era cascadeOnDelete()) — la riga sopravvive con
    // target_article_id azzerato (nullOnDelete()), cosi
    // ArticleLinkSuggestionService::markAccepted() può ancora ripulire dal body
    // della sorgente un link già fisicamente inserito prima della cancellazione,
    // usando lo snapshot target_slug al posto della relazione ormai assente.
    public function test_deleting_the_target_article_nulls_the_reference_instead_of_deleting_the_suggestion(): void
    {
        $source = $this->article();
        $target = $this->article();
        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => $target->slug,
            'anchor_text' => 'termine',
            'reason' => 'motivo',
            'confidence_score' => 50,
        ]);

        $target->delete();

        $this->assertSame(1, ArticleLinkSuggestion::count());
        $this->assertNull($suggestion->fresh()->target_article_id);
        $this->assertNull($suggestion->fresh()->targetArticle);
        $this->assertSame($target->slug, $suggestion->fresh()->target_slug);
    }

    // 3c. Codex (PR #165, round 14): su un'installazione già in produzione, le righe
    // esistenti al momento del deploy di questa migrazione non passano mai da
    // analyzeForSource()/analyzeForNewTarget() (l'unico punto che valorizza
    // target_slug) finché non tornano 'proposed' — la migrazione stessa deve quindi
    // effettuare il backfill da articles.slug, altrimenti quelle righe restano con
    // target_slug NULL per sempre e un target eliminato in seguito non è più
    // ripulibile dal body (né target_article_id né target_slug disponibili).
    public function test_migration_backfills_target_slug_for_rows_that_predate_the_column(): void
    {
        $source = $this->article();
        $target = $this->article();
        $targetMigration = '2026_08_11_165128_alter_target_article_id_on_article_link_suggestions_to_null_on_delete';
        $targetMigrationId = DB::table('migrations')->where('migration', $targetMigration)->value('id');

        $this->assertNotNull($targetMigrationId);

        // Roll back this migration and every migration that was added after it.
        // Using the actual migration position instead of --step=1 keeps this
        // regression test valid when unrelated newer migrations are introduced.
        $rollbackSteps = DB::table('migrations')->where('id', '>=', $targetMigrationId)->count();
        Artisan::call('migrate:rollback', ['--step' => $rollbackSteps]);
        $this->assertFalse(Schema::hasColumn('article_link_suggestions', 'target_slug'));

        $suggestionId = DB::table('article_link_suggestions')->insertGetId([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'termine',
            'reason' => 'motivo',
            'confidence_score' => 50,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Il deploy vero e proprio: la migrazione (con il backfill) viene applicata
        // a dati "preesistenti" già in tabella, non solo a righe create dopo.
        Artisan::call('migrate');

        $this->assertSame($target->slug, DB::table('article_link_suggestions')->where('id', $suggestionId)->value('target_slug'));
    }

    // 4. Scope "proposed" isola solo i suggerimenti ancora da rivedere
    public function test_proposed_scope_excludes_reviewed_suggestions(): void
    {
        $source = $this->article();
        $accepted = $this->article();
        $ignored = $this->article();
        $pending = $this->article();

        ArticleLinkSuggestion::create(['source_article_id' => $source->id, 'target_article_id' => $accepted->id, 'anchor_text' => 'a', 'reason' => 'r', 'confidence_score' => 50, 'status' => ArticleLinkSuggestion::STATUS_ACCEPTED]);
        ArticleLinkSuggestion::create(['source_article_id' => $source->id, 'target_article_id' => $ignored->id, 'anchor_text' => 'b', 'reason' => 'r', 'confidence_score' => 50, 'status' => ArticleLinkSuggestion::STATUS_IGNORED]);
        ArticleLinkSuggestion::create(['source_article_id' => $source->id, 'target_article_id' => $pending->id, 'anchor_text' => 'c', 'reason' => 'r', 'confidence_score' => 50, 'status' => ArticleLinkSuggestion::STATUS_PROPOSED]);

        $proposed = ArticleLinkSuggestion::proposed()->forSource($source->id)->get();

        $this->assertCount(1, $proposed);
        $this->assertSame($pending->id, $proposed->first()->target_article_id);
    }

    // 5. Il numero massimo di suggerimenti restituiti alla redazione è
    // rispettato — "pochi ma pertinenti" invece di una lista lunga di
    // proposte mediocri (Audit qualità suggerimenti, Ago 2026).
    public function test_proposed_link_suggestions_are_capped_at_the_maximum(): void
    {
        $source = $this->article();

        for ($i = 0; $i < 6; $i++) {
            ArticleLinkSuggestion::create([
                'source_article_id' => $source->id,
                'target_article_id' => $this->article()->id,
                'anchor_text' => 'termine '.$i,
                'reason' => 'motivo',
                'confidence_score' => 50 + $i,
                'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
            ]);
        }

        $this->assertSame(6, ArticleLinkSuggestion::proposed()->forSource($source->id)->count());
        $this->assertCount(ArticleLinkSuggestion::MAX_PROPOSED_RESULTS, $source->proposedLinkSuggestions());
        $this->assertSame(55, $source->proposedLinkSuggestions()->first()->confidence_score);
    }
}
