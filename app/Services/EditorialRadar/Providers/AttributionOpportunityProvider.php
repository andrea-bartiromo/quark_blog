<?php

namespace App\Services\EditorialRadar\Providers;

use App\Models\Newsletter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AttributionOpportunityProvider
{
    /** @return Collection<int, array<string, mixed>> */
    public function opportunities(): Collection
    {
        // Placement-only newsletter data cannot name an article, and the social
        // ledger has no downstream visit/engagement event. No editorial card is
        // more truthful than an invented zero or attribution.
        return collect();
    }

    /** @return array<string, array<string, mixed>> */
    public function availability(): array
    {
        try {
            $articleSurfaceSignups = Schema::hasColumn('newsletter', 'source')
                ? Newsletter::query()->where('source', 'article')->count()
                : null;
            $socialLedger = Schema::hasTable('social_publications');

            return [
                'newsletter_article_attribution' => [
                    'status' => 'insufficient',
                    'article_surface_signups' => $articleSurfaceSignups,
                    'reason' => 'Placement-only source does not persist article identity.',
                ],
                'social_downstream_attribution' => [
                    'status' => 'insufficient',
                    'publication_ledger_available' => $socialLedger,
                    'reason' => 'The publication ledger records delivery, not downstream visits or engagement.',
                ],
            ];
        } catch (Throwable) {
            return [
                'newsletter_article_attribution' => ['status' => 'unavailable', 'reason' => 'Source data unavailable.'],
                'social_downstream_attribution' => ['status' => 'unavailable', 'reason' => 'Source data unavailable.'],
            ];
        }
    }
}
