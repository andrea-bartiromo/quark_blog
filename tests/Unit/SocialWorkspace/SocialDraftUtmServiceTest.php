<?php

namespace Tests\Unit\SocialWorkspace;

use App\Models\Article;
use App\Models\SocialDraft;
use App\Models\User;
use App\Services\SocialWorkspace\SocialDraftUtmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SocialDraftUtmServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SocialDraftUtmService
    {
        return new SocialDraftUtmService;
    }

    private function article(array $overrides = []): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-utm-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_resolves_the_article_canonical_url_when_no_custom_url_given(): void
    {
        $article = $this->article();

        $resolved = $this->service()->resolveDestinationUrl($article, null);

        $this->assertSame($article->metaCanonicalUrl(), $resolved);
    }

    public function test_a_valid_same_host_custom_url_is_accepted(): void
    {
        $article = $this->article();
        $custom = url('/articolo/'.$article->slug);

        $resolved = $this->service()->resolveDestinationUrl($article, $custom);

        $this->assertSame($custom, $resolved);
    }

    public function test_a_different_host_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->resolveDestinationUrl($this->article(), 'https://evil.example.com/phishing');
    }

    public function test_javascript_scheme_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->resolveDestinationUrl($this->article(), 'javascript:alert(1)');
    }

    public function test_data_scheme_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->resolveDestinationUrl($this->article(), 'data:text/html,<script>alert(1)</script>');
    }

    public function test_malformed_url_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->resolveDestinationUrl($this->article(), 'not a url at all ://');
    }

    public function test_utm_is_appended_with_channel_defaults(): void
    {
        $article = $this->article();
        $base = $article->metaCanonicalUrl();

        $fb = $this->service()->withUtm($base, SocialDraft::CHANNEL_FACEBOOK, true, null, $article);
        $li = $this->service()->withUtm($base, SocialDraft::CHANNEL_LINKEDIN, true, null, $article);

        $this->assertStringContainsString('utm_source=facebook', $fb);
        $this->assertStringContainsString('utm_medium=social', $fb);
        $this->assertStringContainsString('utm_source=linkedin', $li);
        $this->assertStringContainsString('utm_medium=social', $li);
    }

    public function test_utm_is_deterministic_across_calls(): void
    {
        $article = $this->article();
        $base = $article->metaCanonicalUrl();

        $first = $this->service()->withUtm($base, SocialDraft::CHANNEL_FACEBOOK, true, null, $article);
        $second = $this->service()->withUtm($base, SocialDraft::CHANNEL_FACEBOOK, true, null, $article);

        $this->assertSame($first, $second);
    }

    public function test_use_utm_false_returns_the_url_unchanged(): void
    {
        $article = $this->article();
        $base = $article->metaCanonicalUrl();

        $result = $this->service()->withUtm($base, SocialDraft::CHANNEL_FACEBOOK, false, null, $article);

        $this->assertSame($base, $result);
    }

    public function test_existing_query_string_and_fragment_are_preserved(): void
    {
        $article = $this->article();
        $base = $article->metaCanonicalUrl().'?ref=newsletter#section-2';

        $result = $this->service()->withUtm($base, SocialDraft::CHANNEL_FACEBOOK, true, null, $article);

        $this->assertStringContainsString('ref=newsletter', $result);
        $this->assertStringContainsString('#section-2', $result);
        $this->assertStringContainsString('utm_source=facebook', $result);
    }

    public function test_utm_parameters_are_never_duplicated_when_already_present(): void
    {
        $article = $this->article();
        $base = $article->metaCanonicalUrl().'?utm_source=old&utm_medium=old';

        $result = $this->service()->withUtm($base, SocialDraft::CHANNEL_FACEBOOK, true, null, $article);

        $this->assertSame(1, substr_count($result, 'utm_source='));
        $this->assertSame(1, substr_count($result, 'utm_medium='));
        $this->assertStringContainsString('utm_source=facebook', $result);
        $this->assertStringNotContainsString('utm_source=old', $result);
    }

    public function test_custom_campaign_is_used_verbatim_when_valid(): void
    {
        $article = $this->article();

        $result = $this->service()->withUtm($article->metaCanonicalUrl(), SocialDraft::CHANNEL_FACEBOOK, true, 'lancio-fisica-2026', $article);

        $this->assertStringContainsString('utm_campaign=lancio-fisica-2026', $result);
    }

    public function test_invalid_campaign_format_is_rejected(): void
    {
        $article = $this->article();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->withUtm($article->metaCanonicalUrl(), SocialDraft::CHANNEL_FACEBOOK, true, 'Not Valid!', $article);
    }

    public function test_unicode_query_values_survive_utm_decoration(): void
    {
        $article = $this->article();
        $base = $article->metaCanonicalUrl().'?nome=Universit%C3%A0';

        $result = $this->service()->withUtm($base, SocialDraft::CHANNEL_FACEBOOK, true, null, $article);

        $this->assertStringContainsString('nome=Universit', $result);
        $this->assertStringContainsString('utm_source=facebook', $result);
    }
}
