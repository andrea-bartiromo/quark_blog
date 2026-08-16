<?php

namespace App\Services\EditorialQuality;

use App\Models\Article;
use Illuminate\Support\Collection;

/**
 * Esegue EditorialQualityChecker su un insieme di articoli e aggrega i
 * risultati per App\Console\Commands\EditorialQualityAuditCommand
 * (content:quality-audit) — sola lettura, nessuna scrittura.
 *
 * Ogni Article::check() lavora sui dati già caricati dalla singola query
 * iniziale (nessuna query aggiuntiva per articolo, nessun parsing DOM
 * ripetuto oltre a quello già interno a ciascun controllo — vedi FASE 37
 * della missione).
 */
class EditorialQualityAuditService
{
    public function __construct(
        private readonly EditorialQualityChecker $checker,
    ) {}

    public function audit(?int $articleId = null, ?string $status = null): EditorialQualityAuditSummary
    {
        // Self-review: EditorialQualityChecker::duplicateTitleCheck()
        // eseguirebbe altrimenti una query per OGNI articolo analizzato
        // (N+1 misurato: 181 query su 60 articoli — vedi
        // tests/Feature/EditorialQualityAuditPerformanceTest.php). Un solo
        // pluck() su tutto il corpus (indipendente dai filtri --article=/
        // --status=, per la stessa ragione di InternalLinkAuditService:
        // un titolo duplicato tra un articolo filtrato fuori e uno dentro
        // resta comunque un duplicato reale) sostituisce quella query
        // ripetuta.
        $duplicateTitleIndex = Article::query()
            ->pluck('title')
            ->map(fn (?string $title) => mb_strtolower(trim((string) $title), 'UTF-8'))
            ->countBy()
            ->all();

        // with('author'): authorCheck() legge $article->author — senza
        // eager loading, ogni articolo scatenerebbe una propria query
        // lazy (altro N+1 osservato in self-review, oltre a quello dei
        // titoli duplicati sopra).
        $query = Article::query()->with('author:id');

        if ($articleId !== null) {
            $query->where('id', $articleId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $articles = $query->orderBy('id')->get();

        $entries = $articles->map(fn (Article $article) => [
            'article' => $article,
            'report' => $this->checker->check($article, $duplicateTitleIndex),
        ]);

        return new EditorialQualityAuditSummary(
            analyzed: $entries->count(),
            readyCount: $entries->filter(fn (array $e) => $e['report']->level() === EditorialQualityReport::LEVEL_READY)->count(),
            attentionCount: $entries->filter(fn (array $e) => $e['report']->level() === EditorialQualityReport::LEVEL_ATTENTION)->count(),
            incompleteCount: $entries->filter(fn (array $e) => $e['report']->level() === EditorialQualityReport::LEVEL_INCOMPLETE)->count(),
            mostFrequentIssues: $this->mostFrequentIssues($entries),
            entries: $entries->all(),
        );
    }

    /**
     * @param  Collection<int, array{article: Article, report: EditorialQualityReport}>  $entries
     * @return array<int, array{code: string, label: string, count: int}>
     */
    private function mostFrequentIssues(Collection $entries): array
    {
        $counts = [];
        $labels = [];

        foreach ($entries as $entry) {
            foreach ($entry['report']->issues() as $issue) {
                $counts[$issue->code] = ($counts[$issue->code] ?? 0) + 1;
                $labels[$issue->code] = $issue->label;
            }
        }

        arsort($counts);

        $result = [];

        foreach ($counts as $code => $count) {
            $result[] = ['code' => $code, 'label' => $labels[$code], 'count' => $count];
        }

        return $result;
    }
}
