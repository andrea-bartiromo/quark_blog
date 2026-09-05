<?php

namespace App\Services\SocialWorkspace;

use App\Models\SocialDraft;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sola lettura: individua collisioni (stesso canale, stesso istante
 * programmato) tra bozze già "scheduled". Non sposta mai date, non
 * modifica record, non decide una policy — restituisce solo i record
 * coinvolti e la motivazione, lasciando la decisione al chiamante
 * (SocialDraftWorkspaceService), coerente col divieto esplicito di
 * spostamento automatico.
 */
class SocialDraftCollisionDetector
{
    /**
     * @return Collection<int, SocialDraft>
     */
    public function collisionsFor(string $channel, Carbon $scheduledAt, ?int $excludeId = null): Collection
    {
        return SocialDraft::query()
            ->where('channel', $channel)
            ->where('status', SocialDraft::STATUS_SCHEDULED)
            ->where('scheduled_at', $scheduledAt)
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
            ->get();
    }

    public function hasCollision(string $channel, Carbon $scheduledAt, ?int $excludeId = null): bool
    {
        return $this->collisionsFor($channel, $scheduledAt, $excludeId)->isNotEmpty();
    }
}
