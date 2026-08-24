<?php

namespace App\Http\Controllers;

use App\Models\CommunicationSubscriber;
use App\Models\ContentCluster;
use App\Models\ContentClusterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Avvisami quando continua" — iscrizione/disiscrizione per singolo
 * Percorso. Riusa comm_subscribers come unica identità/consenso (mai
 * duplicata) e content_cluster_subscribers come intento di iscrizione
 * (vedi il docblock della migration). Non invia mai email di
 * notifica-contenuto direttamente: quella parte passa esclusivamente da
 * CommunicationDeliveryService (vedi App\Jobs\SendPathContinuationNotification
 * e App\Listeners\NotifyPathSubscribersOnArticlePublished).
 *
 * L'unica email inviata direttamente da questo controller è la conferma
 * di iscrizione (double opt-in) — transazionale, non una notifica di
 * contenuto, quindi intenzionalmente fuori dal ledger generico: quel
 * livello garantisce "at-most-once per un evento di notifica verso un
 * destinatario già consenziente", non "invia un link di conferma a
 * un'identità non ancora consenziente" (problema diverso, stesso schema
 * usato dal Newsletter legacy — vedi NewsletterController::subscribe()).
 */
class ContentClusterSubscriptionController extends Controller
{
    public function subscribe(Request $request, string $slug): RedirectResponse
    {
        // Honeypot anti-bot, stesso pattern di NewsletterController.
        if ($request->filled('website')) {
            return $this->back($request, $slug, 'ok');
        }

        $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);

        $cluster = ContentCluster::query()->where('slug', $slug)->publiclyVisible()->first();

        // Un Percorso concluso o inattivo non deve MAI accettare una nuova
        // iscrizione, anche via submit diretto che aggira la UI (Parti
        // 14/15) — non è un leak: lo stato è già visibile pubblicamente
        // sulla pagina stessa, quindi qui basta rifiutare esplicitamente
        // invece di fingere un successo.
        if (! $cluster || ! $cluster->acceptsPathSubscriptions()) {
            return $this->back($request, $slug, 'unavailable');
        }

        $email = Str::lower(trim($request->input('email')));

        $subscriber = $this->findOrCreateSubscriberIdentity($email, $cluster->slug);

        $this->activatePathSubscription($subscriber->id, $cluster->id);

        // Manda (o rimanda) la conferma solo se l'identità non è ancora
        // confermata — un subscriber già confermato (newsletter o altro
        // Percorso) non riceve una seconda email inutile: la sua riga
        // content_cluster_subscribers è già "active" e pronta a ricevere
        // notifiche reali tramite il ledger.
        if ($subscriber->status === CommunicationSubscriber::STATUS_PENDING && $subscriber->token) {
            $this->sendConfirmationEmail($subscriber);
        }

        // Risposta identica in ogni caso (nuovo/esistente/già iscritto):
        // non deve mai rivelare se un indirizzo è già presente nel sistema.
        return $this->back($request, $slug, 'ok');
    }

    public function confirm(Request $request): View
    {
        $token = trim((string) $request->query('token'));

        if ($token === '') {
            abort(404);
        }

        $subscriber = CommunicationSubscriber::where('token', $token)->first();

        if (! $subscriber) {
            abort(404);
        }

        $subscriber->update([
            'status' => CommunicationSubscriber::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'token' => null,
        ]);

        $clusters = ContentCluster::query()
            ->whereIn('id', $subscriber->pathSubscriptions()->active()->pluck('content_cluster_id'))
            ->get(['id', 'name', 'slug']);

        return view('percorsi.subscription-confirmed', compact('clusters'));
    }

    public function unsubscribe(string $token): View
    {
        $token = trim($token);

        $subscription = $token !== ''
            ? ContentClusterSubscriber::where('unsubscribe_token', $token)->first()
            : null;

        if (! $subscription) {
            return view('percorsi.subscription-unsubscribed', ['notFound' => true]);
        }

        // Idempotente: una seconda visita dello stesso link (doppia
        // disiscrizione) riapplica lo stesso stato, nessun errore, nessun
        // secondo side effect — non viene inviata alcuna email qui.
        $subscription->update([
            'status' => ContentClusterSubscriber::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ]);

        return view('percorsi.subscription-unsubscribed', [
            'notFound' => false,
            'cluster' => $subscription->contentCluster,
        ]);
    }

    /**
     * insertOrIgnore() contro l'unique su email, mai un SELECT-poi-INSERT:
     * due submit concorrenti con la stessa nuova email non devono mai
     * produrre due identità (stesso principio già stabilito in
     * CommunicationDeliveryService::registerDelivery()).
     */
    private function findOrCreateSubscriberIdentity(string $email, string $source): CommunicationSubscriber
    {
        $existing = CommunicationSubscriber::where('email', $email)->first();

        if ($existing) {
            return $existing;
        }

        $now = now();

        DB::table('comm_subscribers')->insertOrIgnore([
            'email' => $email,
            'status' => CommunicationSubscriber::STATUS_PENDING,
            'token' => Str::random(64),
            'unsubscribe_token' => Str::random(32),
            'source' => 'percorso:'.$source,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return CommunicationSubscriber::where('email', $email)->firstOrFail();
    }

    /**
     * insertOrIgnore() contro l'unique (subscriber_id, content_cluster_id)
     * per creare la riga la prima volta, poi una UPDATE guardata per
     * riattivarla se era stata disiscritta — mai una seconda riga per la
     * stessa coppia (Parte 16, scenario G), e mai un secondo giro se la
     * riga è già attiva.
     */
    private function activatePathSubscription(int $subscriberId, int $contentClusterId): bool
    {
        $now = now();

        DB::table('content_cluster_subscribers')->insertOrIgnore([
            'subscriber_id' => $subscriberId,
            'content_cluster_id' => $contentClusterId,
            'status' => ContentClusterSubscriber::STATUS_ACTIVE,
            'unsubscribe_token' => Str::random(32),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Riattiva solo se la riga esisteva già ma era disiscritta — un
        // nuovo token di disiscrizione invalida quello vecchio, nel caso
        // fosse stato condiviso o esposto in precedenza.
        $reactivated = ContentClusterSubscriber::query()
            ->where('subscriber_id', $subscriberId)
            ->where('content_cluster_id', $contentClusterId)
            ->where('status', ContentClusterSubscriber::STATUS_UNSUBSCRIBED)
            ->update([
                'status' => ContentClusterSubscriber::STATUS_ACTIVE,
                'unsubscribe_token' => Str::random(32),
                'unsubscribed_at' => null,
                'updated_at' => $now,
            ]);

        return $reactivated > 0;
    }

    private function sendConfirmationEmail(CommunicationSubscriber $subscriber): void
    {
        try {
            Mail::send([], [], function ($message) use ($subscriber) {
                $confirmUrl = route('percorsi.subscribe.confirm', ['token' => $subscriber->token]);

                $message->to($subscriber->email)
                    ->subject('🧭 Conferma la tua email per seguire i Percorsi Kairus')
                    ->html("
                        <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:2rem;background:#ffffff;'>
                            <div style='text-align:center;margin-bottom:2rem;'>
                                <div style='width:64px;height:64px;background:#0d9488;border-radius:50%;
                                            display:inline-flex;align-items:center;justify-content:center;
                                            font-size:1.8rem;margin-bottom:1rem;'>🧭</div>
                                <h1 style='font-size:1.6rem;color:#111827;margin:0 0 .25rem;font-weight:900;'>Kairus.</h1>
                                <p style='color:#6b7280;font-size:.82rem;margin:0;'>La scienza spiegata come si deve</p>
                            </div>
                            <h2 style='font-size:1.2rem;color:#111827;margin-bottom:.75rem;'>Un solo passo prima di iniziare</h2>
                            <p style='color:#374151;line-height:1.7;margin-bottom:1.5rem;'>
                                Hai chiesto di essere avvisato quando un Percorso Kairus continua.
                                Conferma il tuo indirizzo per ricevere gli aggiornamenti.
                            </p>
                            <div style='text-align:center;margin-bottom:1.5rem;'>
                                <a href='{$confirmUrl}'
                                   style='display:inline-block;background:#0d9488;color:#fff;
                                          padding:.85rem 2rem;border-radius:8px;text-decoration:none;
                                          font-weight:700;font-size:1rem;'>
                                    ✅ Conferma la tua email
                                </a>
                            </div>
                            <hr style='border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0;'>
                            <p style='color:#9ca3af;font-size:.72rem;text-align:center;margin:0;line-height:1.6;'>
                                Se non hai richiesto questo avviso, ignora questa email. Non riceverai nulla.
                            </p>
                        </div>
                    ");
            });
        } catch (\Throwable $exception) {
            Log::warning('Email di conferma Percorso non inviata: '.$exception->getMessage());
        }
    }

    private function back(Request $request, string $slug, string $status): RedirectResponse
    {
        return redirect(url()->previous() ?: route('percorsi.show', $slug))
            ->with('percorso_subscription_status', $status)
            ->with('percorso_subscription_slug', $slug);
    }
}
