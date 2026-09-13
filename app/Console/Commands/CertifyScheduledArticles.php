<?php

namespace App\Console\Commands;

use App\Services\EditorialOperations\ScheduledArticlesCertificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CertifyScheduledArticles extends Command
{
    protected $signature = 'editorial:scheduled-certification
        {--days=14 : Ampiezza della finestra futura, da 1 a 31 giorni}
        {--from= : Istante iniziale ISO-8601 (UTC); omesso usa now()}
        {--json : Output JSON machine-readable}';

    protected $description = 'Certifica in sola lettura gli articoli programmati nella prossima finestra editoriale';

    public function handle(ScheduledArticlesCertificationService $report): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 31],
        ]);

        if ($days === false) {
            $this->error('--days deve essere un intero tra 1 e 31.');

            return self::FAILURE;
        }

        try {
            $from = filled($this->option('from'))
                ? Carbon::parse((string) $this->option('from'), 'UTC')->utc()
                : now()->utc();
        } catch (\Throwable) {
            $this->error('--from deve essere un istante ISO-8601 valido.');

            return self::FAILURE;
        }

        $payload = $report->report($from, $days);

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $rows = collect($payload['items']);
        $this->info("Programmati dal {$payload['from']} al {$payload['until']} (UTC): {$payload['count']}");
        $this->table(
            ['ID', 'Data UTC', 'Titolo', 'Health', 'Percorsi', 'Concept', 'Fonti', 'Collisione', 'Pagina'],
            $rows->map(fn (array $row) => [
                $row['id'],
                $row['published_at'],
                $row['title'],
                $row['content_health']['warning_count'].' warning',
                implode(', ', $row['percorsi']),
                implode(', ', $row['concepts']),
                $row['has_sources'] ? 'sì' : 'no',
                $row['collision_count'] > 1 ? (string) $row['collision_count'] : 'no',
                $row['public_page_expectation'],
            ])->all(),
        );

        return self::SUCCESS;
    }
}
