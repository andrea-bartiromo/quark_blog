<?php

namespace App\Services\EditorialRadar;

use App\Services\EditorialRadar\Providers\SearchConsoleOpportunityProvider;
use App\Services\EditorialRadar\Providers\SecondReadOpportunityProvider;
use Illuminate\Support\Collection;

/**
 * Convergence layer for the provider graph currently available on main.
 *
 * Keeping composition outside the original provider-heavy service makes the
 * new Search Console dependency optional-by-data: when no import exists the
 * provider returns an empty collection, while the established health/SEO/
 * linking opportunities remain unchanged.
 */
class EditorialRadarProviderGraphService
{
    public function __construct(
        private readonly EditorialRadarService $core,
        private readonly SearchConsoleOpportunityProvider $searchConsole,
        private readonly SecondReadOpportunityProvider $secondRead,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function opportunities(): Collection
    {
        return $this->core->opportunities()
            ->concat($this->searchConsole->opportunities())
            ->concat($this->secondRead->opportunities())
            ->unique('key')
            ->sortBy(fn (array $row) => [
                match ($row['priority']) {
                    'HIGH' => 1, 'MEDIUM' => 2, default => 3
                },
                $row['type'],
                $row['key'],
            ])
            ->values();
    }
}
