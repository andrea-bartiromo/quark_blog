<?php

namespace App\Services\InternalLinking;

/**
 * Un articolo analizzato dal comando content:internal-link-audit — mai
 * persistito, ricalcolato ad ogni esecuzione (FASE 47-equivalente:
 * nessuna colonna quality/audit nel DB, il dato è sempre derivato).
 */
final readonly class InternalLinkAuditRow
{
    /**
     * @param  array<int, array{slug: string, anchorText: string, classification: string}>  $outgoingLinks
     */
    public function __construct(
        public int $articleId,
        public string $title,
        public string $slug,
        public string $status,
        public array $outgoingLinks,
        public int $outgoingDistinctCount,
        public int $incomingLinksCount,
        public bool $hasAmbiguousAnchor,
    ) {}

    public function isOrphan(): bool
    {
        return $this->status === 'published' && $this->incomingLinksCount === 0;
    }

    public function hasBrokenOutgoingLinks(): bool
    {
        return $this->countByClassification('missing') > 0;
    }

    public function countByClassification(string $classification): int
    {
        return count(array_filter($this->outgoingLinks, fn (array $l) => $l['classification'] === $classification));
    }
}
