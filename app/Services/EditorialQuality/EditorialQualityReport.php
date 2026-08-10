<?php

namespace App\Services\EditorialQuality;

/**
 * Fotografia della qualità editoriale di UN articolo in un dato momento —
 * mai persistita, sempre ricalcolata (vedi EditorialQualityChecker::check()).
 *
 * READINESS (FASE 29): un FAIL su un controllo ESSENTIAL rende l'articolo
 * INCOMPLETE; nessun FAIL essenziale ma almeno un WARNING (o un FAIL su un
 * controllo RECOMMENDED — trattato come un segnale "da guardare", non
 * bloccante) rende l'articolo ATTENTION; tutto PASS (o NOT_APPLICABLE)
 * rende l'articolo READY. Non è una media di severità: una sola condizione
 * essenziale non soddisfatta basta a segnalare INCOMPLETE, indipendentemente
 * da quanti altri controlli sono PASS.
 */
final readonly class EditorialQualityReport
{
    public const LEVEL_READY = 'ready';

    public const LEVEL_ATTENTION = 'attention';

    public const LEVEL_INCOMPLETE = 'incomplete';

    /**
     * @param  array<int, EditorialQualityCheckResult>  $results
     */
    public function __construct(
        public int $articleId,
        public array $results,
    ) {}

    public function level(): string
    {
        $essentialFailed = array_filter(
            $this->results,
            fn (EditorialQualityCheckResult $r) => $r->importance === EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL
                && $r->status === EditorialQualityCheckResult::STATUS_FAIL
        );

        if ($essentialFailed !== []) {
            return self::LEVEL_INCOMPLETE;
        }

        $anyAttention = array_filter(
            $this->results,
            fn (EditorialQualityCheckResult $r) => in_array($r->status, [
                EditorialQualityCheckResult::STATUS_WARNING,
                EditorialQualityCheckResult::STATUS_FAIL,
            ], true)
        );

        return $anyAttention !== [] ? self::LEVEL_ATTENTION : self::LEVEL_READY;
    }

    /** Etichetta italiana del livello — usata da UI e CLI, un solo punto di traduzione. */
    public function levelLabel(): string
    {
        return match ($this->level()) {
            self::LEVEL_READY => 'Pronto',
            self::LEVEL_ATTENTION => 'Attenzione',
            self::LEVEL_INCOMPLETE => 'Da completare',
        };
    }

    /** Controlli applicabili (esclude NOT_APPLICABLE) — il denominatore di "N/M controlli superati". */
    public function applicableResults(): array
    {
        return array_values(array_filter($this->results, fn (EditorialQualityCheckResult $r) => $r->isApplicable()));
    }

    public function passedCount(): int
    {
        return count(array_filter(
            $this->applicableResults(),
            fn (EditorialQualityCheckResult $r) => $r->status === EditorialQualityCheckResult::STATUS_PASS
        ));
    }

    public function applicableCount(): int
    {
        return count($this->applicableResults());
    }

    /** @return array<int, EditorialQualityCheckResult> */
    public function issues(): array
    {
        return array_values(array_filter(
            $this->results,
            fn (EditorialQualityCheckResult $r) => in_array($r->status, [
                EditorialQualityCheckResult::STATUS_WARNING,
                EditorialQualityCheckResult::STATUS_FAIL,
            ], true)
        ));
    }
}
