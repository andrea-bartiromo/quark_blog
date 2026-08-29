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
            $newsletterSchemaAvailable = Schema::hasColumn('newsletter', 'source');
            $socialLedgerAvailable = Schema::hasTable('social_publications');
            $articleSurfaceSignups = $newsletterSchemaAvailable
                ? Newsletter::query()->where('source', 'article')->count()
                : null;

            return [
                'newsletter_article_attribution' => [
                    'status' => $newsletterSchemaAvailable ? 'insufficient' : 'unavailable',
                    'article_surface_signups' => $articleSurfaceSignups,
                    'reason' => $newsletterSchemaAvailable
                        ? 'Placement-only source does not persist article identity.'
                        : 'Newsletter source schema is unavailable.',
                ],
                'social_downstream_attribution' => [
                    'status' => $socialLedgerAvailable ? 'insufficient' : 'unavailable',
                    'publication_ledger_available' => $socialLedgerAvailable,
                    'reason' => $socialLedgerAvailable
                        ? 'The publication ledger records delivery, not downstream visits or engagement.'
                        : 'Social publication ledger schema is unavailable.',
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
