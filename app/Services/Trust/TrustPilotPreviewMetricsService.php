<?php

namespace App\Services\Trust;

use App\Models\TrustKnowledgeStatement;
use App\Models\TrustKnowledgeStatementPreviewView;
use Illuminate\Support\Collection;

/**
 * Cantiere 43 (programma "100 cantieri Kairus", dipende dal Cantiere 40).
 *
 * Rehearsal privacy-first della metrica "Visualizzazioni aggregate" del
 * contratto B-44 (docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md),
 * applicata all'uso interno redazionale della preview (Cantiere 40) —
 * mai al pilot pubblico reale, che non esiste ancora (gate B-45 in
 * vigore). Stessa semantica INSUFFICIENT_DATA di B-44 per questa
 * metrica: "meno di 7 giorni di dati raccolti" — un conteggio zero dopo
 * 7 giorni resta un valore reale (zero), non "dati insufficienti";
 * stesso principio già documentato in docs/DASHBOARD_DATA_EXPORT_V1.md
 * ("lo zero del campione resta zero").
 *
 * Registra e legge SOLO conteggi aggregati per TrustKnowledgeStatement:
 * nessun identificativo di visitatore/sessione/utente, mai esposto o
 * persistito qui (si legga il docblock del modello).
 */
class TrustPilotPreviewMetricsService
{
    public const STATE_AVAILABLE = 'available';

    public const STATE_INSUFFICIENT_DATA = 'insufficient_data';

    private const MIN_DAYS_COLLECTED = 7;

    public function recordView(TrustKnowledgeStatement $statement): void
    {
        TrustKnowledgeStatementPreviewView::create([
            'trust_knowledge_statement_id' => $statement->id,
        ]);
    }

    /** @return array{state: string, count: int, days_collected: int} */
    public function aggregateViewsFor(TrustKnowledgeStatement $statement): array
    {
        $daysCollected = (int) $statement->created_at->diffInDays(now());
        $count = TrustKnowledgeStatementPreviewView::query()
            ->where('trust_knowledge_statement_id', $statement->id)
            ->count();

        return [
            'state' => $daysCollected < self::MIN_DAYS_COLLECTED ? self::STATE_INSUFFICIENT_DATA : self::STATE_AVAILABLE,
            'count' => $count,
            'days_collected' => $daysCollected,
        ];
    }

    /**
     * Una sola query per l'intero elenco — mai una per riga, stesso
     * principio già in uso altrove in questo codebase (es.
     * SearchOpportunityDecisionService::decisionsFor()).
     *
     * @param  Collection<int, TrustKnowledgeStatement>  $statements
     * @return array<int, array{state: string, count: int, days_collected: int}> statement_id => metrica
     */
    public function aggregateViewsForMany(Collection $statements): array
    {
        if ($statements->isEmpty()) {
            return [];
        }

        $counts = TrustKnowledgeStatementPreviewView::query()
            ->whereIn('trust_knowledge_statement_id', $statements->pluck('id'))
            ->selectRaw('trust_knowledge_statement_id, count(*) as aggregate')
            ->groupBy('trust_knowledge_statement_id')
            ->pluck('aggregate', 'trust_knowledge_statement_id');

        return $statements->mapWithKeys(function (TrustKnowledgeStatement $statement) use ($counts) {
            $daysCollected = (int) $statement->created_at->diffInDays(now());

            return [$statement->id => [
                'state' => $daysCollected < self::MIN_DAYS_COLLECTED ? self::STATE_INSUFFICIENT_DATA : self::STATE_AVAILABLE,
                'count' => (int) ($counts[$statement->id] ?? 0),
                'days_collected' => $daysCollected,
            ]];
        })->all();
    }
}
