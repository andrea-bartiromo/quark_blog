<?php

namespace App\Services\InternalLinking;

/**
 * Risultato completo di InternalLinkAuditService::audit() — sola lettura,
 * nessun side effect. $rows riflette il sottoinsieme filtrato da
 * --article=/--status= (se presenti), ma i conteggi al suo interno
 * (incomingLinksCount, classificazioni) restano calcolati sull'INTERO
 * corpus indipendentemente dal filtro — un articolo isolato lo è rispetto
 * a tutta Kairus, non solo al sottoinsieme richiesto in questa esecuzione.
 */
final readonly class InternalLinkAuditReport
{
    /**
     * @param  array<int, InternalLinkAuditRow>  $rows
     * @param  array<int, array{id:int,title:string,slug:string}>  $publishedWithoutIncomingLinks
     * @param  array<int, array{id:int,title:string,slug:string,published_at:?string}>  $scheduledWithoutInternalLinks
     * @param  array<int, array{id:int,source:array{id:int,title:string},target:array{id:int,title:string},anchor_text:string,confidence_score:int}>  $highConfidenceUnusedSuggestions
     */
    public function __construct(
        public int $analyzed,
        public int $withoutOutgoingLinks,
        public int $withOneOutgoingLink,
        public int $withTwoOrMoreOutgoingLinks,
        public int $brokenLinks,
        public int $selfLinks,
        public int $unpublishedTargets,
        public int $scheduledSafeLinks,
        public int $redirectedLinks,
        public int $articlesWithAmbiguousAnchors,
        public int $isolatedArticles,
        public array $rows,
        public array $publishedWithoutIncomingLinks,
        public array $scheduledWithoutInternalLinks,
        public array $highConfidenceUnusedSuggestions,
    ) {}
}
