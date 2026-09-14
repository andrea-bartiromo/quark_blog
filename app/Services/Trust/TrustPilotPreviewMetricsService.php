<?php

namespace App\Services\Trust;

use App\Models\TrustKnowledgeStatement;
use App\Models\TrustKnowledgeStatementPreviewView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cantiere 43 (programma "100 cantieri Kairus", dipende dal Cantiere 40).
 *
 * Rehearsal privacy-first della metrica "Visualizzazioni aggregate" del
 * contratto B-44 (docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md: evento
 * pageview, denominatore conteggio assoluto, finestra 30 giorni da
 * pubblicazione, INSUFFICIENT_DATA se meno di 7 giorni di dati raccolti),
 * applicata all'uso interno redazionale della preview (Cantiere 40) — mai
 * al pilot pubblico reale, che non esiste ancora (gate B-45 in vigore).
 * Stesso principio già documentato in docs/DASHBOARD_DATA_EXPORT_V1.md
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

    private const WINDOW_DAYS = 30;

    /**
     * Data di attivazione di questa strumentazione (Cantiere 43): nessun
     * evento di visualizzazione può fisicamente esistere prima di questa
     * data. Serve da ancora per "giorni di dati raccolti" quando una
     * TrustKnowledgeStatement è stata creata (Cantiere 38-39) prima che
     * questa metrica esistesse — altrimenti la sua sola anzianità
     * produrrebbe "available" anche a zero giorni di raccolta reale
     * trascorsi (Codex, PR #602).
     */
    private const TRACKING_STARTED_AT = '2026-09-14 00:00:00';

    public function recordView(TrustKnowledgeStatement $statement): void
    {
        TrustKnowledgeStatementPreviewView::create([
            'trust_knowledge_statement_id' => $statement->id,
        ]);
    }

    /** @return array{state: string, count: int, days_collected: int} */
    public function aggregateViewsFor(TrustKnowledgeStatement $statement): array
    {
        [$windowStart, $windowEnd] = $this->windowFor($statement);

        $count = TrustKnowledgeStatementPreviewView::query()
            ->where('trust_knowledge_statement_id', $statement->id)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->count();

        return $this->metricsFor($windowStart, $windowEnd, $count);
    }

    /**
     * Una sola query per l'intero elenco — mai una per riga, stesso
     * principio già in uso altrove in questo codebase (es.
     * SearchOpportunityDecisionService::decisionsFor()). L'aggregazione
     * per finestra (diversa per ogni statement, "30gg da pubblicazione")
     * avviene qui in PHP invece che in SQL per restare portabile fra
     * SQLite (test) e MariaDB (produzione) senza aritmetica di date
     * specifica del motore — il volume è quello di click editoriali
     * interni, non di traffico pubblico.
     *
     * @param  Collection<int, TrustKnowledgeStatement>  $statements
     * @return array<int, array{state: string, count: int, days_collected: int}> statement_id => metrica
     */
    public function aggregateViewsForMany(Collection $statements): array
    {
        if ($statements->isEmpty()) {
            return [];
        }

        $viewsByStatement = TrustKnowledgeStatementPreviewView::query()
            ->whereIn('trust_knowledge_statement_id', $statements->pluck('id'))
            ->get(['trust_knowledge_statement_id', 'created_at'])
            ->groupBy('trust_knowledge_statement_id');

        return $statements->mapWithKeys(function (TrustKnowledgeStatement $statement) use ($viewsByStatement) {
            [$windowStart, $windowEnd] = $this->windowFor($statement);

            $count = ($viewsByStatement[$statement->id] ?? collect())
                ->filter(fn ($view) => $view->created_at->between($windowStart, $windowEnd))
                ->count();

            return [$statement->id => $this->metricsFor($windowStart, $windowEnd, $count)];
        })->all();
    }

    /** @return array{0: Carbon, 1: Carbon} [$windowStart, $windowEnd] — 30 giorni da pubblicazione, per contratto B-44 */
    private function windowFor(TrustKnowledgeStatement $statement): array
    {
        $windowStart = $statement->created_at->copy();
        $windowEnd = $windowStart->copy()->addDays(self::WINDOW_DAYS);

        return [$windowStart, $windowEnd];
    }

    /** @return array{state: string, count: int, days_collected: int} */
    private function metricsFor(Carbon $windowStart, Carbon $windowEnd, int $count): array
    {
        $daysCollected = $this->daysCollected($windowStart, $windowEnd);

        return [
            'state' => $daysCollected < self::MIN_DAYS_COLLECTED ? self::STATE_INSUFFICIENT_DATA : self::STATE_AVAILABLE,
            'count' => $count,
            'days_collected' => $daysCollected,
        ];
    }

    /**
     * Giorni realmente trascorsi entro la finestra di misurazione in cui
     * la raccolta poteva avvenire: mai prima dell'attivazione della
     * strumentazione (TRACKING_STARTED_AT), mai oltre la fine della
     * finestra dei 30 giorni.
     */
    private function daysCollected(Carbon $windowStart, Carbon $windowEnd): int
    {
        $collectionStart = $windowStart->copy()->max(Carbon::parse(self::TRACKING_STARTED_AT));
        $collectionEnd = Carbon::now()->min($windowEnd);

        return $collectionEnd->greaterThan($collectionStart)
            ? $collectionStart->diffInDays($collectionEnd)
            : 0;
    }
}
