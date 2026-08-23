<?php

namespace Tests\Unit\SocialDistribution;

use App\Models\Article;
use App\Models\User;
use App\Services\SocialDistribution\UtmLinkGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class UtmLinkGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function article(): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create([
            'user_id' => $author->id,
            'title' => 'Onde gravitazionali osservate di nuovo',
            'slug' => 'onde-gravitazionali-osservate-di-nuovo',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_generates_a_link_with_the_facebook_utm_contract(): void
    {
        $article = $this->article();

        $link = app(UtmLinkGenerator::class)->forArticle($article, UtmLinkGenerator::CHANNEL_FACEBOOK, 'test-campagna');

        $this->assertStringContainsString(route('articolo', $article->slug), $link);
        $this->assertStringContainsString('utm_source=facebook', $link);
        $this->assertStringContainsString('utm_medium=social', $link);
        $this->assertStringContainsString('utm_campaign=test-campagna', $link);
    }

    public function test_default_campaign_name_is_derived_from_article_slug_and_todays_date_when_none_is_given(): void
    {
        $article = $this->article();

        $link = app(UtmLinkGenerator::class)->forArticle($article, UtmLinkGenerator::CHANNEL_FACEBOOK);

        $expected = 'fb-'.$article->slug.'-'.now()->format('Ymd');
        $this->assertStringContainsString('utm_campaign='.$expected, $link);
    }

    public function test_an_unsupported_channel_is_rejected(): void
    {
        $article = $this->article();

        $this->expectException(InvalidArgumentException::class);

        app(UtmLinkGenerator::class)->forArticle($article, 'instagram');
    }

    public function test_a_campaign_name_with_uppercase_or_spaces_is_rejected(): void
    {
        $article = $this->article();

        $this->expectException(InvalidArgumentException::class);

        app(UtmLinkGenerator::class)->forArticle($article, UtmLinkGenerator::CHANNEL_FACEBOOK, 'Campagna Con Spazi');
    }

    public function test_a_campaign_name_that_could_be_pasted_pii_like_an_email_is_rejected(): void
    {
        $article = $this->article();

        $this->expectException(InvalidArgumentException::class);

        app(UtmLinkGenerator::class)->forArticle($article, UtmLinkGenerator::CHANNEL_FACEBOOK, 'redattore@kairus.it');
    }

    public function test_an_overly_long_campaign_name_is_rejected(): void
    {
        $article = $this->article();

        $this->expectException(InvalidArgumentException::class);

        app(UtmLinkGenerator::class)->forArticle($article, UtmLinkGenerator::CHANNEL_FACEBOOK, str_repeat('a-', 60));
    }

    public function test_the_generated_link_never_alters_the_canonical_url(): void
    {
        $article = $this->article();

        $link = app(UtmLinkGenerator::class)->forArticle($article, UtmLinkGenerator::CHANNEL_FACEBOOK, 'campagna-x');

        $this->assertSame($article->metaCanonicalUrl(), route('articolo', $article->slug));
        $this->assertStringStartsWith($article->metaCanonicalUrl(), $link);
    }

    public function test_hyphenated_multi_word_campaign_names_are_accepted(): void
    {
        $article = $this->article();

        $link = app(UtmLinkGenerator::class)->forArticle($article, UtmLinkGenerator::CHANNEL_FACEBOOK, 'lancio-fisica-2026');

        $this->assertStringContainsString('utm_campaign=lancio-fisica-2026', $link);
    }
}
