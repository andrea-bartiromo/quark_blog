<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\EditorialOperations\EditorialOperationsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialOperationsDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_aggregates_real_sources_and_marks_unavailable_sections_explicitly(): void
    {
        Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Scheduled Operations Test',
            'slug' => 'scheduled-operations-test',
            'excerpt' => '',
            'body' => '<p>Body</p>',
            'category' => 'operations-test',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
            'read_minutes' => 1,
        ]));

        $snapshot = app(EditorialOperationsDashboardService::class)->snapshot();

        $this->assertCount(1, $snapshot['da_pubblicare']);
        $this->assertNotEmpty($snapshot['da_sistemare']);
        $this->assertArrayHasKey('summary', $snapshot['seo']);
        $this->assertFalse($snapshot['opportunita']['available']);
        $this->assertFalse($snapshot['distribuzione']['available']);
    }

    public function test_draft_articles_are_not_exposed_in_operational_sections(): void
    {
        $draft = Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Draft Operations Test',
            'slug' => 'draft-operations-test',
            'excerpt' => '',
            'body' => '<p>Body</p>',
            'category' => 'operations-test',
            'status' => Article::STATUS_DRAFT,
            'read_minutes' => 1,
        ]));

        $snapshot = app(EditorialOperationsDashboardService::class)->snapshot();
        $ids = collect($snapshot['da_pubblicare'])->pluck('article_id')
            ->merge(collect($snapshot['da_sistemare'])->pluck('article_id'));

        $this->assertFalse($ids->contains($draft->id));
    }
}
