<?php

namespace App\Services\ContentClusters;

use App\Models\ContentCluster;

/**
 * Percorsi Automatic Lifecycle Completion V1.
 *
 * Promotes a Percorso from lifecycle_status=updating to complete, and
 * ONLY in that direction, the instant every configured member has become
 * part of the continuous public prefix (ContentClusterPublicSequence — the
 * single source of truth for article-prefix visibility, never
 * reconstructed here). lifecycle_status remains purely editorial metadata:
 * this service never touches is_active/publish_at, and reconcile() itself
 * has zero effect on what the public site actually shows.
 *
 * One-way only: an already-complete Percorso that later gains a hidden
 * member is NEVER silently reopened back to updating by this service — see
 * the class docblock rationale in the mission spec. A human decides that.
 *
 * Idempotent: calling reconcile() repeatedly on an unchanged cluster is a
 * no-op after the first successful transition (or never transitions at
 * all if the preconditions aren't met).
 */
class ContentClusterLifecycleReconciler
{
    /**
     * Testo base dell'ActivityLog registrato da
     * ReconcileContentClusterLifecycle a ogni promozione riuscita. Unica
     * fonte di verità condivisa con PercorsiAutomationObservability, che
     * riconosce queste righe con un confronto sul PREFISSO (Missione 14,
     * secondo batch autonomo KAIRUS): il comando appende in coda a questa
     * stessa costante il prefisso pubblico e il conteggio tappe, mai
     * sostituendola, cosi' quel confronto continua a funzionare.
     */
    public const PROMOTION_BASE_ACTION = 'Percorso concluso automaticamente (tutte le tappe configurate sono pubbliche)';

    public function __construct(
        private readonly ContentClusterPublicSequence $publicSequence,
    ) {}

    public function reconcile(ContentCluster $cluster): LifecycleReconciliationResult
    {
        $previous = $cluster->lifecycle_status;
        $sequence = $this->publicSequence->resolve($cluster);
        $publicPrefixLength = $sequence['articles']->count();
        $hasHiddenRemainder = $sequence['has_hidden_remainder'];
        $totalMembershipCount = $cluster->articles()->count();

        if ($previous !== ContentCluster::LIFECYCLE_UPDATING) {
            return new LifecycleReconciliationResult($previous, $previous, false, 'not_updating', $publicPrefixLength, $totalMembershipCount, $hasHiddenRemainder);
        }

        if ($totalMembershipCount === 0) {
            return new LifecycleReconciliationResult($previous, $previous, false, 'no_members', 0, 0, false);
        }

        if ($publicPrefixLength === 0) {
            return new LifecycleReconciliationResult($previous, $previous, false, 'zero_public_prefix', 0, $totalMembershipCount, $hasHiddenRemainder);
        }

        if ($hasHiddenRemainder) {
            return new LifecycleReconciliationResult($previous, $previous, false, 'hidden_remainder', $publicPrefixLength, $totalMembershipCount, true);
        }

        $cluster->lifecycle_status = ContentCluster::LIFECYCLE_COMPLETE;
        $cluster->save();

        return new LifecycleReconciliationResult($previous, ContentCluster::LIFECYCLE_COMPLETE, true, 'continuous_public_prefix_covers_all_members', $publicPrefixLength, $totalMembershipCount, false);
    }
}
