<?php

namespace Tests\Unit\SocialWorkspace;

use App\Models\Article;
use App\Models\SocialDraft;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifica lo schema di social_drafts creato dalla migration dedicata —
 * separata e mai collegata a social_publications (invariata, verificato
 * in SocialPublicationUnaffectedTest).
 */
class SocialDraftSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function article(): Article
    {
        return Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_table_has_the_exact_columns_specified(): void
    {
        $expected = [
            'id', 'article_id', 'channel', 'status', 'copy', 'destination_url',
            'use_utm', 'utm_campaign', 'scheduled_at', 'created_by',
            'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at',
            'created_at', 'updated_at',
        ];

        $columns = Schema::getColumnListing('social_drafts');

        foreach ($expected as $column) {
            $this->assertContains($column, $columns, "Colonna mancante: {$column}");
        }
    }

    public function test_forbidden_delivery_columns_are_absent(): void
    {
        $forbidden = ['token', 'remote_id', 'remote_url', 'attempt_count', 'last_error_class', 'last_error_message', 'payload', 'webhook_url'];
        $columns = Schema::getColumnListing('social_drafts');

        foreach ($forbidden as $column) {
            $this->assertNotContains($column, $columns, "Colonna vietata presente: {$column}");
        }
    }

    public function test_status_defaults_to_draft(): void
    {
        $draft = SocialDraft::create([
            'article_id' => $this->article()->id,
            'channel' => SocialDraft::CHANNEL_FACEBOOK,
        ]);

        $this->assertSame(SocialDraft::STATUS_DRAFT, $draft->fresh()->status);
    }

    public function test_use_utm_defaults_to_true(): void
    {
        $draft = SocialDraft::create([
            'article_id' => $this->article()->id,
            'channel' => SocialDraft::CHANNEL_LINKEDIN,
        ]);

        $this->assertTrue($draft->fresh()->use_utm);
    }

    public function test_article_id_is_required(): void
    {
        $this->expectException(QueryException::class);

        SocialDraft::create(['channel' => SocialDraft::CHANNEL_FACEBOOK]);
    }

    public function test_deleting_an_article_with_a_social_draft_is_restricted(): void
    {
        $article = $this->article();
        SocialDraft::create(['article_id' => $article->id, 'channel' => SocialDraft::CHANNEL_FACEBOOK]);

        $this->expectException(QueryException::class);
        $article->delete();
    }

    public function test_deleting_a_user_nulls_the_actor_foreign_keys(): void
    {
        $actor = $this->author();
        $draft = SocialDraft::create([
            'article_id' => $this->article()->id,
            'channel' => SocialDraft::CHANNEL_FACEBOOK,
            'created_by' => $actor->id,
        ]);

        $actor->delete();

        $this->assertNull($draft->fresh()->created_by);
    }
}
