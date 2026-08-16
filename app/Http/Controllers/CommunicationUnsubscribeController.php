<?php

namespace App\Http\Controllers;

use App\Models\CommunicationSubscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Disiscrizione pubblica per comm_subscribers (Newsletter 2.0) — GET/POST
 * separati deliberatamente, a differenza del vecchio flusso Newsletter
 * legacy (NewsletterController::unsubscribe(), un'unica GET che cancella
 * la riga): un link GET che muta stato è un anti-pattern noto (prefetch
 * dei client di posta, scanner di sicurezza, bot che seguono i link nelle
 * email possono attivarlo senza intervento dell'utente). Qui GET mostra
 * solo una pagina di conferma senza alcun effetto collaterale; solo la
 * POST esegue davvero la disiscrizione.
 *
 * Nessun login richiesto (per design: chi possiede il token è per
 * definizione l'unico destinatario legittimo). Nessuna cancellazione
 * della riga: lo stato passa a 'unsubscribed', preservando la riga per
 * auditabilità — la cancellazione GDPR è un'azione distinta e più
 * deliberata, mai implicita in un click di disiscrizione.
 */
class CommunicationUnsubscribeController extends Controller
{
    public function confirm(string $token): View|Response
    {
        $subscriber = $this->findByToken($token);

        if (! $subscriber) {
            return response()
                ->view('communication.unsubscribe-invalid')
                ->setStatusCode(404);
        }

        return view('communication.unsubscribe-confirm', [
            'subscriber' => $subscriber,
            'alreadyUnsubscribed' => $subscriber->status === CommunicationSubscriber::STATUS_UNSUBSCRIBED,
        ]);
    }

    public function unsubscribe(Request $request, string $token): View|Response
    {
        $subscriber = $this->findByToken($token);

        if (! $subscriber) {
            return response()
                ->view('communication.unsubscribe-invalid')
                ->setStatusCode(404);
        }

        // UPDATE idempotente: sicura da ripetere (doppio click, refresh
        // pagina, retry di rete) — non fallisce né produce un risultato
        // diverso se il subscriber è già unsubscribed. Un solo statement
        // atomico, nessuna finestra di corsa da chiudere con un lock
        // applicativo: due POST concorrenti sullo stesso token convergono
        // entrambe allo stesso stato finale senza eccezioni.
        CommunicationSubscriber::where('id', $subscriber->id)
            ->where('status', '!=', CommunicationSubscriber::STATUS_UNSUBSCRIBED)
            ->update([
                'status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED,
                'unsubscribed_at' => now(),
            ]);

        return view('communication.unsubscribe-done');
    }

    /**
     * Mai un messaggio o un log distinto tra "token con formato non
     * valido" e "token valido ma inesistente": stesso esito (404
     * generico), stessa vista, nessuna informazione differenziale che
     * confermi o smentisca l'esistenza di un token specifico. Nessun
     * log qui contiene mai il token stesso.
     */
    private function findByToken(string $token): ?CommunicationSubscriber
    {
        if (trim($token) === '') {
            return null;
        }

        return CommunicationSubscriber::where('unsubscribe_token', $token)->first();
    }
}
