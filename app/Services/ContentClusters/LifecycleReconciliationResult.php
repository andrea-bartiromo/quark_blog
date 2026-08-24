<?php

namespace App\Services\ContentClusters;

/**
 * Read-only outcome of one ContentClusterLifecycleReconciler::reconcile()
 * call — never constructed directly by callers, always returned by the
 * reconciler so the "why" behind a transition (or lack thereof) is
 * explainable without re-deriving it.
 */
final class LifecycleReconciliationResult
{
    public function __construct(
        public readonly string $previousLifecycle,
        public readonly string $resultingLifecycle,
        public readonly bool $changed,
        public readonly string $reason,
        public readonly int $publicPrefixLength,
        public readonly int $totalMembershipCount,
        public readonly bool $hasHiddenRemainder,
    ) {}
}
