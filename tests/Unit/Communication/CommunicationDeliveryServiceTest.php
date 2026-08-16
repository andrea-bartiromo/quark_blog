<?php

namespace Tests\Unit\Communication;

use App\Models\Article;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationSubscriber;
use App\Models\User;
use App\Services\Communication\CommunicationDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Motore di consegna idempotente generico — vedi il docblock di classe di
 * CommunicationDeliveryService per il vocabolario di garanzia (idempotent
 * job execution / atomic delivery claim / at-most-once local delivery
 * attempt / idempotent delivery record / exactly-once external email —
 * quest'ultima mai dichiarata).
 *
 * Copre esattamente gli scenari della Parte M della missione. La prova di
 * concorrenza REALE (due tentativi simultanei, processi separati, MariaDB)
 * vive in CommunicationDeliveryConcurrencyTest — qui la "concorrenza" è
 * verificata a livello sequenziale/di stato (stesso principio già usato in
 * RecipientSnapshotRaceAndScaleTest per comm_sends: il vincolo/claim regge
 * sotto chiamate ripetute, non è una simulazione di thread paralleli).
 */
class CommunicationDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CommunicationDeliveryService
    {
        return app(CommunicationDeliveryService::class);
    }

    private function confirmedSubscriber(): CommunicationSubscriber
    {
        return CommunicationSubscriber::factory()->confirmed()->create();
    }

    private function resource(): Article
    {
        return Article::create([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid('', true),
            'body' => 'Corpo di prova.',
            'category' => 'energia',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    // ── Identità di consegna ─────────────────────────────────────

    public function test_the_same_arguments_always_produce_the_same_delivery_key(): void
    {
        $keyA = CommunicationDelivery::computeDeliveryKey('email', 'digest', 5, 'App\\Models\\Article', 10, 'v1');
        $keyB = CommunicationDelivery::computeDeliveryKey('email', 'digest', 5, 'App\\Models\\Article', 10, 'v1');

        $this->assertSame($keyA, $keyB);
        $this->assertSame(64, strlen($keyA));
    }

    public function test_a_different_argument_produces_a_different_delivery_key(): void
    {
        $base = CommunicationDelivery::computeDeliveryKey('email', 'digest', 5, null, null, null);
        $differentSubscriber = CommunicationDelivery::computeDeliveryKey('email', 'digest', 6, null, null, null);
        $differentType = CommunicationDelivery::computeDeliveryKey('email', 'other', 5, null, null, null);
        $differentEvent = CommunicationDelivery::computeDeliveryKey('email', 'digest', 5, null, null, 'v2');

        $this->assertNotSame($base, $differentSubscriber);
        $this->assertNotSame($base, $differentType);
        $this->assertNotSame($base, $differentEvent);
    }

    public function test_null_notifiable_fields_never_collide_with_a_different_event_key(): void
    {
        // La trappola classica di un vincolo unique multi-colonna: due
        // notifiche senza risorsa (notifiable_type/id NULL) con event_key
        // diverso NON devono mai produrre la stessa identità solo perché
        // "NULL" appare in entrambe — verificato qui a livello di chiave,
        // non solo a livello di vincolo DB.
        $withoutEventA = CommunicationDelivery::computeDeliveryKey('email', 'digest', 5, null, null, null);
        $withoutEventB = CommunicationDelivery::computeDeliveryKey('email', 'digest', 5, null, null, 'settimana-34');

        $this->assertNotSame($withoutEventA, $withoutEventB);
    }

    // ── 1. Stessa delivery identity richiesta due volte → un solo record ──

    public function test_registering_the_same_delivery_twice_produces_only_one_row(): void
    {
        $subscriber = $this->confirmedSubscriber();
        $service = $this->service();

        $first = $service->registerDelivery('email', 'weekly_digest', $subscriber, null, '2026-W34');
        $second = $service->registerDelivery('email', 'weekly_digest', $subscriber, null, '2026-W34');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CommunicationDelivery::count());
    }

    public function test_registering_with_a_notifiable_resource_produces_a_stable_identity(): void
    {
        $subscriber = $this->confirmedSubscriber();
        $article = $this->resource();
        $service = $this->service();

        $first = $service->registerDelivery('email', 'resource_notice', $subscriber, $article);
        $second = $service->registerDelivery('email', 'resource_notice', $subscriber, $article);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CommunicationDelivery::count());
        $this->assertSame(Article::class, $first->notifiable_type);
        $this->assertSame($article->id, $first->notifiable_id);
    }

    public function test_two_different_subscribers_for_the_same_notification_produce_two_distinct_deliveries(): void
    {
        $subscriberA = $this->confirmedSubscriber();
        $subscriberB = $this->confirmedSubscriber();
        $service = $this->service();

        $service->registerDelivery('email', 'weekly_digest', $subscriberA, null, '2026-W34');
        $service->registerDelivery('email', 'weekly_digest', $subscriberB, null, '2026-W34');

        $this->assertSame(2, CommunicationDelivery::count());
    }

    // ── 2. Duplicate dispatch → niente duplicate claim ──

    public function test_a_second_dispatch_after_a_successful_send_reuses_the_same_record_and_never_resends(): void
    {
        Mail::fake();
        $subscriber = $this->confirmedSubscriber();
        $service = $this->service();

        $delivery = $service->registerDelivery('email', 'weekly_digest', $subscriber, null, '2026-W34');
        $sends = 0;
        $service->attemptSend($delivery, function () use (&$sends) {
            $sends++;
            Mail::raw('corpo', function ($m) {
                $m->to('destinatario@example.com')->subject('oggetto');
            });
        });

        // Simula un secondo dispatch duplicato: stessa identità, nuova chiamata a registerDelivery.
        $redispatched = $service->registerDelivery('email', 'weekly_digest', $subscriber, null, '2026-W34');
        $service->attemptSend($redispatched, function () use (&$sends) {
            $sends++;
        });

        $this->assertSame(1, $sends);
        $this->assertSame(1, CommunicationDelivery::count());
        $this->assertSame(CommunicationDelivery::STATUS_SENT, CommunicationDelivery::first()->status);
    }

    // ── 3. Concurrent claim → un solo winner (livello sequenziale — vedi anche il test MariaDB dedicato) ──

    public function test_only_one_of_two_sequential_claim_attempts_on_the_same_row_wins(): void
    {
        $subscriber = $this->confirmedSubscriber();
        $service = $this->service();
        $delivery = $service->registerDelivery('email', 'weekly_digest', $subscriber, null, '2026-W34');

        // Simula due "worker" che hanno già letto la stessa riga 'pending'
        // prima che uno dei due la reclami: il secondo tentativo deve
        // sempre trovare la transizione già avvenuta e non eseguire mai
        // $send() una seconda volta.
        $callsA = 0;
        $callsB = 0;

        $service->attemptSend($delivery, function () use (&$callsA) {
            $callsA++;
        });
        $service->attemptSend($delivery, function () use (&$callsB) {
            $callsB++;
        });

        $this->assertSame(1, $callsA);
        $this->assertSame(0, $callsB);
    }

    // ── 4. Retry dopo completed → nessun secondo send ──

    public function test_retrying_a_sent_delivery_through_attempt_send_never_sends_again(): void
    {
        $subscriber = $this->confirmedSubscriber();
        $service = $this->service();
        $delivery = $service->registerDelivery('email', 'weekly_digest', $subscriber, null, '2026-W34');

        $service->attemptSend($delivery, fn () => null);
        $this->assertSame(CommunicationDelivery::STATUS_SENT, $delivery->fresh()->status);

        $sends = 0;
        $result = $service->attemptSend($delivery, function () use (&$sends) {
            $sends++;
        });

        $this->assertSame(0, $sends);
        $this->assertSame(CommunicationDelivery::STATUS_SENT, $result->status);
    }

    // ── 5. Failure prima del side effect (subscriber non più eleggibile) → comportamento recuperabile ──

    public function test_a_delivery_that_fails_before_the_side_effect_can_be_explicitly_retried(): void
    {
        $subscriber = $this->confirmedSubscriber();
        $service = $this->service();
        $delivery = $service->registerDelivery('email', 'weekly_digest', $subscriber, null, '2026-W34');

        $subscriber->update(['status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED, 'unsubscribed_at' => now()]);

        $sends = 0;
        $service->attemptSend($delivery, function () use (&$sends) {
            $sends++;
        });

        $this->assertSame(0, $sends);
        $this->assertSame(CommunicationDelivery::STATUS_FAILED, $delivery->fresh()->status);
        $this->assertSame('subscriber_ineligible', $delivery->fresh()->failure_reason);

        // Un subscriber tornato eleggibile e un retry esplicito permettono un nuovo tentativo.
        $subscriber->update(['status' => CommunicationSubscriber::STATUS_CONFIRMED, 'unsubscribed_at' => null]);
        $retried = $service->retryFailed($delivery);
        $this->assertNotNull($retried);
        $this->assertSame(CommunicationDelivery::STATUS_PENDING, $retried->status);

        $service->attemptSend($retried, function () use (&$sends) {
            $sends++;
        });
        $this->assertSame(1, $sends);
    }

    // ── 6. Failure durante il send → stato coerente, ritentabile esplicitamente ──

    public function test_a_synchronous_exception_during_send_marks_the_delivery_failed_and_rethrows(): void
    {
        $subscriber = $this->confirmedSubscriber();
        $service = $this->service();
        $delivery = $service->registerDelivery('email', 'weekly_digest', $subscriber, null, '2026-W34');

        try {
            $service->attemptSend($delivery, function () {
                throw new \RuntimeException('SMTP connection refused');
            });
            $this->fail('Attesa una eccezione propagata.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('SMTP connection refused', $exception->getMessage());
        }

        $fresh = $delivery->fresh();
        $this->assertSame(CommunicationDelivery::STATUS_FAILED, $fresh->status);
        $this->assertSame('SMTP connection refused', $fresh->failure_reason);
        $this->assertSame(1, $fresh->attempts);
        $this->assertNotNull($fresh->failed_at);

        // Nessun secondo invio finché non viene esplicitamente ri-accodata.
        $sends = 0;
        $service->attemptSend($fresh, function () use (&$sends) {
            $sends++;
        });
        $this->assertSame(0, $sends);
    }

    // ── 7. Failure window dopo provider acceptance → rappresentata esplicitamente, mai dichiarata sicura ──

    public function test_a_delivery_stuck_in_sending_is_never_silently_resolved_by_this_service(): void
    {
        // Simula un processo morto DOPO il claim (pending->sending) ma
        // PRIMA che l'esito sia registrato — esattamente la finestra che
        // il servizio dichiara esplicitamente di non poter risolvere.
        $delivery = CommunicationDelivery::factory()->sending()->create();

        $sends = 0;
        $result = $this->service()->attemptSend($delivery, function () use (&$sends) {
            $sends++;
        });

        // Non è 'pending': attemptSend() non tenta mai un nuovo claim su una
        // riga che non è (più) 'pending' — la riga resta 'sending',
        // nessuna transizione automatica, nessun secondo invio.
        $this->assertSame(0, $sends);
        $this->assertSame(CommunicationDelivery::STATUS_SENDING, $result->status);
        $this->assertSame(CommunicationDelivery::STATUS_SENDING, $delivery->fresh()->status);
    }

    // ── 8. Subscriber non eleggibile → nessun send ──

    public function test_a_never_confirmed_subscriber_never_gets_sent_to(): void
    {
        $pending = CommunicationSubscriber::factory()->create(['status' => CommunicationSubscriber::STATUS_PENDING]);
        $service = $this->service();
        $delivery = $service->registerDelivery('email', 'weekly_digest', $pending, null, '2026-W34');

        $sends = 0;
        $service->attemptSend($delivery, function () use (&$sends) {
            $sends++;
        });

        $this->assertSame(0, $sends);
        $this->assertSame(CommunicationDelivery::STATUS_FAILED, $delivery->fresh()->status);
    }

    public function test_a_bounced_subscriber_never_gets_sent_to(): void
    {
        $bounced = CommunicationSubscriber::factory()->create(['status' => CommunicationSubscriber::STATUS_BOUNCED]);
        $service = $this->service();
        $delivery = $service->registerDelivery('email', 'weekly_digest', $bounced, null, '2026-W34');

        $sends = 0;
        $service->attemptSend($delivery, function () use (&$sends) {
            $sends++;
        });

        $this->assertSame(0, $sends);
    }

    // ── 9. Consenso revocato → nessun nuovo send (revocato dopo la registrazione, prima dell'invio) ──

    public function test_consent_revoked_after_registration_but_before_send_blocks_the_send(): void
    {
        $subscriber = $this->confirmedSubscriber();
        $service = $this->service();
        $delivery = $service->registerDelivery('email', 'weekly_digest', $subscriber, null, '2026-W34');

        // Il consenso viene revocato PRIMA che qualunque worker tenti l'invio.
        $subscriber->update(['status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED, 'unsubscribed_at' => now()]);

        $sends = 0;
        $result = $service->attemptSend($delivery, function () use (&$sends) {
            $sends++;
        });

        $this->assertSame(0, $sends);
        $this->assertSame(CommunicationDelivery::STATUS_FAILED, $result->status);
    }

    /**
     * Prova la SECONDA riverifica del consenso (subito dopo il claim
     * vincente, prima di invocare $send()) — non solo la prima (sopra),
     * che da sola non basta a coprire la finestra tra "il claim ha vinto"
     * e "$send() parte davvero". Un test sequenziale non può aspettare un
     * secondo processo reale per colpire quella finestra (vedi
     * CommunicationDeliveryConcurrencyTest per il perché), quindi qui la
     * si simula in modo deterministico con DB::listen(): appena la UPDATE
     * del claim atomico (pending->sending, riconoscibile dai bindings)
     * viene eseguita, il consenso viene revocato — esattamente come
     * farebbe un secondo processo reale nello stesso istante.
     */
    public function test_consent_revoked_in_the_instant_between_the_atomic_claim_and_the_send_still_blocks_it(): void
    {
        $subscriber = $this->confirmedSubscriber();
        $service = $this->service();
        $delivery = $service->registerDelivery('email', 'weekly_digest', $subscriber, null, '2026-W35');

        $revoked = false;
        DB::listen(function ($query) use (&$revoked, $subscriber) {
            if ($revoked || ! str_contains($query->sql, 'communication_deliveries') || ! in_array('sending', $query->bindings, true)) {
                return;
            }

            DB::table('comm_subscribers')->where('id', $subscriber->id)->update([
                'status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED,
                'unsubscribed_at' => now(),
            ]);

            $revoked = true;
        });

        $sends = 0;
        $result = $service->attemptSend($delivery, function () use (&$sends) {
            $sends++;
        });

        $this->assertTrue($revoked, 'Il listener non ha intercettato la UPDATE del claim atomico — il test non ha provato nulla.');
        $this->assertSame(0, $sends, 'Consenso revocato subito dopo il claim non deve mai far scattare $send().');
        $this->assertSame(CommunicationDelivery::STATUS_FAILED, $result->status);
        $this->assertSame(1, $result->attempts, 'Il claim ha comunque vinto una volta sola: attempts riflette il tentativo, non un secondo giro.');
    }

    public function test_deleting_the_subscriber_cascades_the_delivery_row(): void
    {
        $subscriber = $this->confirmedSubscriber();
        $service = $this->service();
        $service->registerDelivery('email', 'weekly_digest', $subscriber, null, '2026-W34');

        $this->assertSame(1, CommunicationDelivery::count());

        $subscriber->delete();

        $this->assertSame(0, CommunicationDelivery::count());
    }
}
