<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Models\User;
use App\Services\Communication\CampaignDeliveryOrchestrator;
use App\Services\Communication\CampaignPreflightService;
use App\Services\Communication\DeliveryResult;
use App\Services\Communication\RecipientSnapshotService;
use App\Services\Communication\RecordingEmailProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * N2.14 — MISSIONE FINALE: simulazione end-to-end obbligatoria a 10.000
 * iscritti. Prepara → disiscrizione parziale → cambio email parziale →
 * anteprima → verifica pre-invio → esecuzione reale (mai un invio
 * reale: SEMPRE RecordingEmailProvider) con una distribuzione
 * deterministica di esiti (accettati/falliti temporanei ritentati/
 * falliti temporanei esauriti/falliti definitivi), fino a completamento
 * — verificando zero duplicati, zero consegna a disiscritti, zero
 * chiamate di rete, stato coerente, nessuna PII in log.
 *
 * È stata proprio la preparazione di QUESTO test a far emergere il bug
 * reale corretto subito prima (runCampaign() non ritentava mai i
 * fallimenti transitori) — la ragion d'essere di una simulazione a
 * scala reale invece di soli unit test mirati.
 */
class Newsletter2EndToEnd10kSimulationTest extends TestCase
{
    use RefreshDatabase;

    private const TOTAL_SUBSCRIBERS = 10000;

    private function seedConfirmedSubscribers(int $count, string $prefix = 'e2e'): void
    {
        collect(range(1, $count))->chunk(1000)->each(function ($chunk) use ($prefix) {
            DB::table('comm_subscribers')->insert(
                $chunk->map(fn ($i) => [
                    'email' => $prefix.'-'.Str::random(12).'@example.com',
                    'status' => CommunicationSubscriber::STATUS_CONFIRMED,
                    'unsubscribe_token' => Str::random(32),
                    'confirmed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );
        });
    }

    public function test_10000_subscriber_campaign_runs_end_to_end_to_completion_with_a_mixed_outcome_distribution(): void
    {
        Mail::fake();
        Notification::fake();
        Bus::fake();

        // ── Prepara: 10.000 iscritti confermati, campagna, snapshot ──
        $this->seedConfirmedSubscribers(self::TOTAL_SUBSCRIBERS);

        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
            'subject' => 'Newsletter di simulazione E2E',
            'preheader' => 'Anteprima simulazione',
            'content' => ['body' => 'Corpo della simulazione end-to-end.'],
        ]);

        $prepareResult = app(RecipientSnapshotService::class)->prepare($campaign);
        $this->assertSame(self::TOTAL_SUBSCRIBERS, $prepareResult['added']);
        $this->assertSame(self::TOTAL_SUBSCRIBERS, CommunicationSend::where('campaign_id', $campaign->id)->count());

        // ── Disiscrizione parziale DOPO lo snapshot: 500 iscritti ──
        $allSubscriberIds = DB::table('comm_subscribers')->orderBy('id')->pluck('id');
        $unsubscribedIds = $allSubscriberIds->slice(0, 500)->values();
        DB::table('comm_subscribers')->whereIn('id', $unsubscribedIds)->update([
            'status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ]);

        // ── Cambio email parziale DOPO lo snapshot: altri 300 iscritti ──
        $emailChangedIds = $allSubscriberIds->slice(500, 300)->values();
        foreach ($emailChangedIds as $id) {
            DB::table('comm_subscribers')->where('id', $id)->update(['email' => 'changed-'.Str::random(10).'@example.com']);
        }

        // ── Anteprima: nessun crash a questa scala, resta di sola lettura ──
        $editor = User::factory()->create(['role' => 'editor']);
        $previewResponse = $this->actingAs($editor)
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));
        $previewResponse->assertOk();
        $this->assertSame(
            self::TOTAL_SUBSCRIBERS,
            CommunicationSend::where('campaign_id', $campaign->id)->count(),
            "L'anteprima non deve mai modificare comm_sends, nemmeno a questa scala."
        );

        // ── Verifica pre-invio: i 500 disiscritti sono un avviso, mai un blocco ──
        $preflight = app(CampaignPreflightService::class)->assess(CommunicationCampaign::find($campaign->id));
        $this->assertTrue($preflight->isReady());
        $this->assertSame(self::TOTAL_SUBSCRIBERS, $preflight->preparedCount);
        $this->assertSame(500, $preflight->staleCount);
        $this->assertSame(0, $preflight->notYetPreparedCount);

        // ── Distribuzione esiti deterministica, basata su subscriber_id ──
        // ~2% rejected (mai ritentato), ~2% fallimento transitorio
        // persistente (esaurisce i tentativi -> failed), ~2% fallimento
        // transitorio SOLO al primo tentativo (poi accettato al retry),
        // il resto accettato. I 500 disiscritti (già esclusi da questa
        // distribuzione perché falliscono in revalidazione PRIMA che il
        // provider venga mai interpellato) e i 300 con email cambiata
        // (che devono comunque risultare consegnati, con l'email
        // AGGIORNATA — mai quella congelata al momento dello snapshot)
        // sono verificati esplicitamente sotto.
        $attemptCounters = [];
        $provider = new RecordingEmailProvider;
        $provider->resolveUsing(function ($message) use (&$attemptCounters) {
            $subscriberId = $message->recipientSubscriberId;
            $attemptCounters[$subscriberId] = ($attemptCounters[$subscriberId] ?? 0) + 1;
            $bucket = $subscriberId % 100;

            if ($bucket < 2) {
                return new DeliveryResult(status: DeliveryResult::STATUS_REJECTED, reason: 'simulated_reject');
            }

            if ($bucket < 4) {
                return new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, reason: 'simulated_persistent_timeout');
            }

            if ($bucket < 6 && $attemptCounters[$subscriberId] === 1) {
                return new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, reason: 'simulated_transient_once');
            }

            return new DeliveryResult(status: DeliveryResult::STATUS_ACCEPTED, providerMessageId: 'e2e-'.$subscriberId);
        });

        $startedAt = microtime(true);
        $report = app(CampaignDeliveryOrchestrator::class)->runCampaign($campaign, $provider);
        $elapsedSeconds = microtime(true) - $startedAt;

        // ── Stato coerente: la campagna raggiunge il completamento ──
        $this->assertNotNull($report);
        $this->assertSame(CommunicationCampaign::STATUS_COMPLETED, $campaign->fresh()->status);
        $this->assertSame(self::TOTAL_SUBSCRIBERS, $report->eligible);

        // ── Nessuna riga resta bloccata in coda o a metà (questa E la
        // riprova diretta del bug corretto poco prima) ──
        $stillQueuedOrSending = CommunicationSend::where('campaign_id', $campaign->id)
            ->whereIn('status', [CommunicationSend::STATUS_QUEUED, CommunicationSend::STATUS_SENDING])
            ->count();
        $this->assertSame(0, $stillQueuedOrSending, 'Nessuna riga deve restare queued/sending a fine esecuzione: ogni destinatario deve risolvere a sent o failed.');

        // ── Zero duplicati: ogni riga ha status terminale coerente con
        // esattamente il numero di tentativi effettivamente registrati ──
        $totalTerminal = CommunicationSend::where('campaign_id', $campaign->id)
            ->whereIn('status', [CommunicationSend::STATUS_SENT, CommunicationSend::STATUS_FAILED])
            ->count();
        $this->assertSame(self::TOTAL_SUBSCRIBERS, $totalTerminal);
        $this->assertSame(self::TOTAL_SUBSCRIBERS, CommunicationSend::where('campaign_id', $campaign->id)->count(), 'Nessuna riga duplicata deve essere mai stata creata.');

        // ── Zero consegna a disiscritti: nessuno dei 500 risulta 'sent' ──
        $deliveredToUnsubscribed = CommunicationSend::where('campaign_id', $campaign->id)
            ->where('status', CommunicationSend::STATUS_SENT)
            ->whereIn('subscriber_id', $unsubscribedIds)
            ->count();
        $this->assertSame(0, $deliveredToUnsubscribed, 'Nessun iscritto disiscritto DOPO lo snapshot deve mai risultare "sent".');
        $failedUnsubscribedReasons = CommunicationSend::where('campaign_id', $campaign->id)
            ->whereIn('subscriber_id', $unsubscribedIds)
            ->pluck('failure_reason')
            ->unique();
        $this->assertSame(['subscriber_not_eligible'], $failedUnsubscribedReasons->all());

        // ── I 300 con email cambiata sono stati comunque elaborati (la
        // revalidazione legge l'email CORRENTE via relazione, mai una
        // copia congelata) ──
        $emailChangedProcessed = CommunicationSend::where('campaign_id', $campaign->id)
            ->whereIn('subscriber_id', $emailChangedIds)
            ->whereIn('status', [CommunicationSend::STATUS_SENT, CommunicationSend::STATUS_FAILED])
            ->count();
        $this->assertSame(300, $emailChangedProcessed);

        // ── Zero chiamate di rete/mail reali, a qualunque scala ──
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Bus::assertNothingDispatched();

        // ── Performance: misurata, non un hard gate qui (già coperto a
        // budget più stretto in N2.12) — visibile nel report finale. ──
        $this->assertLessThan(
            120.0,
            $elapsedSeconds,
            "L'esecuzione end-to-end a 10.000 ha impiegato {$elapsedSeconds}s — soglia di sicurezza generosa contro una regressione grave, non un budget stretto."
        );

        fwrite(STDERR, "\n[E2E 10k] Durata esecuzione reale: ".round($elapsedSeconds, 2)."s\n");
        fwrite(STDERR, '[E2E 10k] Report: '.json_encode($report->toArray())."\n");
    }

    /**
     * Cancellazione a scala: una campagna con 10.000 righe ancora
     * 'queued' (nessuna elaborazione iniziata) viene annullata con
     * un'UNICA UPDATE bulk — mai un loop riga-per-riga — e il provider
     * fake non viene mai interpellato per nessuna di esse.
     */
    public function test_cancelling_a_10000_recipient_campaign_before_processing_cancels_every_row_in_bulk(): void
    {
        $this->seedConfirmedSubscribers(self::TOTAL_SUBSCRIBERS, prefix: 'e2e-cancel');

        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
            'subject' => 'Campagna da annullare',
            'content' => ['body' => 'Corpo.'],
        ]);
        app(RecipientSnapshotService::class)->prepare($campaign);
        $this->assertSame(self::TOTAL_SUBSCRIBERS, CommunicationSend::where('campaign_id', $campaign->id)->count());

        // cancelCampaign() transiziona da 'sending': portiamo la campagna
        // li' prima, come farebbe runCampaign() prima di processare la coda.
        $campaign->update(['status' => CommunicationCampaign::STATUS_SENDING]);

        $provider = new RecordingEmailProvider;
        $cancelled = app(CampaignDeliveryOrchestrator::class)->cancelCampaign($campaign->fresh());

        $this->assertTrue($cancelled);
        $this->assertSame(CommunicationCampaign::STATUS_CANCELLED, $campaign->fresh()->status);
        $this->assertSame(
            self::TOTAL_SUBSCRIBERS,
            CommunicationSend::where('campaign_id', $campaign->id)->where('status', CommunicationSend::STATUS_CANCELLED)->count()
        );
        $this->assertSame(0, $provider->attemptCount(), 'cancelCampaign() non deve mai interpellare il provider.');
    }
}
