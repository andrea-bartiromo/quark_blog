<?php

namespace Tests\Feature\SocialWorkspace;

use App\Models\Article;
use App\Models\SocialDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prompt 41/42/43/44/70 — preview server-rendered interne per Facebook e
 * LinkedIn: nessun iframe/script/API esterna, dichiarazione esplicita che
 * la resa finale può differire, fallback cover testuale, warning alt
 * mancante, escaping di markup ostile, Unicode preservato, URL con UTM
 * visibile.
 */
class SocialDraftPreviewTest extends TestCase
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

    public function test_facebook_preview_shows_the_editorial_disclaimer(): void
    {
        $draft = $this->draft(['channel' => SocialDraft::CHANNEL_FACEBOOK]);

        $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.show', $draft))
            ->assertOk()
            ->assertSee('Anteprima Facebook')
            ->assertSee('Anteprima editoriale interna: la resa finale può differire da quella della piattaforma.');
    }

    public function test_linkedin_preview_shows_the_editorial_disclaimer(): void
    {
        $draft = $this->draft(['channel' => SocialDraft::CHANNEL_LINKEDIN]);

        $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.show', $draft))
            ->assertOk()
            ->assertSee('Anteprima LinkedIn')
            ->assertSee('Anteprima editoriale interna: la resa finale può differire da quella della piattaforma.');
    }

    public function test_no_iframe_script_or_external_sdk_in_preview(): void
    {
        $draft = $this->draft();

        $html = $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.show', $draft))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('connect.facebook.net', $html);
        $this->assertStringNotContainsString('platform.linkedin.com', $html);
    }

    public function test_missing_cover_shows_a_textual_fallback_not_a_fake_image(): void
    {
        $article = $this->article(['cover_image' => null, 'title' => 'Titolo senza copertina']);
        $draft = $this->draft(['article_id' => $article->id]);

        $response = $this->actingAs($this->editor())->get(route('admin.social-drafts.show', $draft));

        $response->assertOk();
        $response->assertSee('Titolo senza copertina');

        // Scoped al riquadro di anteprima, non all'intera pagina: la
        // sidebar admin ha una propria <img> (il logo) irrilevante qui.
        preg_match('/<section class="social-draft-preview".*?<\/section>/s', $response->getContent(), $previewBlock);
        $this->assertNotEmpty($previewBlock, 'Blocco anteprima non trovato in pagina.');
        $this->assertStringNotContainsString('<img', $previewBlock[0]);
    }

    public function test_cover_present_without_alt_shows_an_operational_warning(): void
    {
        $article = $this->article(['cover_image' => 'copertina.jpg', 'cover_alt' => null]);
        $draft = $this->draft(['article_id' => $article->id]);

        $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.show', $draft))
            ->assertOk()
            ->assertSee('Testo alternativo mancante');
    }

    public function test_cover_with_alt_shows_no_warning(): void
    {
        $article = $this->article(['cover_image' => 'copertina.jpg', 'cover_alt' => 'Descrizione della copertina']);
        $draft = $this->draft(['article_id' => $article->id]);

        $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.show', $draft))
            ->assertOk()
            ->assertDontSee('Testo alternativo mancante');
    }

    public function test_hostile_markup_in_copy_is_escaped_in_the_preview(): void
    {
        $draft = $this->draft(['copy' => '<script>window.__pwned = true;</script>']);

        $response = $this->actingAs($this->editor())->get(route('admin.social-drafts.show', $draft));

        $response->assertOk();
        $response->assertDontSee('<script>window.__pwned = true;</script>', false);
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_unicode_copy_is_preserved_verbatim_in_the_preview(): void
    {
        $draft = $this->draft(['copy' => 'Università degli Studi — ricerca sui raggi cosmici (© 2026) 🚀']);

        $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.show', $draft))
            ->assertOk()
            ->assertSee('Università degli Studi — ricerca sui raggi cosmici (© 2026) 🚀');
    }

    public function test_preview_url_includes_utm_when_enabled(): void
    {
        $draft = $this->draft(['use_utm' => true]);

        $this->actingAs($this->editor())
            ->get(route('admin.social-drafts.show', $draft))
            ->assertOk()
            ->assertSee('utm_source=facebook', false);
    }

    public function test_preview_url_excludes_utm_when_disabled(): void
    {
        $draft = $this->draft(['use_utm' => false]);

        $response = $this->actingAs($this->editor())->get(route('admin.social-drafts.show', $draft));

        $response->assertOk();
        $response->assertDontSee('utm_source', false);
    }
}
