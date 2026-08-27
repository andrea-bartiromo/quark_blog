<?php

namespace App\Services\ContentGraph;

use App\Models\Concept;
use App\Models\ConceptAlias;

/**
 * Conservative, read-only alias integrity audit.
 *
 * Only exact, whitespace-trimmed and case-insensitive facts are reported.
 * Unicode canonical-equivalence and linguistic similarity are deliberately
 * not guessed: the repository has no normalization policy for those cases.
 */
class ConceptAliasIntegrityService
{
    public const EMPTY_AFTER_NORMALIZATION = 'EMPTY_AFTER_NORMALIZATION';

    public const DUPLICATE_EXACT = 'DUPLICATE_EXACT';

    public const DUPLICATE_CASE_INSENSITIVE = 'DUPLICATE_CASE_INSENSITIVE';

    public const MATCHES_OTHER_CONCEPT_NAME = 'MATCHES_OTHER_CONCEPT_NAME';

    /**
     * @return list<array{
     *     code:string,
     *     normalized_text:string,
     *     aliases:list<array{
     *         alias_id:int,
     *         alias:string,
     *         concept_id:int,
     *         concept_name:string,
     *         edit_url:string
     *     }>
     * }>
     */
    public function audit(): array
    {
        $concepts = Concept::query()
            ->with('aliases')
            ->orderBy('id')
            ->get();

        $aliases = $concepts
            ->flatMap(fn (Concept $concept) => $concept->aliases->map(
                fn (ConceptAlias $alias) => $this->row($concept, $alias)
            ))
            ->values();

        $findings = collect();

        foreach ($aliases->filter(fn (array $row) => $row['trimmed'] === '') as $row) {
            $findings->push($this->finding(
                self::EMPTY_AFTER_NORMALIZATION,
                '',
                [$row],
            ));
        }

        $nonEmpty = $aliases->filter(fn (array $row) => $row['trimmed'] !== '');

        $nonEmpty
            ->groupBy('trimmed')
            ->filter(fn ($group) => $group->count() > 1)
            ->each(fn ($group, string $text) => $findings->push(
                $this->finding(self::DUPLICATE_EXACT, $text, $group->all())
            ));

        $nonEmpty
            ->groupBy('folded')
            ->filter(function ($group) {
                return $group->count() > 1
                    && $group->pluck('trimmed')->unique()->count() > 1;
            })
            ->each(fn ($group, string $text) => $findings->push(
                $this->finding(self::DUPLICATE_CASE_INSENSITIVE, $text, $group->all())
            ));

        $canonicalNames = $concepts
            ->groupBy(fn (Concept $concept) => $this->fold($concept->name));

        foreach ($nonEmpty as $row) {
            $otherConcepts = $canonicalNames
                ->get($row['folded'], collect())
                ->where('id', '!=', $row['concept_id']);

            if ($otherConcepts->isEmpty()) {
                continue;
            }

            $findings->push($this->finding(
                self::MATCHES_OTHER_CONCEPT_NAME,
                $row['folded'],
                [$row],
            ));
        }

        return $findings->values()->all();
    }

    private function row(Concept $concept, ConceptAlias $alias): array
    {
        $trimmed = trim($alias->alias);

        return [
            'alias_id' => $alias->id,
            'alias' => $alias->alias,
            'concept_id' => $concept->id,
            'concept_name' => $concept->name,
            'edit_url' => route('admin.concepts.edit', $concept),
            'trimmed' => $trimmed,
            'folded' => $this->fold($trimmed),
        ];
    }

    private function finding(string $code, string $normalizedText, array $rows): array
    {
        return [
            'code' => $code,
            'normalized_text' => $normalizedText,
            'aliases' => collect($rows)
                ->map(fn (array $row) => collect($row)->except(['trimmed', 'folded'])->all())
                ->values()
                ->all(),
        ];
    }

    private function fold(string $text): string
    {
        return mb_strtolower(trim($text), 'UTF-8');
    }
}
