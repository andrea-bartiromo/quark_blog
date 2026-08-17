<?php

namespace App\Services\Communication;

/**
 * Esito dell'elaborazione di UNA riga comm_sends da parte di
 * CampaignDeliveryOrchestrator::processSend(). Value object di sola
 * osservabilità — non guida alcuna logica, la state machine ha già
 * scritto lo stato persistito prima che questo venga restituito.
 */
final class SendProcessingOutcome
{
    public const SKIPPED = 'skipped';

    public const SENT = 'sent';

    public const RETRIED = 'retried';

    public const FAILED = 'failed';

    private function __construct(
        public readonly string $outcome,
        public readonly ?string $reason = null,
    ) {}

    public static function skipped(string $reason): self
    {
        return new self(self::SKIPPED, $reason);
    }

    public static function sent(): self
    {
        return new self(self::SENT);
    }

    public static function retried(string $reason): self
    {
        return new self(self::RETRIED, $reason);
    }

    public static function failed(string $reason): self
    {
        return new self(self::FAILED, $reason);
    }
}
