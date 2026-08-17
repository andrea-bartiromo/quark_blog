<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\CampaignDeliveryOrchestrator;
use App\Services\Communication\DeliveryResult;
use App\Services\Communication\RecordingEmailProvider;
use App\Services\Communication\StaleSendRecoveryService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Red-team pre-merge (FASE 3): riproduce con DUE PROCESSI REALI su
 * MariaDB reale lo scenario esplicitamente richiesto — "stale recovery
 * mentre un worker originale è ancora vivo" — che ha portato al bug
 * reale corretto in CampaignDeliveryOrchestrator::persistResult()
 * (valore di ritorno di transition() ignorato, un esito "sent" pulito
 * veniva riportato anche quando la persistenza era silenziosamente
 * fallita, lasciando la riga disponibile per un secondo, reale, invio
 * allo stesso destinatario).
 *
 * Processo A (worker "originale"): reclama la riga, poi — nella
 * finestra tra il claim e la persistenza dell'esito, dentro il
 * resolver del provider fake — segnala di essere pronto e ATTENDE che
 * il processo B abbia completato la recovery, prima di restituire un
 * esito "accepted" e proseguire verso persistResult().
 *
 * Processo B ("operatore" che esegue una recovery, erroneamente, su una
 * riga NON realmente abbandonata): attende il segnale di A, poi chiama
 * StaleSendRecoveryService::release() con soglia --minutes=0 (qualunque
 * riga 'sending' committata anche un istante prima appare "stale" con
 * questa soglia aggressiva — non serve un vero crash, basta il
 * giudizio errato di un operatore).
 */
class StaleSendRecoveryLiveWorkerRaceTest extends TestCase
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

    public function test_a_stale_recovery_racing_a_live_worker_never_results_in_a_silent_double_delivery(): void
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

        $resultsDir = sys_get_temp_dir().'/kairus-stale-race-'.uniqid('', true);
        mkdir($resultsDir, 0777, true);
        $claimedMarker = $resultsDir.'/claimed';
        $releasedMarker = $resultsDir.'/released';

        DB::disconnect();

        $pids = [];

        // ── Processo A: worker "originale" ──
        $pidA = pcntl_fork();
        if ($pidA === -1) {
            $this->fail('pcntl_fork() non riuscita (worker).');
        }
        if ($pidA === 0) {
            $outcome = 'error:unknown';
            try {
                DB::purge();
                DB::reconnect();

                $freshSend = CommunicationSend::find($send->id);
                $provider = new RecordingEmailProvider;
                $provider->resolveUsing(function () use ($claimedMarker, $releasedMarker) {
                    touch($claimedMarker);

                    $waited = 0;
                    while (! file_exists($releasedMarker) && $waited < 5000) {
                        usleep(10000);
                        $waited += 10;
                    }
                    if (! file_exists($releasedMarker)) {
                        throw new \RuntimeException('timeout in attesa della recovery concorrente');
                    }

                    return new DeliveryResult(status: DeliveryResult::STATUS_ACCEPTED, providerMessageId: 'race-test-ok');
                });

                app(CampaignDeliveryOrchestrator::class)->processSend($freshSend, $provider);
                $outcome = 'no_exception:'.$provider->attemptCount();
            } catch (\Throwable $e) {
                $outcome = 'exception:'.get_class($e).':'.$provider->attemptCount().':'.$e->getMessage();
            }
            file_put_contents($resultsDir.'/worker', $outcome);
            exit(0);
        }
        $pids[] = $pidA;

        // ── Processo B: "operatore" che rilascia una riga giudicata (erroneamente) stale ──
        $pidB = pcntl_fork();
        if ($pidB === -1) {
            $this->fail('pcntl_fork() non riuscita (operatore recovery).');
        }
        if ($pidB === 0) {
            $outcome = 'error:unknown';
            try {
                DB::purge();
                DB::reconnect();

                $waited = 0;
                while (! file_exists($claimedMarker) && $waited < 5000) {
                    usleep(10000);
                    $waited += 10;
                }
                if (! file_exists($claimedMarker)) {
                    throw new \RuntimeException('timeout in attesa del claim del worker');
                }

                // release() non dipende dalla finestra temporale di
                // findStale() — è lo stesso identico codice che gira sia
                // che l'operatore l'abbia appena vista in un run di
                // reportistica di qualche istante fa, sia di 30 minuti
                // fa: qui la testiamo direttamente sul suo comportamento
                // reale, senza legare il test alla granularità del
                // timestamp DB (irrilevante per il bug in questione).
                $service = app(StaleSendRecoveryService::class);
                $target = CommunicationSend::find($send->id);
                $released = $target ? $service->release($target) : false;

                $outcome = 'released:'.($released ? '1' : '0').':found_stale:'.($target ? '1' : '0');
            } catch (\Throwable $e) {
                $outcome = 'exception:'.get_class($e).':'.$e->getMessage();
            }
            touch($releasedMarker);
            file_put_contents($resultsDir.'/operator', $outcome);
            exit(0);
        }
        $pids[] = $pidB;

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        $workerOutcome = file_exists($resultsDir.'/worker') ? trim(file_get_contents($resultsDir.'/worker')) : 'MISSING';
        $operatorOutcome = file_exists($resultsDir.'/operator') ? trim(file_get_contents($resultsDir.'/operator')) : 'MISSING';

        foreach ([$claimedMarker, $releasedMarker, $resultsDir.'/worker', $resultsDir.'/operator'] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        rmdir($resultsDir);

        // ── L'operatore ha davvero trovato e rilasciato la riga (era
        // 'sending' con soglia --minutes=0, indipendentemente da quanto
        // recentemente il worker l'avesse reclamata) ──
        $this->assertStringStartsWith('released:1:found_stale:1', $operatorOutcome, "L'operatore doveva trovare e rilasciare la riga: {$operatorOutcome}");

        // ── Il worker DEVE aver sollevato l'eccezione del fix, mai un
        // esito pulito silenzioso, e deve aver interpellato il provider
        // esattamente una volta (la chiamata reale è già avvenuta, il
        // fix la rende visibile invece di fingerla mai successa) ──
        $this->assertStringStartsWith('exception:RuntimeException:1:', $workerOutcome, "Il worker doveva sollevare l'eccezione del fix invece di riportare un esito pulito: {$workerOutcome}");
        $this->assertStringContainsString('perso', $workerOutcome);

        // ── Stato finale: la riga resta 'queued' (impostata
        // dall'operatore) — MAI 'sent', che significherebbe che il
        // fallimento di persistenza è stato mascherato da un successo
        // apparente mentre in realtà la riga resterebbe disponibile per
        // un secondo invio reale allo stesso destinatario. ──
        $fresh = CommunicationSend::find($send->id);
        $this->assertSame(CommunicationSend::STATUS_QUEUED, $fresh->status);
        $this->assertNotSame(CommunicationSend::STATUS_SENT, $fresh->status);
    }
}
