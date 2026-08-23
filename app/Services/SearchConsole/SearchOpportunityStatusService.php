<?php

namespace App\Services\SearchConsole;

use App\Models\SearchOpportunityStatus;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Workflow editoriale leggero (Mission 6): nuova/vista/gestita/ignorata,
 * mai un punteggio o una raccomandazione automatica — solo uno stato che
 * la redazione assegna a mano. Non crea mai articoli, non modifica mai
 * copy SEO, non tocca mai EditorialRadarService (dominio della Radar
 * dell'altra corsia, non ancora su main).
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
