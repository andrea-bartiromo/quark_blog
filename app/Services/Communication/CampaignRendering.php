<?php

namespace App\Services\Communication;

/**
 * Risultato immutabile del rendering di una campagna per un destinatario
 * specifico (o segnaposto — vedi CampaignRenderer). Puramente un valore:
 * nessun metodo qui ha effetti collaterali o accede al DB.
 */
final class CampaignRendering
{
    public function __construct(
        public readonly string $subject,
        public readonly ?string $preheader,
        public readonly string $html,
        public readonly ?string $recipientEmail,
        public readonly bool $isPlaceholderRecipient,
        public readonly ?string $fromName,
        public readonly ?string $fromEmail,
    ) {}
}
