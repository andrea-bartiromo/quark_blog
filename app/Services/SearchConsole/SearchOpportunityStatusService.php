<?php

namespace App\Services\SearchConsole;

use App\Models\SearchOpportunityStatus;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Workflow editoriale leggero (Mission 6): nuova/vista/gestita/ignorata,
 * mai un punteggio o una raccomandazione automatica — solo uno stato che
 * la redazione assegna a mano. Non crea mai articoli, non modifica mai
 * copy SEO.
 *
 * Missione 48/49 (secondo batch autonomo KAIRUS, Fase F — Search
 * Intelligence): questo servizio è ora consultato anche da
 * SearchConsoleOpportunityProvider (EditorialRadar), che a sua volta lo
 * usa per non ripresentare per sempre un'opportunità già "gestita" o
 * "ignorata" — l'isolamento da EditorialRadarService era solo temporaneo
 * (quel servizio non era ancora su main quando questa classe è nata), non
 * un vincolo architetturale permanente.
 */
class SearchOpportunityStatusService
{
    /**
     * Una sola query per l'intero elenco di opportunità mostrato — mai una
     * query per riga.
     *
     * @param  Collection<int, SearchOpportunity>  $opportunities
     * @return array<string,string> opportunity_key => status
     */
    public function statusesFor(Collection $opportunities): array
    {
        $keys = $opportunities->pluck('key')->unique()->values();

        if ($keys->isEmpty()) {
            return [];
        }

        return SearchOpportunityStatus::query()
            ->whereIn('opportunity_key', $keys)
            ->pluck('status', 'opportunity_key')
            ->all();
    }

    public function setStatus(string $opportunityKey, string $status, ?User $actor): SearchOpportunityStatus
    {
        $record = SearchOpportunityStatus::query()->firstOrNew(['opportunity_key' => $opportunityKey]);
        $record->status = $status;
        $record->updated_by = $actor?->id;
        $record->save();

        return $record;
    }
}
