<?php

namespace App\Services\ContentGraph;

use App\Models\Article;
use App\Models\Concept;

/**
 * Mission 23 — Orphan Health: la listing per-item che
 * ContentGraphCoverageService (Mission 19) deliberatamente non fa — quel
 * servizio espone solo conteggi aggregati, questo espone GLI ELEMENTI.
 * Stesso principio "read-only, editor decide" di ConceptDuplicateAuditService
 * e stessa forma di riepilogo di PercorsoCoverageAuditService::audit()
 * (published_without_path): solo id/titolo/slug/stato, mai un elenco
 * che ricalcoli o modifichi qualcosa.
 *
 * Simmetrico apposta, mirror del precedente Percorsi: articoli pubblicati
 * senza collegamento e concetti attivi senza collegamento. "Concetti
 * attivi senza domande" resta fuori — è già itemizzato separatamente
 * dal readiness per-domanda di ConceptQuestionReadinessService (Mission
 * 21), una dimensione diversa (profondità del contenuto, non
 * connettività del grafo) che confonderebbe questo audit se fusa qui.
 */
class ContentGraphOrphanAuditService
{
    /**
     * @return list<array{id:int, title:string, slug:string, status:string}>
     */
    public function orphanArticles(): array
    {
        return Article::query()
            ->published()
            ->whereDoesntHave('contentConcepts')
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'status'])
            ->map(fn (Article $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'status' => $article->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int, name:string, slug:string, status:string}>
     */
    public function orphanConcepts(): array
    {
        return Concept::query()
            ->active()
            ->whereDoesntHave('articleLinks')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'status'])
            ->map(fn (Concept $concept) => [
                'id' => $concept->id,
                'name' => $concept->name,
                'slug' => $concept->slug,
                'status' => $concept->status,
            ])
            ->values()
            ->all();
    }
}
