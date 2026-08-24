<?php

namespace App\Services\ContentGraph;

use App\Models\Concept;

/**
 * Audit diagnostico e read-only per concetti potenzialmente duplicati.
 * Non fonde, non elimina e non riassegna alias: segnala soltanto coppie
 * di concetti che condividono lo stesso testo normalizzato (nome o alias),
 * lasciando ogni decisione a un editor — stesso contratto "read-only,
 * editor decide" di PercorsoCoverageAuditService.
 *
 * Nessun vincolo DB impedisce oggi due Concept con lo stesso "name" (solo
 * lo slug è unico), né un alias che coincide col nome/alias di un ALTRO
 * concetto (l'unicità di concept_aliases è scoped a un solo concetto).
 * Due concetti quasi-duplicati non rompono nessuna query pubblica —
 * ContentGraphService opera sempre per singolo concept_id — ma
 * frammentano i collegamenti articolo/domanda tra due nodi che
 * dovrebbero essere uno solo: un problema di qualità editoriale, non di
 * correttezza.
 *
 * Confronto deliberatamente prudente: solo match ESATTO dopo
 * normalizzazione (case, spazi, apostrofi, punteggiatura finale) — stessa
 * normalizzazione conservativa di EditorialCalendarMatchingService::normalizeTitle(),
 * l'unico altro punto in questo codebase che confronta testo editoriale
 * per quasi-duplicati. Nessun fuzzy match (similar_text/Levenshtein): non
 * esiste qui un precedente di soglia per nomi di concetti, e introdurne
 * uno rischierebbe falsi positivi tra concetti legittimamente distinti
 * (es. "Rete Neurale" vs "Reti Neurali").
 */
class ConceptDuplicateAuditService
{
    /**
     * @return list<array{
     *     normalized_text:string,
     *     concepts:list<array{id:int,name:string,slug:string,status:string,matched_via:string}>,
     * }>
     */
    public function audit(): array
    {
        $concepts = Concept::query()->with('aliases')->orderBy('id')->get();

        // Ogni concetto contribuisce una voce per il proprio nome e una per
        // ciascun alias, tutte con lo stesso concept_id — un solo passaggio
        // di raggruppamento per testo normalizzato individua name-vs-name,
        // name-vs-alias e alias-vs-alias tra concetti DIVERSI, senza
        // confronti O(n²) a coppie.
        $entries = collect();
        foreach ($concepts as $concept) {
            $entries->push([
                'concept' => $concept,
                'normalized' => $this->normalize($concept->name),
                'source' => 'name',
            ]);
            foreach ($concept->aliases as $alias) {
                $entries->push([
                    'concept' => $concept,
                    'normalized' => $this->normalize($alias->alias),
                    'source' => 'alias',
                ]);
            }
        }

        return $entries
            ->filter(fn (array $entry) => $entry['normalized'] !== '')
            ->groupBy('normalized')
            ->filter(fn ($group) => $group->pluck('concept.id')->unique()->count() > 1)
            ->map(fn ($group, string $normalizedText) => [
                'normalized_text' => $normalizedText,
                'concepts' => $group
                    ->unique(fn (array $entry) => $entry['concept']->id.'|'.$entry['source'])
                    ->map(fn (array $entry) => [
                        'id' => $entry['concept']->id,
                        'name' => $entry['concept']->name,
                        'slug' => $entry['concept']->slug,
                        'status' => $entry['concept']->status,
                        'matched_via' => $entry['source'],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Stessa normalizzazione conservativa di
     * EditorialCalendarMatchingService::normalizeTitle() — duplicata qui
     * (non condivisa via injection) perché le due funzioni pure vivono in
     * bounded context distinti (calendario editoriale vs content graph) e
     * non hanno altro in comune.
     */
    private function normalize(string $text): string
    {
        $text = trim($text);
        $text = str_replace(['’', '‘', '´', '`'], "'", $text);
        $text = str_replace(['“', '”', '"'], '', $text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = rtrim($text, " \t\n\r\0\x0B?!.,;:");

        return trim($text);
    }
}
