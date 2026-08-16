<?php

namespace App\Services\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSubscriber;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

/**
 * Rendering PURO di una campagna per un destinatario: nessun invio,
 * nessuna coda, nessun side effect, nessuna scrittura DB. Unica logica
 * di rendering nel repository — la preview la usa oggi, la futura
 * orchestrazione di invio (con provider fake) la riusa identica, mai una
 * seconda implementazione divergente.
 *
 * Fonte del contenuto: SEMPRE $campaign->subject/preheader/content — mai
 * il template collegato. La campagna "si ancora" al contenuto scritto al
 * momento della sua creazione/modifica (vedi il docblock di
 * CommunicationCampaign::templateVersion()): il template è solo lo
 * scaffold iniziale usato da resolveTemplateSelection() in fase di
 * creazione, non una fonte che il rendering deve ri-leggere ogni volta.
 *
 * Nessun motore di merge-tag è stato introdotto qui (non esiste altrove
 * nel repository): il corpo della campagna è renderizzato così com'è.
 * Il testo semplice è ottenuto convertendo l'HTML effettivamente
 * renderizzato (HtmlToPlainTextConverter), non ricostruito a parte:
 * questo garantisce che le due rappresentazioni non possano mai
 * divergere.
 *
 * Idempotenza (Newsletter 2.0, FASE 9 della missione): campaign_id +
 * subscriber_id è la chiave canonica, la STESSA granularità del vincolo
 * unique(campaign_id, subscriber_id) già su comm_sends fin dalla sua
 * migration originale. Non esiste (e non viene introdotto qui) un
 * concetto di "campaign_version": questo schema non supporta il
 * reinvio della stessa campagna come evento logico distinto, quindi
 * modificare il contenuto della campagna DOPO uno snapshot non crea una
 * nuova identità di consegna — al momento della consegna (reale o fake)
 * verrà semplicemente renderizzato il contenuto CORRENTE della
 * campagna con quella stessa chiave, esattamente come editare una bozza
 * email prima di premere invio non la trasforma in un'email diversa.
 * Introdurre un campaign_version avrebbe senso solo per un futuro
 * concetto di "invii ripetuti della stessa campagna come eventi
 * distinti", che questa missione non costruisce.
 */
class CampaignRenderer
{
    public function __construct(
        private readonly HtmlToPlainTextConverter $textConverter,
    ) {}

    public function render(CommunicationCampaign $campaign, ?CommunicationSubscriber $subscriber): RenderedCampaignMessage
    {
        $isPlaceholder = $subscriber === null;

        $unsubscribeUrl = $subscriber
            ? URL::route('comunicazione.disiscrizione.conferma', $subscriber->unsubscribe_token)
            : null;

        $html = View::make('emails.communication.campaign', [
            'campaign' => $campaign,
            'subscriber' => $subscriber,
            'isPlaceholderRecipient' => $isPlaceholder,
            'unsubscribeUrl' => $unsubscribeUrl,
        ])->render();

        return new RenderedCampaignMessage(
            subject: self::sanitizeHeaderValue((string) $campaign->subject) ?? '',
            preheader: self::sanitizeHeaderValue($campaign->preheader),
            html: $html,
            text: $this->textConverter->convert($html),
            fromName: self::sanitizeHeaderValue($campaign->senderProfile?->from_name),
            fromEmail: self::sanitizeHeaderValue($campaign->senderProfile?->from_email),
            replyTo: self::sanitizeHeaderValue($campaign->senderProfile?->reply_to),
            recipientSubscriberId: $subscriber?->id,
            recipientEmail: $subscriber?->email,
            isPlaceholderRecipient: $isPlaceholder,
            campaignId: $campaign->id,
            campaignUuid: $campaign->uuid,
            unsubscribeUrl: $unsubscribeUrl,
            idempotencyKey: $subscriber ? self::idempotencyKey($campaign->id, $subscriber->id) : null,
        );
    }

    public static function idempotencyKey(int $campaignId, int $subscriberId): string
    {
        return hash('sha256', $campaignId.':'.$subscriberId);
    }

    /**
     * Difesa in profondità contro CRLF/header injection (N2.11): questi
     * campi (subject/preheader/from_name/from_email/reply_to) diventano
     * header email veri quando un provider reale verrà collegato — un
     * \r o \n al loro interno basterebbe a iniettare header aggiuntivi
     * se quel provider non li normalizzasse da solo. La validazione in
     * ingresso (StoreCommunicationCampaignRequest e affini) già rifiuta
     * questi caratteri, ma questo è il punto di rendering condiviso da
     * preview, dry-run E futuro invio reale: non deve MAI produrre un
     * valore con newline, indipendentemente da come i dati sono arrivati
     * nel DB (fixture, import, scrittura diretta che bypassa il
     * FormRequest).
     */
    private static function sanitizeHeaderValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim(str_replace(["\r\n", "\r", "\n"], ' ', $value));
    }
}
