<?php

namespace App\Services\ProjectAction;

/**
 * Sintesi in tre livelli dello stato operativo di un progetto — risponde
 * alla domanda "devo fare qualcosa adesso?", non duplica la domanda
 * "cosa" (quella resta di NextActionSuggestion/ProjectNextActionResolverV2).
 *
 * Deliberatamente derivato dalla severità dei segnali già calcolati da
 * ProjectNextActionResolverV2, non una seconda regola parallela: due motori
 * di classificazione indipendenti sulla stessa base di fatti rischierebbero
 * di divergere silenziosamente (un segnale "urgente" per la prossima azione
 * ma "OK" per lo stato di salute confonderebbe più che aiutare). Vedi
 * ProjectHealthResolver per la mappatura esplicita.
 */
final readonly class ProjectHealth
{
    public const LEVEL_OK = 'ok';

    public const LEVEL_ATTENTION = 'attention';

    public const LEVEL_BLOCKED = 'blocked';

    public function __construct(
        public string $level,
        /** @var list<NextActionSuggestion> */
        public array $signals,
    ) {}

    public function label(): string
    {
        return match ($this->level) {
            self::LEVEL_BLOCKED => 'Bloccato',
            self::LEVEL_ATTENTION => 'Attenzione',
            default => 'OK',
        };
    }
}
