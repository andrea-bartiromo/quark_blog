<?php

namespace App\Services\Communication;

/**
 * Esito immutabile di CampaignPreflightService::assess(). Sola lettura,
 * nessuna azione: la valutazione può essere ricalcolata a piacere senza
 * alcun effetto collaterale, coerente con il resto della pipeline di
 * verifica pre-invio (Prepara → Anteprima → Preflight → Dry-run).
 */
final readonly class CampaignPreflightReport
{
    public const NOT_READY = 'not_ready';

    public const READY_FOR_TEST_SEND = 'ready_for_test_send';

    /**
     * @param  list<string>  $blockingErrors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $readiness,
        public int $preparedCount,
        public int $staleCount,
        public int $notYetPreparedCount,
        public int $eligibleTotal,
        public bool $unsubscribeRouteAvailable,
        public array $blockingErrors,
        public array $warnings,
    ) {}

    public function isReady(): bool
    {
        return $this->readiness === self::READY_FOR_TEST_SEND;
    }

    public function readinessLabel(): string
    {
        return $this->isReady() ? 'Pronta per un invio di test' : 'Non pronta';
    }
}
