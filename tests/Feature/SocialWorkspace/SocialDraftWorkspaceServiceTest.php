<?php

namespace Tests\Feature\SocialWorkspace;

use App\Models\Article;
use App\Models\SocialDraft;
use App\Models\User;
use App\Services\SocialWorkspace\SocialDraftValidationException;
use App\Services\SocialWorkspace\SocialDraftWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialDraftWorkspaceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SocialDraftWorkspaceService
    {
        return app(SocialDraftWorkspaceService::class);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function draft(array $overrides = []): SocialDraft
    {
        return SocialDraft::create(array_merge([
            'article_id' => $this->article()->id,
            'channel' => SocialDraft::CHANNEL_FACEBOOK,
            'status' => SocialDraft::STATUS_DRAFT,
            'copy' => 'Testo di prova.',
        ], $overrides));
    }

    // ---- transizioni + audit ----

    public function test_draft_to_reviewed_records_reviewer_and_timestamp(): void
    {
        $draft = $this->draft();
        $editor = $this->editor();

        $result = $this->service()->transition($draft, SocialDraft::STATUS_REVIEWED, $editor);

        $this->assertSame($editor->id, $result->reviewed_by);
        $this->assertNotNull($result->reviewed_at);
    }

    public function test_reviewed_to_draft_clears_review_fields(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_REVIEWED, 'reviewed_by' => $this->editor()->id, 'reviewed_at' => now()]);

        $result = $this->service()->transition($draft, SocialDraft::STATUS_DRAFT, null);

        $this->assertNull($result->reviewed_by);
        $this->assertNull($result->reviewed_at);
    }

    public function test_reviewed_to_approved_requires_non_empty_copy(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_REVIEWED, 'copy' => null]);

        $this->expectException(SocialDraftValidationException::class);

        $this->service()->transition($draft, SocialDraft::STATUS_APPROVED, $this->editor());
    }

    public function test_reviewed_to_approved_records_approver(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_REVIEWED]);
        $editor = $this->editor();

        $result = $this->service()->transition($draft, SocialDraft::STATUS_APPROVED, $editor);

        $this->assertSame($editor->id, $result->approved_by);
        $this->assertNotNull($result->approved_at);
    }

    public function test_approved_to_reviewed_clears_approval_fields(): void
    {
        $draft = $this->draft([
            'status' => SocialDraft::STATUS_APPROVED,
            'approved_by' => $this->editor()->id,
            'approved_at' => now(),
        ]);

        $result = $this->service()->transition($draft, SocialDraft::STATUS_REVIEWED, null);

        $this->assertNull($result->approved_by);
        $this->assertNull($result->approved_at);
    }

    public function test_every_transition_writes_an_activity_log_entry(): void
    {
        $draft = $this->draft();

        $this->service()->transition($draft, SocialDraft::STATUS_REVIEWED, $this->editor());

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'social_draft',
            'subject_id' => $draft->id,
        ]);
    }

    public function test_forbidden_transition_is_rejected_with_no_state_change(): void
    {
        $draft = $this->draft();

        try {
            $this->service()->transition($draft, SocialDraft::STATUS_SCHEDULED, $this->editor());
            $this->fail('Doveva lanciare SocialDraftValidationException.');
        } catch (SocialDraftValidationException) {
            // atteso
        }

        $this->assertSame(SocialDraft::STATUS_DRAFT, $draft->fresh()->status);
    }

    public function test_transition_to_published_is_always_rejected_regardless_of_source_state(): void
    {
        foreach ([SocialDraft::STATUS_DRAFT, SocialDraft::STATUS_REVIEWED, SocialDraft::STATUS_APPROVED, SocialDraft::STATUS_SCHEDULED] as $status) {
            $draft = $this->draft(['status' => $status]);
            $rejected = false;

            try {
                $this->service()->transition($draft, SocialDraft::STATUS_PUBLISHED, null);
            } catch (SocialDraftValidationException) {
                $rejected = true;
            }

            $this->assertTrue($rejected, "Il passaggio a published da {$status} doveva essere rifiutato.");
            $this->assertSame($status, $draft->fresh()->status);
        }
    }

    public function test_transition_to_failed_is_always_rejected(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_APPROVED]);

        $this->expectException(SocialDraftValidationException::class);

        $this->service()->transition($draft, SocialDraft::STATUS_FAILED, null);
    }

    // ---- programmazione: articolo ----

    public function test_scheduling_requires_the_article_to_be_published_or_scheduled(): void
    {
        $article = $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $draft = $this->draft(['article_id' => $article->id, 'status' => SocialDraft::STATUS_APPROVED]);

        $this->service()->update($draft, ['scheduled_date' => now()->addDays(3)->format('Y-m-d'), 'scheduled_time' => '10:00']);

        $this->expectException(SocialDraftValidationException::class);
        $this->service()->transition($draft, SocialDraft::STATUS_SCHEDULED, null);
    }

    public function test_scheduling_a_draft_for_a_scheduled_article_must_be_after_article_publication(): void
    {
        $articlePublishAt = now()->addDays(5);
        $article = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => $articlePublishAt]);
        $draft = $this->draft(['article_id' => $article->id, 'status' => SocialDraft::STATUS_APPROVED]);

        // Social schedulato PRIMA della pubblicazione articolo: vietato.
        $this->service()->update($draft, [
            'scheduled_date' => $articlePublishAt->clone()->subHour()->timezone('Europe/Rome')->format('Y-m-d'),
            'scheduled_time' => $articlePublishAt->clone()->subHour()->timezone('Europe/Rome')->format('H:i'),
        ]);

        $this->expectException(SocialDraftValidationException::class);
        $this->service()->transition($draft, SocialDraft::STATUS_SCHEDULED, null);
    }

    public function test_scheduling_a_draft_for_a_scheduled_article_after_publication_succeeds(): void
    {
        $articlePublishAt = now()->addDays(5);
        $article = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => $articlePublishAt]);
        $draft = $this->draft(['article_id' => $article->id, 'status' => SocialDraft::STATUS_APPROVED]);

        $after = $articlePublishAt->clone()->addHour()->timezone('Europe/Rome');
        $this->service()->update($draft, ['scheduled_date' => $after->format('Y-m-d'), 'scheduled_time' => $after->format('H:i')]);

        $result = $this->service()->transition($draft, SocialDraft::STATUS_SCHEDULED, null);

        $this->assertSame(SocialDraft::STATUS_SCHEDULED, $result->status);
    }

    public function test_scheduling_a_published_article_in_the_future_succeeds(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_APPROVED]);
        $future = now()->addDays(2)->timezone('Europe/Rome');

        $this->service()->update($draft, ['scheduled_date' => $future->format('Y-m-d'), 'scheduled_time' => $future->format('H:i')]);

        $result = $this->service()->transition($draft, SocialDraft::STATUS_SCHEDULED, null);

        $this->assertSame(SocialDraft::STATUS_SCHEDULED, $result->status);
    }

    public function test_scheduling_in_the_past_is_rejected(): void
    {
        $article = $this->article(['status' => Article::STATUS_PUBLISHED, 'published_at' => now()->subDays(10)]);
        $draft = $this->draft(['article_id' => $article->id, 'status' => SocialDraft::STATUS_APPROVED]);

        // Aggiorna direttamente per bypassare la validazione futuro/passato
        // del metodo update(), che non applica questo controllo: solo
        // transition() verso "scheduled" lo fa.
        $draft->forceFill(['scheduled_at' => now()->subHour()])->save();

        $this->expectException(SocialDraftValidationException::class);
        $this->service()->transition($draft, SocialDraft::STATUS_SCHEDULED, null);
    }

    public function test_scheduling_without_copy_is_rejected(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_APPROVED, 'copy' => 'x']);
        $future = now()->addDays(2)->timezone('Europe/Rome');
        $this->service()->update($draft, ['scheduled_date' => $future->format('Y-m-d'), 'scheduled_time' => $future->format('H:i')]);
        $draft->forceFill(['copy' => null])->save();

        $this->expectException(SocialDraftValidationException::class);
        $this->service()->transition($draft, SocialDraft::STATUS_SCHEDULED, null);
    }

    // ---- collisioni ----

    public function test_same_channel_same_instant_collision_blocks_scheduling(): void
    {
        $instant = now()->addDays(3)->timezone('Europe/Rome');

        $existing = $this->draft(['status' => SocialDraft::STATUS_SCHEDULED, 'scheduled_at' => now()->addDays(3)]);

        $candidate = $this->draft(['channel' => SocialDraft::CHANNEL_FACEBOOK, 'status' => SocialDraft::STATUS_APPROVED]);
        $this->service()->update($candidate, ['scheduled_date' => $instant->format('Y-m-d'), 'scheduled_time' => $instant->format('H:i')]);

        // Allinea l'istante esatto (stesso secondo) per garantire la collisione.
        $candidate->forceFill(['scheduled_at' => $existing->scheduled_at])->save();

        $this->expectException(SocialDraftValidationException::class);
        $this->service()->transition($candidate, SocialDraft::STATUS_SCHEDULED, null);
    }

    public function test_different_channel_same_instant_does_not_collide(): void
    {
        $instant = now()->addDays(3);

        $this->draft(['channel' => SocialDraft::CHANNEL_FACEBOOK, 'status' => SocialDraft::STATUS_SCHEDULED, 'scheduled_at' => $instant]);

        $candidate = $this->draft(['channel' => SocialDraft::CHANNEL_LINKEDIN, 'status' => SocialDraft::STATUS_APPROVED]);
        $candidate->forceFill(['scheduled_at' => $instant])->save();

        $result = $this->service()->transition($candidate, SocialDraft::STATUS_SCHEDULED, null);

        $this->assertSame(SocialDraft::STATUS_SCHEDULED, $result->status);
    }

    public function test_same_channel_different_instant_does_not_collide(): void
    {
        $this->draft(['channel' => SocialDraft::CHANNEL_FACEBOOK, 'status' => SocialDraft::STATUS_SCHEDULED, 'scheduled_at' => now()->addDays(3)]);

        $candidate = $this->draft(['channel' => SocialDraft::CHANNEL_FACEBOOK, 'status' => SocialDraft::STATUS_APPROVED]);
        $candidate->forceFill(['scheduled_at' => now()->addDays(4)])->save();

        $result = $this->service()->transition($candidate, SocialDraft::STATUS_SCHEDULED, null);

        $this->assertSame(SocialDraft::STATUS_SCHEDULED, $result->status);
    }

    public function test_a_cancelled_and_reapproved_draft_no_longer_collides(): void
    {
        $instant = now()->addDays(3);

        $blocker = $this->draft(['channel' => SocialDraft::CHANNEL_FACEBOOK, 'status' => SocialDraft::STATUS_SCHEDULED, 'scheduled_at' => $instant]);
        // Annulla la programmazione del "blocker": non è più scheduled.
        $this->service()->transition($blocker, SocialDraft::STATUS_APPROVED, null);

        $candidate = $this->draft(['channel' => SocialDraft::CHANNEL_FACEBOOK, 'status' => SocialDraft::STATUS_APPROVED]);
        $candidate->forceFill(['scheduled_at' => $instant])->save();

        $result = $this->service()->transition($candidate, SocialDraft::STATUS_SCHEDULED, null);

        $this->assertSame(SocialDraft::STATUS_SCHEDULED, $result->status);
    }

    public function test_collision_never_moves_any_date(): void
    {
        $instant = now()->addDays(3);
        $existing = $this->draft(['status' => SocialDraft::STATUS_SCHEDULED, 'scheduled_at' => $instant]);

        $candidate = $this->draft(['status' => SocialDraft::STATUS_APPROVED]);
        $candidate->forceFill(['scheduled_at' => $instant])->save();

        try {
            $this->service()->transition($candidate, SocialDraft::STATUS_SCHEDULED, null);
        } catch (SocialDraftValidationException) {
            // atteso
        }

        $this->assertSame($instant->format('Y-m-d H:i:s'), $existing->fresh()->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame(SocialDraft::STATUS_APPROVED, $candidate->fresh()->status);
    }

    // ---- annulla programmazione ----

    public function test_cancelling_a_schedule_clears_the_scheduled_at_field(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_SCHEDULED, 'scheduled_at' => now()->addDays(3)]);

        $result = $this->service()->transition($draft, SocialDraft::STATUS_APPROVED, null);

        $this->assertNull($result->scheduled_at);
    }

    // ---- immutabilità campi dopo approvazione ----

    public function test_copy_cannot_be_edited_once_approved(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_APPROVED]);

        $this->expectException(SocialDraftValidationException::class);
        $this->service()->update($draft, ['copy' => 'Testo modificato di nascosto.']);
    }

    public function test_destination_url_cannot_be_edited_once_approved(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_APPROVED]);

        $this->expectException(SocialDraftValidationException::class);
        $this->service()->update($draft, ['destination_url' => url('/altra-pagina')]);
    }

    public function test_scheduled_at_remains_editable_while_approved(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_APPROVED]);
        $future = now()->addDays(6)->timezone('Europe/Rome');

        $result = $this->service()->update($draft, ['scheduled_date' => $future->format('Y-m-d'), 'scheduled_time' => $future->format('H:i')]);

        $this->assertNotNull($result->scheduled_at);
    }

    public function test_nothing_is_editable_once_scheduled(): void
    {
        $draft = $this->draft(['status' => SocialDraft::STATUS_SCHEDULED, 'scheduled_at' => now()->addDays(2)]);

        $this->expectException(SocialDraftValidationException::class);
        $this->service()->update($draft, ['copy' => 'x']);
    }

    // ---- URL / destinazione ----

    public function test_unsafe_destination_url_is_rejected_on_update(): void
    {
        $draft = $this->draft();

        $this->expectException(SocialDraftValidationException::class);
        $this->service()->update($draft, ['destination_url' => 'javascript:alert(1)']);
    }
}
