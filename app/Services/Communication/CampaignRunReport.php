<?php

namespace App\Services\Communication;

/**
 * Riepilogo numerico di un'esecuzione completa dell'orchestrazione
 * (reale o dry-run) su una campagna: esattamente i sei contatori che il
 * dry-run deve poter riportare (FASE 13 della missione).
 */
final class CampaignRunReport
{
    public function __construct(
        public readonly int $eligible = 0,
        public readonly int $skipped = 0,
        public readonly int $rendered = 0,
        public readonly int $accepted = 0,
        public readonly int $transientFailed = 0,
        public readonly int $permanentFailed = 0,
    ) {}

    public function withOutcome(SendProcessingOutcome $outcome): self
    {
        return match (true) {
            $outcome->outcome === SendProcessingOutcome::SKIPPED => new self(
                $this->eligible, $this->skipped + 1, $this->rendered, $this->accepted, $this->transientFailed, $this->permanentFailed
            ),
            $outcome->outcome === SendProcessingOutcome::SENT => new self(
                $this->eligible, $this->skipped, $this->rendered + 1, $this->accepted + 1, $this->transientFailed, $this->permanentFailed
            ),
            $outcome->outcome === SendProcessingOutcome::RETRIED => new self(
                $this->eligible, $this->skipped, $this->rendered + 1, $this->accepted, $this->transientFailed + 1, $this->permanentFailed
            ),
            $outcome->outcome === SendProcessingOutcome::FAILED && $outcome->reason === 'render_exception' => new self(
                $this->eligible, $this->skipped, $this->rendered, $this->accepted, $this->transientFailed, $this->permanentFailed + 1
            ),
            default => new self(
                $this->eligible, $this->skipped, $this->rendered + 1, $this->accepted, $this->transientFailed, $this->permanentFailed + 1
            ),
        };
    }

    public function withEligible(int $count): self
    {
        return new self($count, $this->skipped, $this->rendered, $this->accepted, $this->transientFailed, $this->permanentFailed);
    }

    /**
     * Somma i contatori di evento di un altro report (skipped/rendered/
     * accepted/transientFailed/permanentFailed) mantenendo l'eligible di
     * QUESTO report — usato da runCampaign() per accorpare più round di
     * elaborazione (una riga ritentata e poi accettata deve produrre sia
     * un transient_failed sia un accepted: entrambi sono eventi reali
     * avvenuti, non un unico "stato finale" da collassare), senza
     * moltiplicare il conteggio di destinatari realmente distinti.
     */
    public function mergeOutcomes(self $other): self
    {
        return new self(
            $this->eligible,
            $this->skipped + $other->skipped,
            $this->rendered + $other->rendered,
            $this->accepted + $other->accepted,
            $this->transientFailed + $other->transientFailed,
            $this->permanentFailed + $other->permanentFailed,
        );
    }

    /**
     * @return array{eligible:int, skipped:int, rendered:int, accepted:int, transient_failed:int, permanent_failed:int}
     */
    public function toArray(): array
    {
        return [
            'eligible' => $this->eligible,
            'skipped' => $this->skipped,
            'rendered' => $this->rendered,
            'accepted' => $this->accepted,
            'transient_failed' => $this->transientFailed,
            'permanent_failed' => $this->permanentFailed,
        ];
    }
}
