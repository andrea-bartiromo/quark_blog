<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDITORIAL RESILIENCE — autosave/recovery locale del form articolo Admin.
 *
 * Il meccanismo di recovery vero e proprio (lettura/scrittura di
 * localStorage, confronto bozza/form, banner di ripristino) è JavaScript
 * puro e non testabile via un test PHP — coperto invece da
 * tests/browser/editor-autosave.spec.js (non eseguibile in questo sandbox,
 * vedi report finale). Questo file verifica ciò che PUÒ essere verificato
 * lato server con certezza: che il form esponga esattamente l'identità e
 * gli hook di cui lo script ha bisogno, che il flash post-salvataggio
 * porti l'informazione necessaria alla pulizia della bozza, e che nessun
 * dato sensibile (file upload, CSRF) sia esposto ai campi osservati dallo
 * script.
 */
class ArticleAutosaveWiringTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function publishedArticle(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo esistente',
            'slug' => 'articolo-esistente-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    public function test_create_form_exposes_a_new_article_draft_identity(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.articles.create'));

        $response->assertOk();
        $response->assertSee('data-editor-autosave-form', false);
        $response->assertSee('data-editor-surface="admin"', false);
        $response->assertSee('data-editor-context="new"', false);
        $response->assertSee('meta name="kairus-user-id" content="'.$editor->id.'"', false);
    }

    public function test_edit_form_exposes_the_article_id_and_server_updated_at_snapshot(): void
    {
        $editor = $this->editor();
        $article = $this->publishedArticle($editor);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('data-editor-context="'.$article->id.'"', false);
        $response->assertSee(
            'data-editor-server-updated-at="'.$article->fresh()->updated_at->timestamp.'"',
            false
        );
    }

    public function test_recovery_banner_and_status_indicator_markup_are_present(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.articles.create'));

        $response->assertOk();
        $response->assertSee('data-editor-autosave-banner', false);
        $response->assertSee('data-editor-autosave-restore', false);
        $response->assertSee('data-editor-autosave-ignore', false);
        $response->assertSee('data-editor-autosave-discard', false);
        $response->assertSee('data-editor-autosave-status', false);
    }

    public function test_a_successful_update_flashes_the_article_id_for_client_side_draft_cleanup(): void
    {
        $editor = $this->editor();
        $article = $this->publishedArticle($editor);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => 'Titolo aggiornato',
            'excerpt' => 'Sommario aggiornato',
            'body' => '<p>Corpo aggiornato.</p>',
            'category' => 'energia',
            'status' => 'published',
        ]);

        $response->assertRedirect(route('admin.articles'));
        $response->assertSessionHas('kairus_draft_cleanup_context', (string) $article->id);

        // Il flash atterra sulla pagina di destinazione del redirect (la
        // lista, mai di nuovo sul form): e' li', non sul form, che deve
        // vivere lo script di pulizia della bozza locale (FASE 8) — vedi
        // partials/kairus-draft-cleanup-script.blade.php. Il marcatore di
        // pulizia e' un elemento DEDICATO, separato dal flash 'success'
        // generico (FASE 1 audit: un'azione admin non correlata non deve
        // mai svuotare la bozza locale di un articolo).
        $followUp = $this->get(route('admin.articles'));
        $followUp->assertSee('id="kairus-flash-success"', false);
        $followUp->assertSee('id="kairus-draft-cleanup-marker"', false);
        $followUp->assertSee('data-editor-surface="admin"', false);
        $followUp->assertSee('data-draft-cleanup-context="'.$article->id.'"', false);
        // Contenuto distintivo di partials/kairus-draft-cleanup-script.blade.php
        // (il nome del partial non compare letteralmente nell'HTML renderizzato,
        // solo il suo output — @include lo inlinea).
        $followUp->assertSee("getElementById('kairus-draft-cleanup-marker')", false);
    }

    public function test_an_unrelated_successful_admin_action_never_triggers_the_draft_cleanup_script(): void
    {
        // FASE 1 audit finding: il marcatore di pulizia della bozza deve
        // dipendere SOLO da kairus_draft_cleanup_context, mai dalla sola
        // presenza di un flash 'success' generico — altrimenti un'azione
        // admin qualunque (qui simulata direttamente in sessione, senza
        // passare da ArticleController) svuoterebbe la bozza "nuovo
        // articolo" di un editor con un'altra bozza ancora in corso.
        $editor = $this->editor();

        $response = $this->actingAs($editor)
            ->withSession(['success' => 'Categoria aggiornata.'])
            ->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('id="kairus-flash-success"', false);
        $response->assertDontSee('id="kairus-draft-cleanup-marker"', false);
        $response->assertDontSee("getElementById('kairus-draft-cleanup-marker')", false);
    }

    public function test_the_file_upload_field_is_never_among_the_fields_the_autosave_script_serializes(): void
    {
        // Verifica statica sul contenuto dello script condiviso: il file
        // input non deve MAI comparire nell'elenco di campi osservati
        // (FASE 5/10 — mai serializzare upload binari in localStorage).
        $script = file_get_contents(resource_path('views/partials/article-autosave-script.blade.php'));

        $this->assertStringNotContainsString("'cover_image_upload'", $script);
        $this->assertStringNotContainsString("'_token'", $script);
        $this->assertStringNotContainsString("'_method'", $script);
    }
}
