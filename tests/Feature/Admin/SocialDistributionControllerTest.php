<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use App\Services\SocialDistribution\UtmLinkGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialDistributionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function publishedArticle(array $overrides = []): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Onde gravitazionali osservate di nuovo',
            'slug' => 'onde-gravitazionali-osservate-di-nuovo',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.social-distribution'))->assertRedirect(route('login'));
    }

    public function test_editor_sees_the_form_with_no_link_by_default(): void
    {
        $this->actingAs($this->editor())
            ->get(route('admin.social-distribution'))
            ->assertOk()
            ->assertSee('Distribuzione social')
            ->assertDontSee('utm_source');
    }

    public function test_generates_a_link_for_a_valid_published_article_slug(): void
    {
        $article = $this->publishedArticle();

        $response = $this->actingAs($this->editor())->get(route('admin.social-distribution', ['slug' => $article->slug]));

        $response->assertOk();
        $response->assertSee('utm_source=facebook', false);
        $response->assertSee('utm_medium=social', false);
    }

    public function test_rejects_a_slug_that_does_not_exist(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.social-distribution', ['slug' => 'non-esiste']));

        $response->assertOk();
        $response->assertSee('Nessun articolo pubblicato con questo slug.');
        $response->assertDontSee('utm_source', false);
    }

    public function test_a_scheduled_articles_slug_is_treated_as_not_found(): void
    {
        $article = $this->publishedArticle(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);

        $response = $this->actingAs($this->editor())->get(route('admin.social-distribution', ['slug' => $article->slug]));

        $response->assertOk();
        $response->assertSee('Nessun articolo pubblicato con questo slug.');
    }

    public function test_an_invalid_campaign_name_shows_an_error_instead_of_a_link(): void
    {
        $article = $this->publishedArticle();

        $response = $this->actingAs($this->editor())->get(route('admin.social-distribution', [
            'slug' => $article->slug,
            'campaign' => 'Campagna Con Spazi',
        ]));

        $response->assertOk();
        $response->assertDontSee('utm_source', false);
    }

    public function test_a_custom_campaign_name_is_reflected_in_the_generated_link(): void
    {
        $article = $this->publishedArticle();

        $response = $this->actingAs($this->editor())->get(route('admin.social-distribution', [
            'slug' => $article->slug,
            'campaign' => 'lancio-fisica-2026',
        ]));

        $response->assertOk();
        $response->assertSee('utm_campaign=lancio-fisica-2026', false);
    }

    public function test_defaults_to_the_facebook_channel_when_none_is_selected(): void
    {
        $article = $this->publishedArticle();

        $response = $this->actingAs($this->editor())->get(route('admin.social-distribution', ['slug' => $article->slug]));

        $response->assertOk();
        $response->assertSee('utm_source=facebook', false);
    }

    public function test_a_selected_linkedin_channel_is_reflected_in_the_generated_link(): void
    {
        $article = $this->publishedArticle();

        $response = $this->actingAs($this->editor())->get(route('admin.social-distribution', [
            'slug' => $article->slug,
            'channel' => UtmLinkGenerator::CHANNEL_LINKEDIN,
        ]));

        $response->assertOk();
        $response->assertSee('utm_source=linkedin', false);
    }

    public function test_an_unrecognized_channel_value_falls_back_to_facebook_instead_of_erroring(): void
    {
        $article = $this->publishedArticle();

        $response = $this->actingAs($this->editor())->get(route('admin.social-distribution', [
            'slug' => $article->slug,
            'channel' => 'not-a-real-channel',
        ]));

        $response->assertOk();
        $response->assertSee('utm_source=facebook', false);
    }

    public function test_an_overly_long_slug_is_treated_as_not_found_without_querying_the_database_for_it(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.social-distribution', [
            'slug' => str_repeat('a', 300),
        ]));

        $response->assertOk();
        $response->assertSee('Nessun articolo pubblicato con questo slug.');
    }

    public function test_a_slug_with_surrounding_whitespace_is_trimmed_before_lookup(): void
    {
        $article = $this->publishedArticle();

        $response = $this->actingAs($this->editor())->get(route('admin.social-distribution', [
            'slug' => '  '.$article->slug.'  ',
        ]));

        $response->assertOk();
        $response->assertSee('utm_source=facebook', false);
    }

    public function test_the_copy_button_is_present_but_hidden_for_progressive_enhancement(): void
    {
        $article = $this->publishedArticle();

        $response = $this->actingAs($this->editor())->get(route('admin.social-distribution', ['slug' => $article->slug]));

        $response->assertOk();
        $response->assertSee('js-copy-link', false);
        $response->assertSee('hidden', false);
    }
}
