<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\CampaignDeliveryOrchestrator;
use App\Services\Communication\RecordingEmailProvider;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prova di concorrenza REALE per il claim atomico queued->sending
 * dell'orchestratore di delivery: due processi PHP separati (worker fake
 * concorrenti, FASE 8 della missione) tentano di elaborare la STESSA
 * riga comm_sends nello stesso istante, contro una MariaDB reale.
 *
 * Ogni processo figlio usa il proprio RecordingEmailProvider (memoria di
 * processo, non condivisa) — l'osservabile condiviso è solo lo stato
 * finale persistito su comm_sends, letto dal processo padre dopo il
 * join.
 */
class CampaignDeliveryOrchestratorConcurrencyTest extends TestCase
{
    private ?int $campaignId = null;

    private ?int $sendId = null;

    private ?int $subscriberId = null;

    private ?int $senderProfileId = null;

    protected function tearDown(): void
    {
        if ($this->sendId !== null) {
            DB::table('comm_sends')->where('id', $this->sendId)->delete();
        }
        if ($this->campaignId !== null) {
            DB::table('comm_campaigns')->where('id', $this->campaignId)->delete();
        }
        if ($this->subscriberId !== null) {
            DB::table('comm_subscribers')->where('id', $this->subscriberId)->delete();
        }
        if ($this->senderProfileId !== null) {
            DB::table('comm_sender_profiles')->where('id', $this->senderProfileId)->delete();
        }

        parent::tearDown();
    }

    public function test_two_concurrent_fake_workers_never_both_deliver_the_same_send(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl non disponibile in questo ambiente — impossibile provare una vera concorrenza multi-processo.');
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Richiede una connessione MariaDB reale (SQLite non è prova sufficiente per la concorrenza).');
        }

        $senderProfile = CommunicationSenderProfile::factory()->create();
        $this->senderProfileId = $senderProfile->id;

        $campaign = CommunicationCampaign::factory()->create([
            'status' => CommunicationCampaign::STATUS_SENDING,
            'sender_profile_id' => $senderProfile->id,
        ]);
        $this->campaignId = $campaign->id;

        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        $this->subscriberId = $subscriber->id;

        $send = CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subscriber->id,
            'status' => CommunicationSend::STATUS_QUEUED,
            'queued_at' => now(),
        ]);
        $this->sendId = $send->id;

        $resultsDir = sys_get_temp_dir().'/kairus-delivery-race-'.uniqid('', true);
        mkdir($resultsDir, 0777, true);

        DB::disconnect();

        $pids = [];

        for ($worker = 0; $worker < 2; $worker++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('pcntl_fork() non riuscita.');
            }

            if ($pid === 0) {
                $outcome = 'error';

                try {
                    DB::purge();
                    DB::reconnect();

                    $freshSend = CommunicationSend::find($send->id);
                    $provider = new RecordingEmailProvider;
                    $result = app(CampaignDeliveryOrchestrator::class)->processSend($freshSend, $provider);

                    $outcome = $result->outcome.':'.$provider->attemptCount();
                } catch (\Throwable $e) {
                    $outcome = 'error:'.$e->getMessage();
                }

                file_put_contents($resultsDir.'/'.getmypid(), $outcome);

                exit(0);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        $outcomeFiles = glob($resultsDir.'/*');
        $outcomes = array_map(fn (string $path) => trim(file_get_contents($path)), $outcomeFiles);
        foreach ($outcomeFiles as $path) {
            unlink($path);
        }
        rmdir($resultsDir);

        $this->assertCount(2, $outcomes);

        foreach ($outcomes as $outcome) {
            $this->assertStringStartsNotWith('error', $outcome, "Un processo figlio ha sollevato un'eccezione inattesa: {$outcome}");
        }

        $sentCount = count(array_filter($outcomes, fn (string $o) => str_starts_with($o, 'sent:1')));
        $skippedCount = count(array_filter($outcomes, fn (string $o) => str_starts_with($o, 'skipped:0')));

        $this->assertSame(1, $sentCount, 'Esattamente un processo doveva consegnare (provider chiamato una sola volta): '.implode(', ', $outcomes));
        $this->assertSame(1, $skippedCount, 'Esattamente un processo doveva essere respinto dal claim, senza mai chiamare il provider: '.implode(', ', $outcomes));

        $fresh = CommunicationSend::find($send->id);
        $this->assertSame(CommunicationSend::STATUS_SENT, $fresh->status);
        $this->assertSame(1, $fresh->attempts, 'Un solo tentativo deve risultare persistito, indipendentemente da quanti worker hanno provato a reclamare la riga.');
    }
}
