<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\RecipientSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Red team dedicato a RecipientSnapshotService: scala, sequenze di run
 * ripetuti, cascata su cancellazione, cambio email, nuovi iscritti/
 * conferme tra un run e l'altro. Complementare a RecipientSnapshotTest.php
 * (che copre eleggibilità/autorizzazione/UX di base).
 *
 * Nota sulla concorrenza reale: PHPUnit è sincrono a singolo processo — non
 * può riprodurre una vera race condition multi-processo. Il test
 * "sequenziale" qui sotto verifica che il vincolo unique(campaign_id,
 * subscriber_id) a livello DB regga sotto RIPETUTE chiamate identiche
 * (la stessa proprietà che protegge anche da una vera race: la seconda
 * scrittura concorrente vede lo stesso vincolo e viene ignorata allo
 * stesso modo) — non una simulazione di thread paralleli.
 */
class RecipientSnapshotRaceAndScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_subscribers_returns_all_zero_counts(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create();

        $result = app(RecipientSnapshotService::class)->prepare($campaign);

        $this->assertSame(['added' => 0, 'already_present' => 0, 'eligible_total' => 0], $result);
    }

    public function test_1000_subscribers_does_not_hit_a_query_placeholder_limit(): void
    {
        // Il controllo "già presenti" usa whereIn() su tutti gli id
        // eleggibili: a scala sufficientemente grande potrebbe avvicinarsi
        // al limite di parametri di alcuni driver DB (SQLite di default
        // intorno a ~32k in versioni moderne). 1000 resta ben sotto
        // qualunque limite realistico (SQLite o MySQL/Postgres in
        // produzione), verificato qui come guardia di scala.
        $campaign = CommunicationCampaign::factory()->draft()->create();

        DB::table('comm_subscribers')->insert(
            collect(range(1, 1000))->map(fn ($i) => [
                'email' => "sub{$i}@example.com",
                'status' => 'confirmed',
                'unsubscribe_token' => Str::random(32),
                'confirmed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );

        $result = app(RecipientSnapshotService::class)->prepare($campaign);

        $this->assertSame(1000, $result['added']);
        $this->assertSame(1000, CommunicationSend::where('campaign_id', $campaign->id)->count());
    }

    public function test_10000_subscribers_completes_without_a_query_placeholder_limit(): void
    {
        // Stessa guardia di scala di test_1000_subscribers_..., a un
        // ordine di grandezza oltre: il chunk(500) sull'insert e le due
        // pluck() (eligibili/già presenti) restano l'unico punto dove un
        // volume elevato di iscritti potrebbe avvicinarsi a un limite di
        // parametri driver — verificato qui end-to-end, non solo stimato.
        $campaign = CommunicationCampaign::factory()->draft()->create();

        DB::table('comm_subscribers')->insert(
            collect(range(1, 10000))->map(fn ($i) => [
                'email' => "scale-sub{$i}@example.com",
                'status' => 'confirmed',
                'unsubscribe_token' => Str::random(32),
                'confirmed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );

        $result = app(RecipientSnapshotService::class)->prepare($campaign);

        $this->assertSame(10000, $result['added']);
        $this->assertSame(10000, CommunicationSend::where('campaign_id', $campaign->id)->count());

        // Rerun idempotente alla stessa scala: nessun nuovo added, nessun
        // duplicato, nessun timeout/errore di query al secondo passaggio.
        $second = app(RecipientSnapshotService::class)->prepare($campaign);
        $this->assertSame(0, $second['added']);
        $this->assertSame(10000, $second['already_present']);
        $this->assertSame(10000, CommunicationSend::where('campaign_id', $campaign->id)->count());
    }

    public function test_editing_campaign_content_after_a_snapshot_does_not_touch_comm_sends(): void
    {
        // comm_sends non referenzia né copia il contenuto della campagna
        // (destinatari e contenuto sono deliberatamente concetti separati,
        // vedi il docblock di RecipientSnapshotService): modificare titolo/
        // oggetto/corpo/stato dopo lo snapshot non deve mai alterare le
        // righe già preparate.
        $campaign = CommunicationCampaign::factory()->draft()->create(['subject' => 'Oggetto originale']);
        CommunicationSubscriber::factory()->confirmed()->count(3)->create();

        app(RecipientSnapshotService::class)->prepare($campaign);
        $sendIds = CommunicationSend::where('campaign_id', $campaign->id)->pluck('id')->sort()->values();

        $campaign->update(['subject' => 'Oggetto modificato', 'title' => 'Titolo modificato', 'content' => ['body' => 'Corpo nuovo']]);

        $afterEditIds = CommunicationSend::where('campaign_id', $campaign->id)->pluck('id')->sort()->values();
        $this->assertSame($sendIds->all(), $afterEditIds->all());
        $this->assertSame(3, CommunicationSend::where('campaign_id', $campaign->id)->count());

        // Rieseguire "Prepara destinatari" dopo la modifica resta un no-op
        // sugli stessi iscritti: la modifica del contenuto non "invalida"
        // artificialmente lo snapshot esistente.
        $result = app(RecipientSnapshotService::class)->prepare($campaign->fresh());
        $this->assertSame(0, $result['added']);
        $this->assertSame(3, $result['already_present']);
    }

    /**
     * Una campagna può passare da 'scheduled' a 'sending' dopo che lo
     * snapshot è già stato preparato (es. un futuro job di invio che
     * transiziona lo stato all'avvio) — canPrepare() deve rifiutare un
     * secondo "Prepara destinatari" da quel momento in poi, senza toccare
     * le righe già create in stato precedente.
     */
    public function test_campaign_transitioning_to_sending_after_a_snapshot_blocks_further_preparation(): void
    {
        $campaign = CommunicationCampaign::factory()->scheduled()->create();
        CommunicationSubscriber::factory()->confirmed()->count(2)->create();

        app(RecipientSnapshotService::class)->prepare($campaign);
        $this->assertSame(2, CommunicationSend::where('campaign_id', $campaign->id)->count());

        $campaign->update(['status' => CommunicationCampaign::STATUS_SENDING, 'sending_started_at' => now()]);
        CommunicationSubscriber::factory()->confirmed()->create();

        $service = app(RecipientSnapshotService::class);
        $this->assertFalse($service->canPrepare($campaign->fresh()));
        $this->expectException(\RuntimeException::class);
        $service->prepare($campaign->fresh());
    }

    public function test_five_consecutive_runs_never_duplicate_or_error(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create();
        CommunicationSubscriber::factory()->confirmed()->count(10)->create();

        $service = app(RecipientSnapshotService::class);
        $added = [];

        for ($i = 0; $i < 5; $i++) {
            $added[] = $service->prepare($campaign)['added'];
        }

        $this->assertSame([10, 0, 0, 0, 0], $added);
        $this->assertSame(10, CommunicationSend::where('campaign_id', $campaign->id)->count());
    }

    public function test_deleting_a_subscriber_after_snapshot_cascades_the_send_row(): void
    {
        // comm_sends.subscriber_id ha cascadeOnDelete() dalla sua migration
        // originale: verificato qui perché è un comportamento silenzioso,
        // facile da rompere per errore in una futura migration.
        $campaign = CommunicationCampaign::factory()->draft()->create();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        app(RecipientSnapshotService::class)->prepare($campaign);
        $this->assertSame(1, CommunicationSend::where('campaign_id', $campaign->id)->count());

        $subscriber->delete();

        $this->assertSame(0, CommunicationSend::where('campaign_id', $campaign->id)->count());
    }

    public function test_changing_subscriber_email_after_snapshot_does_not_orphan_or_duplicate_the_send_row(): void
    {
        // comm_sends non denormalizza l'email: la riga resta legata al
        // subscriber_id, l'email letta a valle (relazione ->subscriber)
        // riflette sempre lo stato corrente, mai una copia congelata.
        $campaign = CommunicationCampaign::factory()->draft()->create();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'prima@example.com']);

        app(RecipientSnapshotService::class)->prepare($campaign);
        $sendId = CommunicationSend::where('campaign_id', $campaign->id)->value('id');

        $subscriber->update(['email' => 'dopo@example.com']);

        $this->assertSame(1, CommunicationSend::where('campaign_id', $campaign->id)->count());
        $this->assertSame($sendId, CommunicationSend::where('campaign_id', $campaign->id)->value('id'));
        $this->assertSame('dopo@example.com', CommunicationSend::find($sendId)->subscriber->email);
    }

    public function test_a_new_subscriber_confirmed_between_two_runs_is_picked_up_without_touching_existing_rows(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create();
        CommunicationSubscriber::factory()->confirmed()->count(3)->create();

        $service = app(RecipientSnapshotService::class);
        $first = $service->prepare($campaign);

        $newSubscriber = CommunicationSubscriber::factory()->confirmed()->create();
        $second = $service->prepare($campaign);

        $this->assertSame(3, $first['added']);
        $this->assertSame(1, $second['added']);
        $this->assertDatabaseHas('comm_sends', ['campaign_id' => $campaign->id, 'subscriber_id' => $newSubscriber->id]);
    }

    public function test_a_pending_subscriber_who_confirms_between_two_runs_is_picked_up_on_the_next_run(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create();
        $pending = CommunicationSubscriber::factory()->create(['status' => CommunicationSubscriber::STATUS_PENDING]);

        $service = app(RecipientSnapshotService::class);
        $first = $service->prepare($campaign);
        $this->assertSame(0, $first['added']);

        $pending->update(['status' => CommunicationSubscriber::STATUS_CONFIRMED, 'confirmed_at' => now()]);

        $second = $service->prepare($campaign);
        $this->assertSame(1, $second['added']);
    }
}
