<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReviewSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function articleInReview(): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo in revisione',
            'slug' => 'articolo-in-revisione-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_REVIEW,
        ]);
    }

    public function test_approving_without_publish_mode_still_publishes_immediately(): void
    {
        Mail::fake();
        $editor = $this->editor();
        $article = $this->articleInReview();

        $response = $this->actingAs($editor)->patch(route('admin.review.approve', $article));

        $response->assertRedirect(route('admin.review'));
        $fresh = $article->fresh();
        $this->assertSame(Article::STATUS_PUBLISHED, $fresh->status);
        $this->assertNotNull($fresh->published_at);
        $this->assertTrue($fresh->published_at->lessThanOrEqualTo(now()));
    }

    public function test_approving_with_publish_mode_now_publishes_immediately(): void
    {
        Mail::fake();
        $editor = $this->editor();
        $article = $this->articleInReview();

        $response = $this->actingAs($editor)->patch(route('admin.review.approve', $article), [
            'publish_mode' => 'now',
        ]);

        $response->assertRedirect(route('admin.review'));
        $this->assertSame(Article::STATUS_PUBLISHED, $article->fresh()->status);
    }

    public function test_approving_with_scheduled_mode_sets_status_scheduled(): void
    {
        Mail::fake();
        $editor = $this->editor();
        $article = $this->articleInReview();

        $date = now()->addDays(5)->format('Y-m-d');

        $response = $this->actingAs($editor)->patch(route('admin.review.approve', $article), [
            'publish_mode' => 'scheduled',
            'published_date' => $date,
            'published_time' => '10:00',
        ]);

        $response->assertRedirect(route('admin.review'));
        $fresh = $article->fresh();
        $this->assertSame(Article::STATUS_SCHEDULED, $fresh->status);
        $this->assertSame($date, $fresh->publishedAtForEditors()->format('Y-m-d'));
        $this->assertSame('10:00', $fresh->publishedAtForEditors()->format('H:i'));

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $article->id,
            'action' => 'Articolo approvato e programmato',
        ]);
    }

    public function test_approving_with_scheduled_mode_and_past_date_is_rejected(): void
    {
        Mail::fake();
        $editor = $this->editor();
        $article = $this->articleInReview();

        $response = $this->actingAs($editor)->patch(route('admin.review.approve', $article), [
            'publish_mode' => 'scheduled',
            'published_date' => now()->subDay()->format('Y-m-d'),
            'published_time' => '10:00',
        ]);

        $response->assertSessionHasErrors('published_date');
        $this->assertSame(Article::STATUS_REVIEW, $article->fresh()->status);
    }

    public function test_approving_with_scheduled_mode_requires_date_and_time(): void
    {
        Mail::fake();
        $editor = $this->editor();
        $article = $this->articleInReview();

        $response = $this->actingAs($editor)->patch(route('admin.review.approve', $article), [
            'publish_mode' => 'scheduled',
        ]);

        $response->assertSessionHasErrors(['published_date', 'published_time']);
        $this->assertSame(Article::STATUS_REVIEW, $article->fresh()->status);
    }

    public function test_rejecting_is_unaffected_by_scheduling_changes(): void
    {
        Mail::fake();
        $editor = $this->editor();
        $article = $this->articleInReview();

        $response = $this->actingAs($editor)->patch(route('admin.review.reject', $article), [
            'note' => 'Serve una fonte in più.',
        ]);

        $response->assertRedirect(route('admin.review'));
        $fresh = $article->fresh();
        $this->assertSame(Article::STATUS_DRAFT, $fresh->status);
        $this->assertNull($fresh->published_at);
    }
}
