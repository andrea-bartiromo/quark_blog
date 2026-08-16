<?php

namespace Tests\Feature\Communication;

use App\Console\Commands\CommunicationReviewStaleSends;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\StaleSendRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class StaleSendRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function sendingRow(int $minutesAgo = 0): CommunicationSend
    {
        $campaign = CommunicationCampaign::factory()->create(['status' => CommunicationCampaign::STATUS_SENDING]);
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $send = CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subscriber->id,
            'status' => CommunicationSend::STATUS_SENDING,
        ]);

        if ($minutesAgo > 0) {
            // updated_at non è in $fillable: update() lo ignorerebbe
            // silenziosamente via mass-assignment guarding. Scrittura
            // diretta per bypassare Eloquent qui.
            DB::table('comm_sends')->where('id', $send->id)->update(['updated_at' => now()->subMinutes($minutesAgo)]);
        }

        return $send->fresh();
    }

    public function test_a_recently_claimed_row_is_not_considered_stale(): void
    {
        $this->sendingRow(minutesAgo: 2);

        $stale = app(StaleSendRecoveryService::class)->findStale(olderThanMinutes: 30);

        $this->assertCount(0, $stale);
    }

    public function test_a_row_stuck_beyond_the_threshold_is_found(): void
    {
        $send = $this->sendingRow(minutesAgo: 45);

        $stale = app(StaleSendRecoveryService::class)->findStale(olderThanMinutes: 30);

        $this->assertCount(1, $stale);
        $this->assertSame($send->id, $stale->first()->id);
    }

    public function test_only_sending_rows_are_ever_considered_stale_never_queued_or_terminal(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['status' => CommunicationCampaign::STATUS_SENDING]);

        foreach ([
            CommunicationSend::STATUS_QUEUED,
            CommunicationSend::STATUS_SENT,
            CommunicationSend::STATUS_FAILED,
            CommunicationSend::STATUS_CANCELLED,
        ] as $status) {
            $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
            $send = CommunicationSend::create([
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'status' => $status,
            ]);
            DB::table('comm_sends')->where('id', $send->id)->update(['updated_at' => now()->subHours(2)]);
        }

        $stale = app(StaleSendRecoveryService::class)->findStale(olderThanMinutes: 30);

        $this->assertCount(0, $stale);
    }

    public function test_release_transitions_a_stale_row_back_to_queued(): void
    {
        $send = $this->sendingRow(minutesAgo: 45);

        $released = app(StaleSendRecoveryService::class)->release($send);

        $this->assertTrue($released);
        $fresh = $send->fresh();
        $this->assertSame(CommunicationSend::STATUS_QUEUED, $fresh->status);
        $this->assertNotNull($fresh->failure_reason);
    }

    public function test_release_returns_false_without_throwing_if_the_row_already_moved_on(): void
    {
        $send = $this->sendingRow(minutesAgo: 45);
        $send->update(['status' => CommunicationSend::STATUS_SENT, 'sent_at' => now()]);

        $released = app(StaleSendRecoveryService::class)->release($send);

        $this->assertFalse($released);
        $this->assertSame(CommunicationSend::STATUS_SENT, $send->fresh()->status);
    }

    public function test_command_reports_stale_rows_without_releasing_them_by_default(): void
    {
        $send = $this->sendingRow(minutesAgo: 45);

        $command = app(CommunicationReviewStaleSends::class);
        $command->setLaravel(app());
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--minutes' => 30]);

        $this->assertSame(SymfonyCommand::SUCCESS, $exitCode);
        $this->assertStringContainsString((string) $send->id, $tester->getDisplay());
        $this->assertSame(CommunicationSend::STATUS_SENDING, $send->fresh()->status);
    }

    public function test_command_release_all_flag_releases_every_stale_row_found(): void
    {
        $send1 = $this->sendingRow(minutesAgo: 45);
        $send2 = $this->sendingRow(minutesAgo: 60);
        $notStale = $this->sendingRow(minutesAgo: 2);

        $command = app(CommunicationReviewStaleSends::class);
        $command->setLaravel(app());
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--minutes' => 30, '--release-all' => true]);

        $this->assertSame(SymfonyCommand::SUCCESS, $exitCode);
        $this->assertSame(CommunicationSend::STATUS_QUEUED, $send1->fresh()->status);
        $this->assertSame(CommunicationSend::STATUS_QUEUED, $send2->fresh()->status);
        $this->assertSame(CommunicationSend::STATUS_SENDING, $notStale->fresh()->status, 'Una riga non stale non deve mai essere toccata.');
    }

    public function test_command_reports_nothing_to_do_when_no_stale_rows_exist(): void
    {
        $command = app(CommunicationReviewStaleSends::class);
        $command->setLaravel(app());
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(SymfonyCommand::SUCCESS, $exitCode);
        $this->assertStringContainsString('Nessuna riga', $tester->getDisplay());
    }

    public function test_the_command_is_never_registered_in_the_scheduler(): void
    {
        // Vincolo esplicito della missione: nessuno scheduler che possa
        // condurre a un invio automatico. Verifica statica sul file reale.
        $schedule = file_get_contents(base_path('routes/console.php'));
        $this->assertStringNotContainsString('communication:review-stale-sends', $schedule);
    }
}
