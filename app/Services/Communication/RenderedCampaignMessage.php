<?php

namespace App\Services\Communication;

/**
 * Risultato immutabile del rendering di una campagna per un destinatario
 * specifico (o segnaposto — vedi CampaignRenderer). Puramente un valore:
 * nessun metodo qui ha effetti collaterali o accede al DB. Sostituisce
 * la precedente CampaignRendering: stessa cosa, con i campi che servono
 * anche alla futura orchestrazione di invio (text fallback, identità
 * campagna, target di disiscrizione, chiave di idempotenza), non solo
 * alla pagina di anteprima — un'unica implementazione riusata da
 * entrambe, mai due rendering divergenti.
 */
final class RenderedCampaignMessage
{
    public function __construct(
        public readonly string $subject,
        public readonly ?string $preheader,
        public readonly string $html,
        public readonly string $text,
        public readonly ?string $fromName,
        public readonly ?string $fromEmail,
        public readonly ?string $replyTo,
        public readonly ?int $recipientSubscriberId,
        public readonly ?string $recipientEmail,
        public readonly bool $isPlaceholderRecipient,
        public readonly int $campaignId,
        public readonly string $campaignUuid,
        public readonly ?string $unsubscribeUrl,
        /**
         * Chiave di idempotenza canonica: sha256(campaign_id . ':' .
         * subscriber_id) — null solo per un destinatario segnaposto (mai
         * per un rendering usato realmente dall'orchestrazione, che non
         * accetta mai un subscriber nullo). Stessa granularità del
         * vincolo unique(campaign_id, subscriber_id) già su comm_sends:
         * NON esiste un concetto di "versione campagna" in questo
         * schema, quindi modificare il contenuto della campagna dopo
         * uno snapshot non genera una nuova identità di consegna — vedi
         * il docblock di CampaignRenderer per il ragionamento completo.
         */
        public readonly ?string $idempotencyKey,
    ) {}
}
