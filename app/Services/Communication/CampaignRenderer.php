<?php

namespace App\Services\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSubscriber;
use Illuminate\Support\Facades\View;

/**
 * Rendering PURO di una campagna per un destinatario: nessun invio,
 * nessuna coda, nessun side effect. Pensato per essere l'UNICA logica di
 * rendering — la preview la usa oggi, il futuro sender reale la dovrà
 * riusare identica (mai una seconda implementazione divergente).
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
 * Il link di disiscrizione reale non esiste ancora come rotta pubblica —
 * mostrare qui un link fittizio sarebbe peggio che dichiararlo onestamente
 * "non ancora disponibile", quindi il footer lo segnala in chiaro invece
 * di fabbricare una URL che risponderebbe 404.
 */
class CampaignRenderer
{
    public function render(CommunicationCampaign $campaign, ?CommunicationSubscriber $subscriber): CampaignRendering
    {
        $isPlaceholder = $subscriber === null;

        $html = View::make('emails.communication.campaign', [
            'campaign' => $campaign,
            'subscriber' => $subscriber,
            'isPlaceholderRecipient' => $isPlaceholder,
        ])->render();

        return new CampaignRendering(
            subject: (string) $campaign->subject,
            preheader: $campaign->preheader,
            html: $html,
            recipientEmail: $subscriber?->email,
            isPlaceholderRecipient: $isPlaceholder,
            fromName: $campaign->senderProfile?->from_name,
            fromEmail: $campaign->senderProfile?->from_email,
        );
    }
}
