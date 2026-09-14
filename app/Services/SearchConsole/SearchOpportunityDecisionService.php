<?php

namespace App\Services\SearchConsole;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SearchOpportunityDecision;
use App\Models\SearchOpportunityDecisionHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cantiere 4 (programma "Kairus Organic Discovery"). Decisione editoriale
 * tracciabile per opportunità di ricerca — mai un'azione automatica, mai
 * una pubblicazione automatica: registra solo la scelta umana e la
 * collega a un articolo esistente o a un brief (ProjectTask di tipo
 * "publication", riusato dal modulo Progettazione — mai un modello
 * duplicato per "brief").
 */
class SearchOpportunityDecisionService
{
    public function __construct(
        private readonly SearchOpportunityScoringService $scoring,
        private readonly SearchConsoleFreshnessService $freshness,
    ) {}

    /**
     * Una sola query per l'intero elenco di opportunità mostrato — mai una
     * query per riga, stesso principio di SearchOpportunityStatusService::statusesFor().
     * Eager-carica article/projectTask: la vista li legge per ogni riga
     * con una decisione (Codex, PR #590), altrimenti una query per riga.
     *
     * @param  Collection<int, SearchOpportunity>  $opportunities
     * @return array<string, SearchOpportunityDecision> opportunity_key => decisione
     */
    public function decisionsFor(Collection $opportunities): array
    {
        $keys = $opportunities->pluck('key')->unique()->values();

        if ($keys->isEmpty()) {
            return [];
        }

        return SearchOpportunityDecision::query()
            ->whereIn('opportunity_key_hash', $keys->map(fn (string $key) => $this->keyHash($key)))
            ->with(['article', 'projectTask'])
            ->get()
            ->keyBy('opportunity_key')
            ->all();
    }

    /**
     * Registra (o aggiorna) la decisione corrente per un'opportunità. Il
     * baseline delle metriche (clic/impression/CTR/posizione) viene
     * catturato SOLO alla primissima decisione per questa opportunity_key
     * — mai ricalcolato da una decisione successiva, altrimenti il
     * confronto "esito dopo 28/90 giorni" perderebbe il punto di
     * riferimento originale. Ogni chiamata produce comunque una riga di
     * storico append-only, con l'intero stato precedente/nuovo (non solo
     * il tipo di decisione — Codex, PR #590), così la cronologia può
     * sempre ricostruire cosa è davvero cambiato.
     *
     * La creazione del brief (quando decisionType è DECISION_CREATE_BRIEF
     * e non esiste già un project_task_id) avviene DENTRO la stessa
     * transazione con lock sulla riga della decisione (Codex, PR #590):
     * due invii concorrenti per la stessa opportunità non possono più
     * creare due ProjectTask distinti, perché il secondo attende il lock
     * del primo e trova già il project_task_id impostato.
     */
    public function record(
        SearchOpportunity $opportunity,
        string $decisionType,
        ?string $rationale,
        ?int $articleId,
        User $actor,
    ): SearchOpportunityDecision {
        return DB::transaction(function () use ($opportunity, $decisionType, $rationale, $articleId, $actor) {
            $decision = SearchOpportunityDecision::query()
                ->where('opportunity_key_hash', $this->keyHash($opportunity->key))
                ->lockForUpdate()
                ->first();

            $isNew = $decision === null;
            $previousSnapshot = $isNew ? null : $this->snapshot($decision->decision_type, $decision->article_id, $decision->project_task_id, $decision->rationale);

            $projectTaskId = $decision?->project_task_id;
            if ($decisionType === SearchOpportunityDecision::DECISION_CREATE_BRIEF && $projectTaskId === null) {
                $projectTaskId = $this->createBriefForOpportunity($opportunity, $actor)->id;
            }

            $decision ??= new SearchOpportunityDecision([
                'opportunity_key' => $opportunity->key,
                'opportunity_key_hash' => $this->keyHash($opportunity->key),
            ]);
            $decision->opportunity_type = $opportunity->type;
            $decision->opportunity_query = $opportunity->query;
            $decision->decision_type = $decisionType;
            $decision->rationale = $rationale;
            $decision->article_id = $articleId;
            $decision->project_task_id = $projectTaskId;
            $decision->updated_by = $actor->id;

            if ($isNew) {
                $decision->created_by = $actor->id;
                $decision->baseline_clicks = $opportunity->clicks;
                $decision->baseline_impressions = $opportunity->impressions;
                $decision->baseline_ctr = $opportunity->ctr;
                $decision->baseline_position = $opportunity->position;
                $decision->baseline_captured_at = now();
            }

            $decision->save();

            SearchOpportunityDecisionHistory::record(
                opportunityKey: $opportunity->key,
                action: $isNew ? 'decision_recorded' : 'decision_updated',
                userId: $actor->id,
                oldValue: $previousSnapshot,
                newValue: $this->snapshot($decisionType, $articleId, $projectTaskId, $rationale),
                reason: $rationale,
            );

            return $decision;
        });
    }

    /**
     * SHA-256 di opportunity_key: la chiave testuale (tipo|query|pagina)
     * può superare la lunghezza indicizzabile in modo univoco in
     * utf8mb4 (768 caratteri, limite di prefisso InnoDB a 3072 byte) —
     * l'hash a lunghezza fissa è ciò che rende l'unicità reale.
     */
    private function keyHash(string $key): string
    {
        return hash('sha256', $key);
    }

    private function snapshot(string $decisionType, ?int $articleId, ?int $projectTaskId, ?string $rationale): string
    {
        return sprintf(
            'decision_type=%s;article_id=%s;project_task_id=%s;rationale=%s',
            $decisionType,
            $articleId ?? '',
            $projectTaskId ?? '',
            $rationale ?? '',
        );
    }

    /**
     * Crea un brief per un nuovo articolo — un ProjectTask di tipo
     * "publication" SENZA alcun articolo collegato, nel progetto
     * editoriale predefinito attivo. Mai un Article creato qui: il brief è
     * solo un'attività di redazione, l'articolo lo scrive un umano.
     *
     * @throws RuntimeException se non esiste un progetto editoriale
     *                          predefinito attivo — fail-closed, mai un
     *                          progetto creato automaticamente al volo.
     */
    public function createBriefForOpportunity(SearchOpportunity $opportunity, User $actor): ProjectTask
    {
        $project = Project::defaultEditorial();

        if ($project === null) {
            throw new RuntimeException('Nessun progetto editoriale predefinito attivo: configura un progetto con "predefinito per l\'editoriale" prima di creare un brief.');
        }

        return ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Brief: '.$opportunity->query,
            'description' => $opportunity->explanation,
            'type' => ProjectTask::TYPE_PUBLICATION,
            'manual_status' => ProjectTask::STATUS_TODO,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Misura l'esito a 28/90 giorni dalla decisione — SOLA LETTURA sui
     * dati Search Console già importati, mai una richiesta esterna. Una
     * decisione la cui opportunità non compare più tra quelle attualmente
     * calcolabili (nessun dato Search Console recente per quella query)
     * resta semplicemente non misurata: fail-closed, mai una misurazione
     * indovinata o azzerata.
     *
     * Ogni orizzonte richiede dati Search Console che coprano davvero
     * quella distanza dal baseline (Codex, PR #590): senza questo
     * controllo, eseguire il comando una sola volta dopo 90 giorni
     * userebbe lo STESSO periodo più recente sia per +28 sia per +90 —
     * e se nessun CSV più recente del baseline fosse mai stato importato,
     * l'"esito" registrato sarebbe in realtà lo stesso dato di partenza,
     * con il timestamp di misurazione che impedirebbe per sempre un
     * nuovo tentativo quando un CSV realmente più recente arriverà. Le
     * opportunità da ricerca interna a zero risultati non dipendono da
     * un periodo importato (il conteggio è sempre "adesso"): per queste
     * il solo tempo trascorso dal baseline basta.
     *
     * @return array{measured_28d: int, measured_90d: int}
     */
    public function measureDueOutcomes(): array
    {
        $now = now();
        $due28d = SearchOpportunityDecision::query()
            ->whereNull('measured_28d_at')
            ->whereNotNull('baseline_captured_at')
            ->where('baseline_captured_at', '<=', $now->clone()->subDays(28))
            ->get();

        $due90d = SearchOpportunityDecision::query()
            ->whereNull('measured_90d_at')
            ->whereNotNull('baseline_captured_at')
            ->where('baseline_captured_at', '<=', $now->clone()->subDays(90))
            ->get();

        if ($due28d->isEmpty() && $due90d->isEmpty()) {
            return ['measured_28d' => 0, 'measured_90d' => 0];
        }

        $periods = $this->freshness->availablePeriods();
        $latestPeriod = $periods->first();
        $latestPeriodEnd = $latestPeriod ? Carbon::parse($latestPeriod['period_end']) : null;
        $currentByKey = $this->scoring->currentOpportunities($latestPeriod, $periods->get(1))->keyBy('key')->all();

        $measured28d = 0;
        foreach ($due28d as $decision) {
            if ($this->periodCoversHorizon($decision, $latestPeriodEnd, 28) && $this->applyMeasurement($decision, $currentByKey, '28d')) {
                $measured28d++;
            }
        }

        $measured90d = 0;
        foreach ($due90d as $decision) {
            if ($this->periodCoversHorizon($decision, $latestPeriodEnd, 90) && $this->applyMeasurement($decision, $currentByKey, '90d')) {
                $measured90d++;
            }
        }

        return ['measured_28d' => $measured28d, 'measured_90d' => $measured90d];
    }

    /**
     * Per ogni decisione la cui misurazione è dovuta (baseline abbastanza
     * vecchio) ma non ancora eseguita, distingue quelle che il comando
     * `measure-outcomes` misurerebbe DAVVERO se eseguito ora ("eseguibili")
     * da quelle che restano bloccate anche eseguendolo — nessun periodo
     * Search Console copre ancora l'orizzonte, o l'opportunità non è più
     * tra quelle attualmente calcolabili (Codex, Cantiere 7/PR #593):
     * un contatore "dovute" che non distingue le due situazioni suggerisce
     * di rilanciare un comando che per una parte di quelle righe non
     * risolverà mai nulla, finché non arriva un import più recente o
     * l'opportunità torna a comparire. Riusa le stesse condizioni di
     * idoneità di measureDueOutcomes(), mai una seconda regola.
     *
     * @return array{runnable_28d: int, blocked_28d: int, runnable_90d: int, blocked_90d: int}
     */
    public function dueOutcomesEligibility(): array
    {
        $now = now();
        $due28d = SearchOpportunityDecision::query()
            ->whereNull('measured_28d_at')
            ->whereNotNull('baseline_captured_at')
            ->where('baseline_captured_at', '<=', $now->clone()->subDays(28))
            ->get();

        $due90d = SearchOpportunityDecision::query()
            ->whereNull('measured_90d_at')
            ->whereNotNull('baseline_captured_at')
            ->where('baseline_captured_at', '<=', $now->clone()->subDays(90))
            ->get();

        if ($due28d->isEmpty() && $due90d->isEmpty()) {
            return ['runnable_28d' => 0, 'blocked_28d' => 0, 'runnable_90d' => 0, 'blocked_90d' => 0];
        }

        $periods = $this->freshness->availablePeriods();
        $latestPeriod = $periods->first();
        $latestPeriodEnd = $latestPeriod ? Carbon::parse($latestPeriod['period_end']) : null;
        $currentByKey = $this->scoring->currentOpportunities($latestPeriod, $periods->get(1))->keyBy('key');

        $classify = function (Collection $due, int $days) use ($latestPeriodEnd, $currentByKey): array {
            $runnable = 0;
            $blocked = 0;

            foreach ($due as $decision) {
                if ($this->periodCoversHorizon($decision, $latestPeriodEnd, $days) && $currentByKey->has($decision->opportunity_key)) {
                    $runnable++;
                } else {
                    $blocked++;
                }
            }

            return [$runnable, $blocked];
        };

        [$runnable28d, $blocked28d] = $classify($due28d, 28);
        [$runnable90d, $blocked90d] = $classify($due90d, 90);

        return [
            'runnable_28d' => $runnable28d,
            'blocked_28d' => $blocked28d,
            'runnable_90d' => $runnable90d,
            'blocked_90d' => $blocked90d,
        ];
    }

    /**
     * Vero se i dati attualmente disponibili coprono davvero l'orizzonte
     * richiesto per QUESTA decisione — mai solo "è passato abbastanza
     * tempo di calendario".
     */
    private function periodCoversHorizon(SearchOpportunityDecision $decision, ?Carbon $latestPeriodEnd, int $days): bool
    {
        if ($decision->opportunity_type === SearchOpportunityScoringService::TYPE_INTERNAL_ZERO_RESULT_SEARCH) {
            return true;
        }

        if ($latestPeriodEnd === null) {
            return false;
        }

        return $latestPeriodEnd->gte($decision->baseline_captured_at->clone()->addDays($days));
    }

    /** @param  array<string, SearchOpportunity>  $currentByKey */
    private function applyMeasurement(SearchOpportunityDecision $decision, array $currentByKey, string $horizon): bool
    {
        $current = $currentByKey[$decision->opportunity_key] ?? null;

        if ($current === null) {
            return false;
        }

        $decision->{"measured_{$horizon}_clicks"} = $current->clicks;
        $decision->{"measured_{$horizon}_impressions"} = $current->impressions;
        $decision->{"measured_{$horizon}_ctr"} = $current->ctr;
        $decision->{"measured_{$horizon}_position"} = $current->position;
        $decision->{"measured_{$horizon}_at"} = now();
        $decision->save();

        SearchOpportunityDecisionHistory::record(
            opportunityKey: $decision->opportunity_key,
            action: "measured_{$horizon}",
            userId: null,
            newValue: "clicks={$current->clicks};impressions={$current->impressions}",
        );

        return true;
    }
}
