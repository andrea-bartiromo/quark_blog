<?php

namespace App\Services\SearchConsole;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SearchOpportunityDecision;
use App\Models\SearchOpportunityDecisionHistory;
use App\Models\User;
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
            ->whereIn('opportunity_key', $keys)
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
     * storico append-only.
     */
    public function record(
        SearchOpportunity $opportunity,
        string $decisionType,
        ?string $rationale,
        ?int $articleId,
        ?int $projectTaskId,
        User $actor,
    ): SearchOpportunityDecision {
        return DB::transaction(function () use ($opportunity, $decisionType, $rationale, $articleId, $projectTaskId, $actor) {
            $decision = SearchOpportunityDecision::query()
                ->where('opportunity_key', $opportunity->key)
                ->lockForUpdate()
                ->first();

            $isNew = $decision === null;
            $previousDecisionType = $decision?->decision_type;
            $previousRationale = $decision?->rationale;

            $decision ??= new SearchOpportunityDecision(['opportunity_key' => $opportunity->key]);
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
                oldValue: $previousDecisionType,
                newValue: $decisionType,
                reason: $rationale ?? $previousRationale,
            );

            return $decision;
        });
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

        $currentByKey = $this->currentOpportunitiesByKey();

        $measured28d = 0;
        foreach ($due28d as $decision) {
            if ($this->applyMeasurement($decision, $currentByKey, '28d')) {
                $measured28d++;
            }
        }

        $measured90d = 0;
        foreach ($due90d as $decision) {
            if ($this->applyMeasurement($decision, $currentByKey, '90d')) {
                $measured90d++;
            }
        }

        return ['measured_28d' => $measured28d, 'measured_90d' => $measured90d];
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

    /** @return array<string, SearchOpportunity> */
    private function currentOpportunitiesByKey(): array
    {
        $periods = $this->freshness->availablePeriods();

        return $this->scoring->currentOpportunities($periods->first(), $periods->get(1))
            ->keyBy('key')
            ->all();
    }
}
