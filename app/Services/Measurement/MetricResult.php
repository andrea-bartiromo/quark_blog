<?php

namespace App\Services\Measurement;

/**
 * Measurement Closeout — forma unica di ogni metrica prodotta da questo
 * gruppo, e dell'export che la ripubblica.
 *
 * Il punto centrale, richiesto esplicitamente sia dalla Missione 3 sia
 * dall'export: ZERO E ASSENTE NON SONO LO STESSO NUMERO. Un second-read rate
 * di 0.0 significa "abbiamo osservato sessioni e nessuna ha letto due
 * articoli"; INSUFFICIENT_DATA significa "non abbiamo osservato abbastanza per
 * dire alcunché". Renderli entrambi come "0%" è il modo più rapido per far
 * prendere una decisione editoriale sbagliata, quindi lo stato viaggia SEMPRE
 * accanto al valore e il valore è null quando lo stato non è AVAILABLE.
 */
final class MetricResult
{
    /** Il valore è stato calcolato su un campione sufficiente. */
    public const AVAILABLE = 'available';

    /** Il dataset esiste ma il campione è sotto soglia: nessun valore pubblicato. */
    public const INSUFFICIENT_DATA = 'insufficient_data';

    /** Il dataset non esiste in questo repository (es. workspace non presente). */
    public const UNAVAILABLE = 'unavailable';

    /** La metrica non ha significato in questo contesto. */
    public const NOT_APPLICABLE = 'not_applicable';

    private function __construct(
        public readonly string $status,
        public readonly ?float $value,
        public readonly ?int $numerator,
        public readonly ?int $denominator,
        public readonly ?int $sample,
        public readonly ?string $reason,
        public readonly ?string $denominatorDefinition,
        public readonly ?string $unit,
    ) {}

    /**
     * Costruisce una metrica di rapporto proteggendo dalla divisione non
     * valida in un solo punto: nessun chiamante di questo gruppo esegue mai
     * una divisione a mano, quindi non esiste un secondo posto in cui possa
     * comparire un `/ 0`.
     *
     * @param  int  $minimumSample  Soglia di campione minimo. Sotto questa soglia
     *                              il valore NON viene pubblicato: con pochissime sessioni un
     *                              rapporto è sia statisticamente privo di senso sia
     *                              potenzialmente re-identificante (vedi
     *                              docs/MEASUREMENT_CLOSEOUT.md).
     */
    public static function ratio(
        int $numerator,
        int $denominator,
        string $denominatorDefinition,
        int $minimumSample = 0,
        string $unit = 'ratio_0_1',
    ): self {
        if ($denominator <= 0 || $denominator < $minimumSample) {
            return new self(
                self::INSUFFICIENT_DATA,
                null,
                $numerator,
                $denominator,
                $denominator,
                $denominator <= 0
                    ? 'Nessuna osservazione nel periodo: denominatore vuoto, il rapporto non è definito.'
                    : 'Campione sotto la soglia minima di '.$minimumSample.' osservazioni.',
                $denominatorDefinition,
                $unit,
            );
        }

        return new self(
            self::AVAILABLE,
            round($numerator / $denominator, 4),
            $numerator,
            $denominator,
            $denominator,
            null,
            $denominatorDefinition,
            $unit,
        );
    }

    public static function count(int $value, string $definition, string $unit = 'count'): self
    {
        return new self(self::AVAILABLE, (float) $value, $value, null, $value, null, $definition, $unit);
    }

    public static function unavailable(string $reason, ?string $definition = null): self
    {
        return new self(self::UNAVAILABLE, null, null, null, null, $reason, $definition, null);
    }

    public static function insufficient(string $reason, ?int $sample = null, ?string $definition = null): self
    {
        return new self(self::INSUFFICIENT_DATA, null, null, null, $sample, $reason, $definition, null);
    }

    public static function notApplicable(string $reason): self
    {
        return new self(self::NOT_APPLICABLE, null, null, null, null, $reason, null, null);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::AVAILABLE;
    }

    /**
     * Percentuale già arrotondata per la sola presentazione. Restituisce null
     * — non 0 — quando il valore non è disponibile, così una vista non può
     * accidentalmente stampare "0%" per un dato assente.
     */
    public function percent(int $decimals = 1): ?float
    {
        return $this->value === null ? null : round($this->value * 100, $decimals);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'value' => $this->value,
            'numerator' => $this->numerator,
            'denominator' => $this->denominator,
            'sample' => $this->sample,
            'unit' => $this->unit,
            'denominator_definition' => $this->denominatorDefinition,
            'unavailable_reason' => $this->reason,
        ];
    }
}
