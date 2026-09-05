<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\ArticleBodyContaminationService;
use Illuminate\Console\Command;

class AuditArticleBodyContamination extends Command
{
    protected $signature = 'articles:audit-body-contamination
        {--article= : Limita l’audit a un articolo}
        {--dry-run : Calcola una bonifica ipotetica senza salvare}
        {--json : Restituisce JSON}';

    protected $description = 'Individua markup contaminato nei corpi articolo senza modificare dati';

    public function handle(ArticleBodyContaminationService $service): int
    {
        $query = Article::query()->select(['id', 'title', 'body'])->whereNotNull('body')->orderBy('id');

        if ($this->option('article') !== null) {
            $query->whereKey((int) $this->option('article'));
        }

        $rows = [];
        foreach ($query->cursor() as $article) {
            $findings = $service->findings((string) $article->body);

            if ($findings === []) {
                continue;
            }

            $row = [
                'id' => $article->id,
                'title' => $article->title,
                'findings' => $findings,
            ];

            if ($this->option('dry-run')) {
                $row['dry_run'] = $service->dryRun((string) $article->body);
            }

            $rows[] = $row;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'read_only' => true,
                'dry_run' => (bool) $this->option('dry-run'),
                'articles' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Audit contaminazione corpi — sola lettura');
        if ($rows === []) {
            $this->line('Nessun finding.');

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            $this->line(sprintf('#%d %s — %s', $row['id'], $row['title'], implode(', ', $row['findings'])));
            if (isset($row['dry_run'])) {
                $this->line(sprintf(
                    '  before=%s after=%s nodi_rimossi=%d anteprima=%s',
                    $row['dry_run']['before_hash'],
                    $row['dry_run']['after_hash'],
                    $row['dry_run']['removed_nodes'],
                    $row['dry_run']['preview'],
                ));
            }
        }

        return self::SUCCESS;
    }
}
