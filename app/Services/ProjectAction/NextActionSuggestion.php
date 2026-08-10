<?php

namespace App\Services\ProjectAction;

/**
 * Un singolo segnale prodotto da ProjectNextActionResolverV2 — dati
 * strutturati e machine-readable, mai una stringa già formattata: la
 * presentazione (view, colore, link) resta sempre una scelta successiva,
 * non di questo servizio. Puramente informativo o suggerito: nessuna
 * istanza di questa classe viene mai applicata automaticamente a nulla.
 */
final readonly class NextActionSuggestion
{
    public const SEVERITY_URGENT = 'urgent';

    public const SEVERITY_ATTENTION = 'attention';

    public const SEVERITY_INFO = 'info';

    private const SEVERITY_RANK = [
        self::SEVERITY_URGENT => 0,
        self::SEVERITY_ATTENTION => 1,
        self::SEVERITY_INFO => 2,
    ];

    public function __construct(
        /** Codice machine-readable, stabile — es. "task_overdue", "editorial_missing_article". */
        public string $code,
        /** Etichetta leggibile, già pronta per la UI. */
        public string $label,
        /** Spiegazione breve del perché — null se l'etichetta è già autoesplicativa. */
        public ?string $rationale,
        public string $severity,
        /** Origine del segnale — "task" | "github" | "editorial_calendar" | "project". */
        public string $source,
        public bool $requiresHumanDecision,
        public ?string $entityType = null,
        public ?int $entityId = null,
    ) {}

    public static function aligned(): self
    {
        return new self(
            code: 'aligned',
            label: 'Progetto allineato — nessuna azione richiesta.',
            rationale: null,
            severity: self::SEVERITY_INFO,
            source: 'project',
            requiresHumanDecision: false,
        );
    }

    public function severityRank(): int
    {
        return self::SEVERITY_RANK[$this->severity] ?? 99;
    }
}
