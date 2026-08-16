<?php

namespace App\Services\Communication;

/**
 * Esito di un tentativo di consegna, come lo restituirebbe un
 * EmailDeliveryProvider reale. Value object puro.
 *
 *   accepted           -> il provider ha accettato il messaggio per la
 *                          consegna. Non è una prova di consegna reale,
 *                          solo che il tentativo non è fallito in modo
 *                          sincrono (stessa semantica onesta già
 *                          documentata per CommunicationDelivery::
 *                          STATUS_SENT).
 *   rejected            -> il provider ha rifiutato il messaggio in modo
 *                          sincrono e definitivo (es. indirizzo non
 *                          valido secondo il provider). Mai ritentabile
 *                          automaticamente.
 *   transient_failure   -> fallimento probabilmente temporaneo (timeout,
 *                          rate limit, errore 5xx del provider).
 *                          Ritentabile entro il numero massimo di
 *                          tentativi.
 *   permanent_failure   -> fallimento non transitorio per motivi diversi
 *                          da un rifiuto esplicito (es. configurazione
 *                          non valida). Mai ritentabile automaticamente,
 *                          come 'rejected'.
 */
final class DeliveryResult
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_TRANSIENT_FAILURE = 'transient_failure';

    public const STATUS_PERMANENT_FAILURE = 'permanent_failure';

    /**
     * @param  array<string, mixed>  $metadata  Mai corpo email, token, o altra PII oltre a quanto già nel RenderedCampaignMessage.
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly array $metadata = [],
        public readonly ?string $reason = null,
    ) {}

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isTransientFailure(): bool
    {
        return $this->status === self::STATUS_TRANSIENT_FAILURE;
    }

    public function isPermanentFailure(): bool
    {
        return in_array($this->status, [self::STATUS_PERMANENT_FAILURE, self::STATUS_REJECTED], true);
    }
}
