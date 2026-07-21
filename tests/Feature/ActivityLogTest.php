<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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

    public function test_migration_creates_the_activity_log_table(): void
    {
        $this->assertTrue(Schema::hasTable('activity_log'));

        $columns = Schema::getColumnListing('activity_log');
        foreach (['id', 'user_id', 'action', 'subject_type', 'subject_id', 'subject_title', 'ip', 'created_at'] as $expected) {
            $this->assertContains($expected, $columns);
        }
    }

    public function test_record_inserts_a_row(): void
    {
        $user = $this->editor();
        $this->actingAs($user);

        ActivityLog::record('Evento di prova', 'article', 42, 'Titolo di prova');

        $this->assertDatabaseHas('activity_log', [
            'action' => 'Evento di prova',
            'subject_type' => 'article',
            'subject_id' => 42,
            'subject_title' => 'Titolo di prova',
            'user_id' => $user->id,
        ]);
    }

    public function test_record_can_be_associated_with_a_user(): void
    {
        $user = $this->editor();
        $this->actingAs($user);

        ActivityLog::record('Evento con utente');

        $log = ActivityLog::latest('id')->first();

        $this->assertNotNull($log->user);
        $this->assertSame($user->id, $log->user->id);
    }

    public function test_record_accepts_a_null_subject(): void
    {
        $user = $this->editor();
        $this->actingAs($user);

        ActivityLog::record('Evento senza soggetto');

        $this->assertDatabaseHas('activity_log', [
            'action' => 'Evento senza soggetto',
            'subject_type' => null,
            'subject_id' => null,
        ]);
    }

    public function test_admin_article_deletion_logs_activity_without_failing(): void
    {
        $editor = $this->editor();
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo da eliminare',
            'slug' => 'articolo-da-eliminare',
            'body' => 'Corpo articolo.',
            'category' => 'energia',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($editor)->delete(route('admin.articles.destroy', $article));

        $response->assertRedirect(route('admin.articles'));
        $this->assertDatabaseHas('activity_log', [
            'action' => 'Articolo eliminato',
            'subject_type' => 'article',
            'subject_id' => $article->id,
        ]);
    }

    public function test_collaborator_removal_logs_activity_with_null_subject_id(): void
    {
        $editor = $this->editor();
        $collaborator = $this->author();

        $response = $this->actingAs($editor)->delete(route('admin.collaborators.destroy', $collaborator));

        $response->assertRedirect(route('admin.collaborators'));
        $this->assertDatabaseHas('activity_log', [
            'action' => 'Collaboratore rimosso',
            'subject_type' => 'user',
            'subject_id' => null,
            'subject_title' => $collaborator->name,
        ]);
    }
}
