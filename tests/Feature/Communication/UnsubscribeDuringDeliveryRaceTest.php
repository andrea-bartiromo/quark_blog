<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\CampaignDeliveryOrchestrator;
use App\Services\Communication\DeliveryResult;
use App\Services\Communication\RecordingEmailProvider;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Red-team pre-merge (FASE 3): "subscriber si disiscrive mentre un
 * worker sta processando" — prova con due processi reali su MariaDB.
 *
 * A differenza del finding corretto in persistResult() (stato della
 * RIGA COMM_SENDS cambiato tra claim e persistenza), qui la domanda è
 * diversa: cosa succede se il CONSENSO dell'iscritto cambia DOPO che
 * revalidate() lo ha già letto come 'confirmed', ma PRIMA che il
 * provider abbia terminato la chiamata? revalidate() legge fresco da
 * DB PRIMA del rendering/della chiamata al provider — non c'è un
 * secondo controllo DOPO. Questo test dimostra che la finestra esiste
 * davvero (non è solo un'ipotesi) e la documenta come rischio residuo
 * accettato, non come bug: ri-verificare il consenso DOPO che il
 * provider ha già accettato non avrebbe senso (l'invio, con un
 * provider reale, sarebbe già irreversibile), e tenere un lock DB
 * aperto per tutta la durata di una chiamata di rete esterna sarebbe un
 * anti-pattern peggiore del rischio stesso. Ogni sistema di invio email
 * bulk reale ha la stessa finestra strutturale.
 */
class UnsubscribeDuringDeliveryRaceTest extends TestCase
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

    public function test_unsubscribing_after_revalidation_but_before_the_provider_responds_still_delivers_this_one_message(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl non disponibile in questo ambiente.');
        }
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Richiede MariaDB reale.');
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

        $resultsDir = sys_get_temp_dir().'/kairus-unsub-race-'.uniqid('', true);
        mkdir($resultsDir, 0777, true);
        $revalidatedMarker = $resultsDir.'/revalidated';
        $unsubscribedMarker = $resultsDir.'/unsubscribed';

        DB::disconnect();
        $pids = [];

        $pidWorker = pcntl_fork();
        if ($pidWorker === -1) {
            $this->fail('pcntl_fork() non riuscita.');
        }
        if ($pidWorker === 0) {
            $outcome = 'error:unknown';
            try {
                DB::purge();
                DB::reconnect();

                $freshSend = CommunicationSend::find($send->id);
                $provider = new RecordingEmailProvider;
                $provider->resolveUsing(function () use ($revalidatedMarker, $unsubscribedMarker) {
                    // A questo punto revalidate() ha già letto (e
                    // approvato) lo stato dell'iscritto, prima che questa
                    // closure fosse mai invocata dal provider.
                    touch($revalidatedMarker);
                    $waited = 0;
                    while (! file_exists($unsubscribedMarker) && $waited < 5000) {
                        usleep(10000);
                        $waited += 10;
                    }

                    return new DeliveryResult(status: DeliveryResult::STATUS_ACCEPTED, providerMessageId: 'unsub-race-ok');
                });

                $result = app(CampaignDeliveryOrchestrator::class)->processSend($freshSend, $provider);
                $outcome = 'outcome:'.$result->outcome.':attempts:'.$provider->attemptCount();
            } catch (\Throwable $e) {
                $outcome = 'exception:'.get_class($e).':'.$e->getMessage();
            }
            file_put_contents($resultsDir.'/worker', $outcome);
            exit(0);
        }
        $pids[] = $pidWorker;

        $pidUnsubscribe = pcntl_fork();
        if ($pidUnsubscribe === -1) {
            $this->fail('pcntl_fork() non riuscita.');
        }
        if ($pidUnsubscribe === 0) {
            try {
                DB::purge();
                DB::reconnect();
                $waited = 0;
                while (! file_exists($revalidatedMarker) && $waited < 5000) {
                    usleep(10000);
                    $waited += 10;
                }
                // Stessa UPDATE idempotente condizionata usata da
                // CommunicationUnsubscribeController::unsubscribe().
                DB::table('comm_subscribers')
                    ->where('id', $subscriber->id)
                    ->where('status', '!=', CommunicationSubscriber::STATUS_UNSUBSCRIBED)
                    ->update(['status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED, 'unsubscribed_at' => now()]);
            } finally {
                touch($unsubscribedMarker);
            }
            exit(0);
        }
        $pids[] = $pidUnsubscribe;

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::reconnect();

        $workerOutcome = file_exists($resultsDir.'/worker') ? trim(file_get_contents($resultsDir.'/worker')) : 'MISSING';
        foreach ([$revalidatedMarker, $unsubscribedMarker, $resultsDir.'/worker'] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        rmdir($resultsDir);

        // La finestra esiste davvero: il messaggio GIÀ in volo al momento
        // della disiscrizione viene comunque consegnato (nessun secondo
        // controllo di consenso dopo la chiamata al provider) — rischio
        // residuo accettato e documentato, non un bug: nessuna chiamata
        // FUTURA a questo iscritto sarà mai possibile (lo stato
        // unsubscribed è persistito correttamente), solo QUESTO singolo
        // messaggio già in corso non viene bloccato retroattivamente.
        $this->assertSame('outcome:sent:attempts:1', $workerOutcome);
        $subscriberAfter = CommunicationSubscriber::find($subscriber->id);
        $this->assertSame(CommunicationSubscriber::STATUS_UNSUBSCRIBED, $subscriberAfter->status);
        $sendAfter = CommunicationSend::find($send->id);
        $this->assertSame(CommunicationSend::STATUS_SENT, $sendAfter->status);
    }
}
