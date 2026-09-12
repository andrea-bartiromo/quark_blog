<?php

namespace App\Services\PublicPages;

use App\Models\AuditFindingStatus;
use App\Models\User;

/**
 * Cantiere 31 (programma 100-cantieri Kairus). Stesso pattern di
 * App\Services\SearchConsole\SearchOpportunityStatusService (Mission 6):
 * solo uno stato assegnato a mano per riga, mai una correzione automatica
 * o un ricalcolo del finding stesso — quello resta compito esclusivo dei
 * sei audit di PublicHealthDashboardService (Cantiere 30).
 */
class AuditFindingStatusService
{
    /**
     * Una sola query per l'intero snapshot della dashboard — mai una
     * query per riga (stesso principio di
     * SearchOpportunityStatusService::statusesFor()).
     *
     * @param  list<string>  $findingKeys
     * @return array<string,string> finding_key => status
     */
    public function statusesFor(array $findingKeys): array
    {
        $keys = collect($findingKeys)->unique()->values();

        if ($keys->isEmpty()) {
            return [];
        }

        return AuditFindingStatus::query()
            ->whereIn('finding_key', $keys)
            ->pluck('status', 'finding_key')
            ->all();
    }

    public function setStatus(string $domain, string $findingKey, string $status, ?User $actor): AuditFindingStatus
    {
        $record = AuditFindingStatus::query()->firstOrNew(['finding_key' => $findingKey]);
        $record->domain = $domain;
        $record->status = $status;
        $record->updated_by = $actor?->id;
        $record->save();

        return $record;
    }
}
