<?php

namespace App\Services\ContentGraph;

use App\Models\Concept;
use Illuminate\Support\Str;

/**
 * Sincronizza gli alias di un Concept da una lista grezza inviata dal form
 * admin (un alias per riga) contro `concept_aliases`, che ha già un vincolo
 * unique (concept_id, alias) — qui si applica la stessa deduplica anche
 * DENTRO la singola submission (case-insensitive, trim), così due righe
 * identiche digitate per errore non producono un tentativo di insert
 * duplicato che romperebbe il vincolo DB a metà sync. Il confronto con gli
 * alias già esistenti è anch'esso case-insensitive, per evitare di
 * cancellare e ricreare un alias che differisce solo per maiuscole/minuscole.
 */
class ConceptAliasSyncService
{
    /**
     * @param  list<string>  $rawAliases
     */
    public function sync(Concept $concept, array $rawAliases): void
    {
        $normalized = collect($rawAliases)
            ->map(fn ($alias) => trim((string) $alias))
            ->filter(fn (string $alias) => $alias !== '')
            ->unique(fn (string $alias) => Str::lower($alias))
            ->values();

        $keepLower = $normalized->map(fn (string $alias) => Str::lower($alias))->all();

        $existing = $concept->aliases()->get();
        $existingByLower = $existing->keyBy(fn ($alias) => Str::lower($alias->alias));

        $existing
            ->reject(fn ($alias) => in_array(Str::lower($alias->alias), $keepLower, true))
            ->each(fn ($alias) => $alias->delete());

        foreach ($normalized as $alias) {
            if (! $existingByLower->has(Str::lower($alias))) {
                $concept->aliases()->create(['alias' => $alias]);
            }
        }
    }
}
