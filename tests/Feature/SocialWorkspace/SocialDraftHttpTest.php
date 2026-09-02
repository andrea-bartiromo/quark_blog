<?php

namespace Tests\Feature\SocialWorkspace;

use App\Models\Article;
use App\Models\SocialDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SocialDraftHttpTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function draft(array $overrides = []): SocialDraft
    {
        return SocialDraft::create(array_merge([
            'article_id' => $this->article()->id,
            'channel' => SocialDraft::CHANNEL_FACEBOOK,
            'status' => SocialDraft::STATUS_DRAFT,
            'copy' => 'Testo di prova.',
        ], $overrides));
    }

    // ---- autorizzazione ----

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.social-drafts.index'))->assertRedirect(route('login'));
    }

    public function test_non_editor_role_cannot_access_the_workspace(): void
    {
        $author = $this->author();

        $this->actingAs($author)
            ->get(route('admin.social-drafts.index'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_can_access_the_index(): void
    {
        $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.index'))
            ->assertOk();
    }

    /**
     * "to" è una data di calendario Europe/Rome (colonna "Programmato
     * (Europe/Rome)" in tabella), mentre scheduled_at è salvato in UTC: un
     * confronto ingenuo tratterebbe "to" come mezzanotte UTC ed escluderebbe
     * una bozza programmata più tardi lo stesso giorno editoriale.
     */
    public function test_to_filter_includes_a_draft_scheduled_later_the_same_editorial_day(): void
    {
        // 22:00 Europe/Rome del 2 settembre 2026 (CEST, UTC+2) = 20:00 UTC —
        // dopo la mezzanotte UTC dello stesso giorno di calendario, il caso
        // che un confronto ingenuo su stringa escluderebbe.
        $lateInTheDay = Carbon::create(2026, 9, 2, 22, 0, 0, 'Europe/Rome');
        $draft = $this->draft([
            'status' => SocialDraft::STATUS_SCHEDULED,
            'scheduled_at' => $lateInTheDay->clone()->utc(),
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.index', ['to' => '2026-09-02']));

        $response->assertOk();
        $response->assertSee($draft->article->title);
    }

    public function test_unauthenticated_store_request_is_rejected_before_any_draft_is_created(): void
    {
        $article = $this->article();

        $this->post(route('admin.social-drafts.store'), [
            'article_id' => $article->id,
            'channel' => SocialDraft::CHANNEL_FACEBOOK,
        ]);

        $this->assertDatabaseCount('social_drafts', 0);
    }

    // ---- flusso CRUD di base ----

    public function test_create_form_loads(): void
    {
        $this->article();

        $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.create'))
            ->assertOk();
    }

    public function test_store_creates_a_draft_with_an_initial_copy_when_none_given(): void
    {
        $article = $this->article(['title' => 'Titolo unico di prova', 'excerpt' => 'Sommario di prova.']);

        $this->actingAs($this->editor())->post(route('admin.social-drafts.store'), [
            'article_id' => $article->id,
            'channel' => SocialDraft::CHANNEL_LINKEDIN,
        ])->assertRedirect();

        $draft = SocialDraft::first();
        $this->assertNotNull($draft);
        $this->assertStringContainsString('Titolo unico di prova', $draft->copy);
        $this->assertSame(SocialDraft::STATUS_DRAFT, $draft->status);
    }

    public function test_store_rejects_an_unsupported_channel(): void
    {
        $article = $this->article();

        $this->actingAs($this->editor())->post(route('admin.social-drafts.store'), [
            'article_id' => $article->id,
            'channel' => 'instagram',
        ])->assertSessionHasErrors('channel');

        $this->assertDatabaseCount('social_drafts', 0);
    }

    public function test_show_page_renders_for_an_editor(): void
    {
        $draft = $this->draft();

        $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.show', $draft))
            ->assertOk()
            ->assertSee('Testo di prova.');
    }

    public function test_update_saves_copy_when_editable(): void
    {
        $draft = $this->draft();

        $this->actingAs($this->editor())->put(route('admin.social-drafts.update', $draft), [
            'copy' => 'Copy aggiornato.',
        ])->assertRedirect();

        $this->assertSame('Copy aggiornato.', $draft->fresh()->copy);
    }

    /**
     * Un checkbox HTML deselezionato non viene inviato dal browser: la vista
     * include un hidden use_utm=0 prima del checkbox proprio per questo, in
     * modo che disattivare gli UTM sia effettivamente rappresentabile nel
     * payload (il checkbox, se spuntato, sovrascrive il valore hidden).
     */
    public function test_show_page_includes_a_hidden_fallback_so_unchecking_utm_is_representable(): void
    {
        $draft = $this->draft(['use_utm' => true]);

        $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.show', $draft))
            ->assertOk()
            ->assertSee('<input type="hidden" name="use_utm" value="0">', false);
    }

    public function test_update_turns_off_utm_when_the_checkbox_is_unchecked(): void
    {
        $draft = $this->draft(['use_utm' => true]);

        // Simula esattamente cosa invia il browser quando il checkbox è
        // deselezionato: solo l'hidden use_utm=0, mai la chiave del
        // checkbox stesso.
        $this->actingAs($this->editor())->put(route('admin.social-drafts.update', $draft), [
            'use_utm' => '0',
        ])->assertRedirect();

        $this->assertFalse($draft->fresh()->use_utm);
    }

    public function test_transition_endpoint_moves_status_forward(): void
    {
        $draft = $this->draft();

        $this->actingAs($this->editor())->post(route('admin.social-drafts.transition', $draft), [
            'to' => SocialDraft::STATUS_REVIEWED,
        ])->assertRedirect();

        $this->assertSame(SocialDraft::STATUS_REVIEWED, $draft->fresh()->status);
    }

    public function test_transition_endpoint_rejects_a_forbidden_target_with_a_friendly_message(): void
    {
        $draft = $this->draft();

        $response = $this->actingAs($this->editor())->post(route('admin.social-drafts.transition', $draft), [
            'to' => SocialDraft::STATUS_SCHEDULED,
        ]);

        $response->assertRedirect();
        $this->assertSame(SocialDraft::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertNotNull(session('error'));
        $this->assertStringNotContainsString('SQLSTATE', (string) session('error'));
        $this->assertStringNotContainsString('Exception', (string) session('error'));
    }

    // ---- stati protetti: mai raggiungibili dalla UI ----

    public function test_transition_endpoint_cannot_set_published(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_APPROVED]);

        $this->actingAs($this->editor())->post(route('admin.social-drafts.transition', $draft), [
            'to' => SocialDraft::STATUS_PUBLISHED,
        ]);

        $this->assertSame(SocialDraft::STATUS_APPROVED, $draft->fresh()->status);
    }

    public function test_transition_endpoint_cannot_set_failed(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_APPROVED]);

        $this->actingAs($this->editor())->post(route('admin.social-drafts.transition', $draft), [
            'to' => SocialDraft::STATUS_FAILED,
        ]);

        $this->assertSame(SocialDraft::STATUS_APPROVED, $draft->fresh()->status);
    }

    public function test_update_endpoint_ignores_a_status_field_in_the_payload(): void
    {
        $draft = $this->draft();

        $this->actingAs($this->editor())->put(route('admin.social-drafts.update', $draft), [
            'copy' => 'Testo.',
            'status' => SocialDraft::STATUS_PUBLISHED,
        ]);

        $this->assertSame(SocialDraft::STATUS_DRAFT, $draft->fresh()->status);
    }

    public function test_store_endpoint_ignores_a_status_field_in_the_payload(): void
    {
        $article = $this->article();

        $this->actingAs($this->editor())->post(route('admin.social-drafts.store'), [
            'article_id' => $article->id,
            'channel' => SocialDraft::CHANNEL_FACEBOOK,
            'status' => SocialDraft::STATUS_SCHEDULED,
        ]);

        $this->assertSame(SocialDraft::STATUS_DRAFT, SocialDraft::first()->status);
    }

    // ---- immutabilità dell'articolo sorgente ----

    public function test_creating_and_editing_a_draft_never_changes_the_source_article(): void
    {
        $article = $this->article([
            'title' => 'Titolo originale',
            'cover_image' => 'copertina.jpg',
            'cover_alt' => 'Testo alternativo originale',
            'category' => 'intelligenza-artificiale',
        ]);
        $original = $article->replicate();

        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.social-drafts.store'), [
            'article_id' => $article->id,
            'channel' => SocialDraft::CHANNEL_FACEBOOK,
            'copy' => 'Copy di prova.',
        ]);

        $draft = SocialDraft::first();

        $this->actingAs($editor)->put(route('admin.social-drafts.update', $draft), [
            'copy' => 'Copy modificato.',
            'destination_url' => url('/articolo/'.$article->slug),
            'use_utm' => 1,
        ]);

        $fresh = $article->fresh();
        $this->assertSame($original->title, $fresh->title);
        $this->assertSame($original->cover_image, $fresh->cover_image);
        $this->assertSame($original->cover_alt, $fresh->cover_alt);
        $this->assertSame($original->category, $fresh->category);
        $this->assertSame($original->status, $fresh->status);
        $this->assertSame($original->published_at?->toIso8601String(), $fresh->published_at?->toIso8601String());
    }

    // ---- assenza di invio esterno ----

    public function test_no_job_is_ever_dispatched_by_workspace_actions(): void
    {
        Queue::fake();
        $editor = $this->editor();
        $article = $this->article();

        $this->actingAs($editor)->post(route('admin.social-drafts.store'), [
            'article_id' => $article->id,
            'channel' => SocialDraft::CHANNEL_FACEBOOK,
            'copy' => 'Copy.',
        ]);
        $draft = SocialDraft::first();

        $this->actingAs($editor)->put(route('admin.social-drafts.update', $draft), ['copy' => 'Copy 2.']);
        $this->actingAs($editor)->post(route('admin.social-drafts.transition', $draft), ['to' => SocialDraft::STATUS_REVIEWED]);

        Queue::assertNothingPushed();
    }

    public function test_no_outbound_http_call_is_ever_made_by_workspace_actions(): void
    {
        Http::fake();
        $editor = $this->editor();
        $article = $this->article();

        $this->actingAs($editor)->post(route('admin.social-drafts.store'), [
            'article_id' => $article->id,
            'channel' => SocialDraft::CHANNEL_LINKEDIN,
            'copy' => 'Copy.',
        ]);
        $draft = SocialDraft::first();
        $this->actingAs($editor)->post(route('admin.social-drafts.transition', $draft), ['to' => SocialDraft::STATUS_REVIEWED]);

        Http::assertNothingSent();
    }

    public function test_social_publications_table_is_never_written_by_workspace_actions(): void
    {
        $editor = $this->editor();
        $article = $this->article();

        $this->actingAs($editor)->post(route('admin.social-drafts.store'), [
            'article_id' => $article->id,
            'channel' => SocialDraft::CHANNEL_FACEBOOK,
            'copy' => 'Copy.',
        ]);
        $draft = SocialDraft::first();
        $this->actingAs($editor)->post(route('admin.social-drafts.transition', $draft), ['to' => SocialDraft::STATUS_REVIEWED]);

        $this->assertDatabaseCount('social_publications', 0);
    }

    // ---- privacy ----

    public function test_index_never_leaks_user_email_or_token_like_strings(): void
    {
        $author = $this->author();
        $author->update(['email' => 'privata@example.test']);
        $article = $this->article(['user_id' => $author->id]);
        $this->draft(['article_id' => $article->id]);

        $response = $this->actingAs($this->editor())->get(route('admin.social-drafts.index'));

        $response->assertOk();
        $response->assertDontSee('privata@example.test');
        $response->assertDontSeeText('access_token');
        $response->assertDontSeeText('Authorization:');
    }

    public function test_show_page_never_leaks_email_or_technical_exception_details(): void
    {
        $author = $this->author();
        $author->update(['email' => 'privata2@example.test']);
        $article = $this->article(['user_id' => $author->id]);
        $draft = $this->draft(['article_id' => $article->id]);

        $response = $this->actingAs($this->editor())->get(route('admin.social-drafts.show', $draft));

        $response->assertOk();
        $response->assertDontSee('privata2@example.test');
        $response->assertDontSeeText('Stack trace');
    }

    // ---- N+1 ----

    public function test_index_query_count_does_not_scale_with_number_of_drafts(): void
    {
        $editor = $this->editor();

        for ($i = 0; $i < 3; $i++) {
            $this->draft();
        }

        DB::enableQueryLog();
        $this->actingAs($editor)->get(route('admin.social-drafts.index'));
        $smallCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        // La creazione delle bozze aggiuntive non deve entrare nel conteggio:
        // logging disattivato per tutta la fase di setup.
        for ($i = 0; $i < 12; $i++) {
            $this->draft();
        }

        DB::enableQueryLog();
        $this->actingAs($editor)->get(route('admin.social-drafts.index'));
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual($smallCount, $largeCount, 'Il numero di query non deve crescere con il numero di bozze (rischio N+1).');
    }
}
