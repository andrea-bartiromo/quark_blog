<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AuditArticleBodyContaminationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_known_signatures_without_exposing_the_body_or_modifying_it(): void
    {
        $body = '<div class="conversation-turn" data-message-id="secret"><p style="color:red">Testo riservato</p><script>alert(1)</script><iframe src="https://example.com"></iframe><a href="https://example.org/paper?utm_source=chatgpt.com&id=7">Fonte</a></div>';
        $article = $this->article('Corpo contaminato', $body);

        $exit = Artisan::call('articles:audit-body-contamination', ['--json' => true]);
        $output = Artisan::output();
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exit);
        $this->assertTrue($decoded['read_only']);
        $this->assertSame([
            'SCRIPT',
            'IFRAME',
            'INLINE_STYLE',
            'CHATGPT_DATA_ATTRIBUTE',
            'CHATGPT_CLASS',
            'FOREIGN_PLATFORM_UTM_SOURCE',
        ], $decoded['articles'][0]['findings']);
        $this->assertStringNotContainsString('Testo riservato', $output);
        $this->assertSame($body, $article->fresh()->body);
    }

    public function test_dry_run_returns_hashes_removed_nodes_and_a_limited_preview_without_writing(): void
    {
        $body = '<div data-turn="1"><p>Contenuto utile.</p><script>segreto()</script></div>';
        $article = $this->article('Dry run', $body);

        Artisan::call('articles:audit-body-contamination', ['--dry-run' => true, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $dryRun = $decoded['articles'][0]['dry_run'];

        $this->assertSame(hash('sha256', $body), $dryRun['before_hash']);
        $this->assertNotSame($dryRun['before_hash'], $dryRun['after_hash']);
        $this->assertGreaterThan(0, $dryRun['removed_nodes']);
        $this->assertSame('Contenuto utile.', $dryRun['preview']);
        $this->assertSame($body, $article->fresh()->body);
    }

    public function test_the_command_has_no_execute_option(): void
    {
        $definition = Artisan::all()['articles:audit-body-contamination']->getDefinition();

        $this->assertFalse($definition->hasOption('execute'));
    }

    public function test_dry_run_removes_only_the_foreign_source_and_preserves_the_raw_query_contract(): void
    {
        $body = '<p><a href="https://example.org/paper?tag=a&tag=b&utm_source=chatgpt.com&flag&q=a+b&q=a%20b#section">Fonte</a></p>';
        $article = $this->article('Query ripetuta', $body);

        Artisan::call('articles:audit-body-contamination', ['--article' => $article->id, '--dry-run' => true, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $expected = '<p><a href="https://example.org/paper?tag=a&tag=b&flag&q=a+b&q=a%20b#section">Fonte</a></p>';

        $this->assertSame(hash('sha256', $expected), $decoded['articles'][0]['dry_run']['after_hash']);
        $this->assertSame($body, $article->fresh()->body);
    }

    public function test_escaped_markup_and_html_comments_do_not_create_false_findings(): void
    {
        $body = '<p>Esempio: &lt;p style="color:red" data-turn="1" class="conversation-turn"&gt;</p><!-- <script>alert(1)</script><iframe></iframe> -->';
        $article = $this->article('Markup documentato', $body);

        Artisan::call('articles:audit-body-contamination', ['--article' => $article->id, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([], $decoded['articles']);
        $this->assertSame($body, $article->fresh()->body);
    }

    public function test_dry_run_preserves_admin_supported_markup_while_removing_only_contamination(): void
    {
        $body = '<table class="editor-table"><tr><td style="color:red">Dato</td></tr></table><img src="/media/chart.png" alt="Grafico"><a href="https://example.org/paper?utm_source=openai&id=7">Fonte</a>';
        $article = $this->article('Markup admin', $body);

        Artisan::call('articles:audit-body-contamination', ['--article' => $article->id, '--dry-run' => true, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $expected = '<table class="editor-table"><tr><td>Dato</td></tr></table><img src="/media/chart.png" alt="Grafico"><a href="https://example.org/paper?id=7">Fonte</a>';

        $this->assertSame(hash('sha256', $expected), $decoded['articles'][0]['dry_run']['after_hash']);
        $this->assertSame($body, $article->fresh()->body);
    }

    public function test_dry_run_does_not_decode_entity_like_text_inside_an_href_twice(): void
    {
        $body = '<p><a href="https://example.org/paper?q=rock&amp;amp;roll&amp;utm_source=chatgpt.com">Fonte</a></p>';
        $article = $this->article('Entità nella query', $body);

        Artisan::call('articles:audit-body-contamination', ['--article' => $article->id, '--dry-run' => true, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $expected = '<p><a href="https://example.org/paper?q=rock&amp;amp;roll">Fonte</a></p>';

        $this->assertSame(hash('sha256', $expected), $decoded['articles'][0]['dry_run']['after_hash']);
        $this->assertSame($body, $article->fresh()->body);
    }

    public function test_malformed_closing_tags_cannot_escape_the_audit_fragment(): void
    {
        $body = '</body><script>alert(1)</script><p>Contenuto successivo.</p>';
        $article = $this->article('Frammento malformato', $body);

        Artisan::call('articles:audit-body-contamination', ['--article' => $article->id, '--dry-run' => true, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $dryRun = $decoded['articles'][0]['dry_run'];

        $this->assertSame(['SCRIPT'], $decoded['articles'][0]['findings']);
        $this->assertSame('Contenuto successivo.', $dryRun['preview']);
        $this->assertSame($body, $article->fresh()->body);
    }

    public function test_dry_run_preserves_unrelated_markup_representation_byte_for_byte(): void
    {
        $body = '<p style="x">O&apos;Brien &copy; <BR/></p>';
        $article = $this->article('Markup invariato', $body);

        Artisan::call('articles:audit-body-contamination', ['--article' => $article->id, '--dry-run' => true, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $expected = '<p>O&apos;Brien &copy; <BR/></p>';

        $this->assertSame(hash('sha256', $expected), $decoded['articles'][0]['dry_run']['after_hash']);
        $this->assertSame($body, $article->fresh()->body);
    }

    public function test_an_unclosed_script_is_removed_with_its_inert_tail(): void
    {
        $body = '<p>Contenuto utile.</p><script>window.secret = "non pubblico";';
        $article = $this->article('Script non chiuso', $body);

        Artisan::call('articles:audit-body-contamination', ['--article' => $article->id, '--dry-run' => true, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['SCRIPT'], $decoded['articles'][0]['findings']);
        $this->assertSame('Contenuto utile.', $decoded['articles'][0]['dry_run']['preview']);
        $this->assertSame($body, $article->fresh()->body);
    }

    private function article(string $title, string $body): Article
    {
        $author = User::factory()->create(['role' => 'editor']);

        return Article::query()->create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => $body,
            'category' => 'energia',
            'status' => Article::STATUS_DRAFT,
            'read_minutes' => 2,
            'verification_status' => 'unverified',
        ]);
    }
}
