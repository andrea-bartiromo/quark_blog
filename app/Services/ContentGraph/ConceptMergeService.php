<?php

namespace App\Services\ContentGraph;

use App\Models\ArticleConcept;
use App\Models\Concept;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mission 18 — Merge Workflow Foundation: esegue la fusione esplicita di
 * un concetto duplicato (individuato da ConceptDuplicateAuditService,
 * Mission 17) in un concetto canonico scelto dall'editor. Nessuna euristica
 * di somiglianza qui: chi chiama questo servizio ha già deciso quali due
 * concetti sono davvero lo stesso — non è questo servizio a deciderlo.
 *
 * concept_aliases, article_concepts e concept_questions hanno tutte una FK
 * concept_id con cascadeOnDelete(): cancellare $duplicate senza prima
 * riassegnare esplicitamente ogni riga figlia le perderebbe in silenzio.
 * Ogni riassegnazione avviene quindi PRIMA della delete, dentro un'unica
 * transazione — o l'intera fusione riesce, o nulla cambia.
 */
class ConceptMergeService
{
    /**
     * @return array{
     *     aliases_moved:int,
     *     aliases_skipped_duplicate:int,
     *     name_preserved_as_alias:bool,
     *     article_links_moved:int,
     *     article_links_conflicts_resolved:int,
     *     questions_moved:int,
     * }
     */
    public function merge(Concept $target, Concept $duplicate): array
    {
        if ($target->id === $duplicate->id) {
            throw new InvalidArgumentException('Un concetto non può essere fuso con se stesso.');
        }

        return DB::transaction(function () use ($target, $duplicate) {
            $report = [
                'aliases_moved' => 0,
                'aliases_skipped_duplicate' => 0,
                'name_preserved_as_alias' => false,
                'article_links_moved' => 0,
                'article_links_conflicts_resolved' => 0,
                'questions_moved' => 0,
            ];

            $this->mergeAliases($target, $duplicate, $report);
            $this->mergeArticleLinks($target, $duplicate, $report);

            // concept_questions.slug è unico a livello di intera tabella
            // (non scoped al concetto): riassegnare concept_id su una riga
            // già persistita non tocca lo slug e non può quindi violare
            // quel vincolo. Nessuna gestione di collisione necessaria.
            $report['questions_moved'] = $duplicate->questions()->update(['concept_id' => $target->id]);

            $duplicate->delete();

            return $report;
        });
    }

    /**
     * @param  array{aliases_moved:int, aliases_skipped_duplicate:int, name_preserved_as_alias:bool, article_links_moved:int, article_links_conflicts_resolved:int, questions_moved:int}  $report
     */
    private function mergeAliases(Concept $target, Concept $duplicate, array &$report): void
    {
        $knownTexts = $target->aliases()->pluck('alias')
            ->map(fn (string $alias) => $this->normalizeForComparison($alias))
            ->all();
        $knownTexts[] = $this->normalizeForComparison($target->name);

        foreach ($duplicate->aliases()->get() as $alias) {
            $normalized = $this->normalizeForComparison($alias->alias);

            if (in_array($normalized, $knownTexts, true)) {
                $alias->delete();
                $report['aliases_skipped_duplicate']++;

                continue;
            }

            $alias->update(['concept_id' => $target->id]);
            $knownTexts[] = $normalized;
            $report['aliases_moved']++;
        }

        // Il nome del duplicato scompare col concetto: lo si conserva come
        // alias sul target, così chi cercava il concetto col vecchio nome
        // continua a trovarlo, a meno che non coincida già col target.
        $duplicateName = $this->normalizeForComparison($duplicate->name);
        if (! in_array($duplicateName, $knownTexts, true)) {
            $target->aliases()->create(['alias' => $duplicate->name]);
            $report['name_preserved_as_alias'] = true;
        }
    }

    /**
     * @param  array{aliases_moved:int, aliases_skipped_duplicate:int, name_preserved_as_alias:bool, article_links_moved:int, article_links_conflicts_resolved:int, questions_moved:int}  $report
     */
    private function mergeArticleLinks(Concept $target, Concept $duplicate, array &$report): void
    {
        $targetLinksByArticle = $target->articleLinks()->get()->keyBy('article_id');

        foreach ($duplicate->articleLinks()->get() as $link) {
            $existing = $targetLinksByArticle->get($link->article_id);

            if ($existing === null) {
                $link->update(['concept_id' => $target->id]);
                $report['article_links_moved']++;

                continue;
            }

            // Conflitto: l'articolo è già collegato al concetto target
            // (article_concepts ha unique(article_id, concept_id), quindi
            // non si può avere entrambe le righe). Vince "primary" su
            // "supporting"; a parità di relation_type vince il weight più
            // alto. La riga perdente viene scartata: l'articolo resta
            // comunque collegato al concetto risultante tramite quella
            // vincente.
            if ($this->articleLinkWins($link, $existing)) {
                $existing->update([
                    'relation_type' => $link->relation_type,
                    'weight' => $link->weight,
                ]);
            }

            $link->delete();
            $report['article_links_conflicts_resolved']++;
        }
    }

    private function articleLinkWins(ArticleConcept $candidate, ArticleConcept $incumbent): bool
    {
        if ($candidate->relation_type !== $incumbent->relation_type) {
            return $candidate->relation_type === ArticleConcept::RELATION_PRIMARY;
        }

        return $candidate->weight > $incumbent->weight;
    }

    private function normalizeForComparison(string $text): string
    {
        return mb_strtolower(trim($text), 'UTF-8');
    }
}
