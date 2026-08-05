<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function baseFormData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Articolo programmato',
            'excerpt' => 'Sommario',
            'body' => 'Corpo articolo di prova.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_SCHEDULED,
            'published_date' => now()->addDays(3)->format('Y-m-d'),
            'published_time' => '09:00',
        ], $overrides);
    }

    public function test_admin_can_schedule_an_article_and_published_at_is_stored_in_utc(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(
            route('admin.articles.store'),
            $this->baseFormData()
        );

        $response->assertRedirect(route('admin.articles'));

        $article = Article::where('title', 'Articolo programmato')->firstOrFail();
        $this->assertSame(Article::STATUS_SCHEDULED, $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertSame('UTC', $article->published_at->timezoneName);

        // Riconvertito in orario redazionale deve corrispondere a quanto inserito.
        $this->assertSame('09:00', $article->publishedAtForEditors()->format('H:i'));
    }

    public function test_scheduling_requires_date_and_time(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(
            route('admin.articles.store'),
            $this->baseFormData(['published_date' => null, 'published_time' => null])
        );

        $response->assertSessionHasErrors(['published_date', 'published_time']);
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo programmato']);
    }

    public function test_scheduling_a_past_date_is_rejected(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(
            route('admin.articles.store'),
            $this->baseFormData([
                'published_date' => now()->subDay()->format('Y-m-d'),
                'published_time' => '09:00',
            ])
        );

        $response->assertSessionHasErrors('published_date');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo programmato']);
    }

    public function test_draft_and_review_never_receive_a_published_at_even_if_sent_by_the_client(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Articolo bozza',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_DRAFT,
            'published_date' => now()->addDay()->format('Y-m-d'),
            'published_time' => '09:00',
        ]);

        $response->assertRedirect(route('admin.articles'));

        $article = Article::where('title', 'Articolo bozza')->firstOrFail();
        $this->assertNull($article->published_at);
    }

    public function test_reverting_a_scheduled_article_to_draft_nulls_published_at(): void
    {
        $editor = $this->editor();
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Da riportare in bozza',
            'slug' => 'da-riportare-in-bozza',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'body' => $article->body,
            'category' => $article->category,
            'status' => Article::STATUS_DRAFT,
        ]);

        $response->assertRedirect(route('admin.articles'));

        $this->assertNull($article->fresh()->published_at);
    }

    public function test_admin_can_reschedule_an_existing_scheduled_article_to_a_new_date(): void
    {
        $editor = $this->editor();
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Da riprogrammare',
            'slug' => 'da-riprogrammare',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $newDate = now()->addDays(10)->format('Y-m-d');

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'body' => $article->body,
            'category' => $article->category,
            'status' => Article::STATUS_SCHEDULED,
            'published_date' => $newDate,
            'published_time' => '18:30',
        ]);

        $response->assertRedirect(route('admin.articles'));

        $fresh = $article->fresh();
        $this->assertSame(Article::STATUS_SCHEDULED, $fresh->status);
        $this->assertSame($newDate, $fresh->publishedAtForEditors()->format('Y-m-d'));
        $this->assertSame('18:30', $fresh->publishedAtForEditors()->format('H:i'));
    }

    public function test_publishing_immediately_still_works_without_date_fields(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Pubblicato subito',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
        ]);

        $response->assertRedirect(route('admin.articles'));

        $article = Article::where('title', 'Pubblicato subito')->firstOrFail();
        $this->assertNotNull($article->published_at);
        $this->assertTrue($article->published_at->lessThanOrEqualTo(now()));
    }
}
