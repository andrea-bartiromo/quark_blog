<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\EditorialQuality\EditorialQualityChecker;
use App\Services\EditorialQuality\FeaturedArticleCertificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Cantiere 36 (programma 100-cantieri Kairus), dipende dai Cantieri 30-35.
 * Stesso pattern già in uso per category:publication-readiness (Cantiere
 * 12-13): sola lettura, mai bloccante, sicuro da eseguire in produzione.
 * Fotografa OGNI articolo correntemente marcato "in evidenza" (hero
 * homepage) — tipicamente zero o uno, ma FINDING_MULTIPLE_FEATURED
 * esiste proprio perché più di uno è un caso reale non impedito altrove.
 */
class FeaturedArticleCertificationAudit extends Command
{
    protected $signature = 'articles:featured-certification-audit
        {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Fotografa gli articoli marcati "in evidenza" (hero homepage) e la loro certificazione primo piano (sola lettura, sicuro in produzione)';

    public function handle(EditorialQualityChecker $qualityChecker, FeaturedArticleCertificationService $certification): int
    {
        $articles = Article::query()->where('featured', true)->orderBy('id')->get();

        $reports = $articles->map(function (Article $article) use ($qualityChecker, $certification) {
            $qualityReport = $qualityChecker->check($article);

            return [
                'article_id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'status' => $article->status,
                'quality_level' => $qualityReport->levelLabel(),
                'findings' => $certification->evaluate($article, $qualityReport)['findings'],
            ];
        })->values();

        if ($this->option('json')) {
            $this->line((string) json_encode($reports->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderTextReport($reports);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $reports
     */
    private function renderTextReport($reports): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>CERTIFICAZIONE PRIMO PIANO — KAIRUS</>');
        $this->line('(sola lettura — nessuna modifica applicata)');
        $this->newLine();

        if ($reports->isEmpty()) {
            $this->info('Nessun articolo marcato "in evidenza" al momento.');

            return;
        }

        foreach ($reports as $r) {
            $this->line("<fg=cyan;options=bold>#{$r['article_id']} — {$r['title']}</> (<fg=gray>{$r['slug']}</>)");
            $this->line("  Stato: {$r['status']} · Qualità editoriale: {$r['quality_level']}");

            if ($r['findings'] === []) {
                $this->line('  <fg=green>Nessuna criticità rilevata.</>');
            } else {
                foreach ($r['findings'] as $finding) {
                    $this->line('  <fg=yellow>'.FeaturedArticleCertificationService::label($finding).'</>');
                }
            }

            $this->newLine();
        }

        $withFindings = $reports->filter(fn ($r) => $r['findings'] !== [])->count();

        $this->line('<fg=cyan;options=bold>Riepilogo</>');
        $this->line('  Articoli "in evidenza": '.$reports->count());
        $this->line("  Con almeno una criticità: {$withFindings}");
    }
}
