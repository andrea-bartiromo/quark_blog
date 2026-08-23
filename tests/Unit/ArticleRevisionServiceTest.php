<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Services\ArticleRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDITORIAL SAFETY — copertura della policy di snapshot (pre-change,
 * solo su cambiamento reale) e della semantica di ripristino (stato
 * attuale sempre salvato prima, transazione atomica, audit trail).
 */
class ArticleRevisionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $overrides = []): Article
    {
        $author = User::factory()->create(['role' => 'editor']);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Titolo originale',
            'slug' => 'titolo-originale-'.uniqid(),
            'excerpt' => 'Sommario originale',
            'body' => '<p>Corpo originale.</p>',
            'category' => 'energia',
            'status' => 'draft',
        ], $overrides));
    }

    public function test_recording_a_changed_field_creates_a_revision_with_the_pre_change_state(): void
    {
        $article = $this->article();
        $actor = User::factory()->create(['role' => 'editor']);

        app(ArticleRevisionService::class)->recordIfChanged($article, [
            'title' => 'Titolo modificato',
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
        ], $actor);

        $this->assertDatabaseCount('article_revisions', 1);

        $revision = ArticleRevision::first();
        $this->assertSame($article->id, $revision->article_id);
        $this->assertSame($actor->id, $revision->user_id);
        // Il valore salvato è quello PRIMA della modifica (pre-change),
        // non "Titolo modificato" — la revisione rappresenta lo stato che
        // sta per essere sovrascritto.
        $this->assertSame('Titolo originale', $revision->title);
    }

    public function test_a_no_op_save_does_not_create_a_revision(): void
    {
        $article = $this->article();

        app(ArticleRevisionService::class)->recordIfChanged($article, [
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
        ], null);

        $this->assertDatabaseCount('article_revisions', 0);
    }

    public function test_a_change_to_a_field_outside_the_snapshot_scope_does_not_create_a_revision(): void
    {
        $article = $this->article();

        // 'featured' non è tra i campi coperti dallo snapshot (vedi
        // ArticleRevisionService::SNAPSHOT_FIELDS) — solo i campi
        // editoriali principali sono tracciati in questa v1.
        app(ArticleRevisionService::class)->recordIfChanged($article, [
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
            'featured' => true,
        ], null);

        $this->assertDatabaseCount('article_revisions', 0);
    }

    public function test_restore_applies_the_revisions_fields_to_the_article(): void
    {
        $article = $this->article(['title' => 'Titolo corrente', 'body' => '<p>Corpo corrente.</p>']);
        $revision = ArticleRevision::create([
            'article_id' => $article->id,
            'user_id' => null,
            'title' => 'Titolo storico',
            'excerpt' => 'Sommario storico',
            'body' => '<p>Corpo storico.</p>',
            'category' => 'energia',
            'status' => 'draft',
            'created_at' => now()->subDay(),
        ]);

        app(ArticleRevisionService::class)->restore($article, $revision, null);

        $article->refresh();
        $this->assertSame('Titolo storico', $article->title);
        $this->assertSame('<p>Corpo storico.</p>', $article->body);
    }

    public function test_restore_snapshots_the_current_state_before_overwriting_it_so_nothing_is_lost(): void
    {
        $article = $this->article(['title' => 'Titolo corrente prima del ripristino']);
        $revision = ArticleRevision::create([
            'article_id' => $article->id,
            'user_id' => null,
            'title' => 'Titolo storico',
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
            'created_at' => now()->subDay(),
        ]);

        app(ArticleRevisionService::class)->restore($article, $revision, null);

        // Due revisioni ora esistono: quella storica originale (invariata)
        // e una nuova che cattura "Titolo corrente prima del ripristino" —
        // il ripristino non ha distrutto quello stato, resta recuperabile.
        $this->assertDatabaseCount('article_revisions', 2);
        $this->assertDatabaseHas('article_revisions', [
            'article_id' => $article->id,
            'title' => 'Titolo corrente prima del ripristino',
        ]);
    }

    public function test_restore_is_reversible_by_restoring_the_newly_created_pre_restore_revision(): void
    {
        $article = $this->article(['title' => 'A']);
        $revisionB = ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => null,
            'title' => 'B', 'excerpt' => $article->excerpt, 'body' => $article->body,
            'category' => $article->category, 'status' => $article->status, 'created_at' => now(),
        ]);

        $service = app(ArticleRevisionService::class);
        $service->restore($article, $revisionB, null);
        $this->assertSame('B', $article->refresh()->title);

        // La revisione appena creata dal primo ripristino cattura "A" —
        // ripristinarla di nuovo riporta l'articolo al punto di partenza.
        $preRestoreRevision = ArticleRevision::where('title', 'A')->latest('id')->first();
        $service->restore($article, $preRestoreRevision, null);

        $this->assertSame('A', $article->refresh()->title);
    }

    public function test_restore_logs_to_the_activity_log_for_audit_trail(): void
    {
        $article = $this->article();
        $revision = ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => null,
            'title' => 'Titolo storico', 'excerpt' => $article->excerpt, 'body' => $article->body,
            'category' => $article->category, 'status' => $article->status, 'created_at' => now(),
        ]);

        app(ArticleRevisionService::class)->restore($article, $revision, null);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'article',
            'subject_id' => $article->id,
            'action' => 'Versione articolo ripristinata',
        ]);
    }

    public function test_restored_values_never_bypass_the_articles_own_model_validation_invariants(): void
    {
        // Article::booted() forza published_at a null per status draft/
        // review indipendentemente da come arriva il dato (vedi
        // app/Models/Article.php) — un ripristino non deve poter aggirare
        // questa invariante restituendo un published_at incoerente.
        $article = $this->article(['status' => 'published', 'published_at' => now()]);
        $revision = ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => null,
            'title' => $article->title, 'excerpt' => $article->excerpt, 'body' => $article->body,
            'category' => $article->category, 'status' => 'draft', 'created_at' => now(),
        ]);

        app(ArticleRevisionService::class)->restore($article, $revision, null);

        $article->refresh();
        $this->assertSame('draft', $article->status);
        $this->assertNull($article->published_at);
    }

    public function test_deleting_an_article_cascades_to_its_revisions(): void
    {
        $article = $this->article();
        ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => null,
            'title' => 'X', 'excerpt' => 'X', 'body' => 'X',
            'category' => $article->category, 'status' => $article->status, 'created_at' => now(),
        ]);

        $article->delete();

        $this->assertDatabaseCount('article_revisions', 0);
    }
}
