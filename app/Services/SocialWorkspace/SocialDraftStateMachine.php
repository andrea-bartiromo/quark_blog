<?php

namespace App\Services\SocialWorkspace;

use App\Models\SocialDraft;

/**
 * Puro: nessuna scrittura, nessuna dipendenza esterna. Rappresenta solo il
 * grafo di transizioni ammesse per il ledger editoriale interno. published
 * e failed non compaiono mai come destinazione: sono stati puramente
 * informativi per una futura fase provider, mai raggiungibili da questa V1
 * (vedi SocialDraftWorkspaceService, unico punto che applica transizioni).
 */
class SocialDraftStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        SocialDraft::STATUS_DRAFT => [SocialDraft::STATUS_REVIEWED],
        SocialDraft::STATUS_REVIEWED => [SocialDraft::STATUS_DRAFT, SocialDraft::STATUS_APPROVED],
        SocialDraft::STATUS_APPROVED => [SocialDraft::STATUS_REVIEWED, SocialDraft::STATUS_SCHEDULED],
        SocialDraft::STATUS_SCHEDULED => [SocialDraft::STATUS_APPROVED],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED[$from] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public function allowedTargets(string $from): array
    {
        return self::ALLOWED[$from] ?? [];
    }
}
