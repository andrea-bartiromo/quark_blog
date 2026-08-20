<?php

namespace Tests\Feature\Security;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S7 CONFIRMED — Stored XSS via article body, reachable by the 'author'
 * role (a lower-trust collaborator role, distinct from 'editor'/'admin' —
 * see User::canAccessRedazione() and the mandatory review/approval gate
 * before publish, App\Http\Controllers\Admin\ReviewController::approve()).
 *
 * Redazione\StoreArticleRequest/UpdateArticleRequest validate 'body' only
 * as 'required' — no HTML sanitization, no allowed-tag whitelist. TinyMCE
 * is a client-side WYSIWYG only: a POST straight to the endpoint (bypassing
 * the browser entirely) can carry an arbitrary raw string. Once an editor
 * approves the article (ReviewController::approve() only flips `status`,
 * never touches `body`), resources/views/articolo.blade.php renders the
 * body RAW via {!! $mainBodyWithTocIds !!} whenever the stored value
 * "looks like HTML" ($isHtml = strip_tags($body) !== $body in
 * articolo.blade.php) — every public visitor of that article executes the
 * attacker's script.
 */
class ArticleBodyStoredXssTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_script_tag_submitted_by_an_author_survives_editor_approval_and_renders_unescaped_on_the_public_page(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $editor = User::factory()->create(['role' => 'editor']);

        $payload = [
            'title' => 'Articolo con payload',
            'excerpt' => 'Sommario',
            'body' => '<p>Contenuto legittimo.</p><script>alert(document.cookie)</script>',
            'category' => array_key_first(config('laboratorio.categories')),
        ];

        $this->actingAs($author)
            ->post(route('redazione.articles.store'), $payload)
            ->assertRedirect(route('redazione.articles'));

        $article = Article::where('title', 'Articolo con payload')->firstOrFail();
        $this->assertSame('review', $article->status);

        $this->actingAs($editor)
            ->patch(route('admin.review.approve', $article))
            ->assertRedirect(route('admin.review'));

        $article->refresh();
        $this->assertSame(Article::STATUS_PUBLISHED, $article->status);

        $html = $this->get(route('articolo', $article->slug))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            '<script>alert(document.cookie)</script>',
            $html,
            'Il tag <script> inviato dal ruolo author sopravvive fino alla pagina pubblica: XSS memorizzato.'
        );

        $this->assertStringContainsString('Contenuto legittimo.', $html);
    }
}
