<?php

namespace App\Console\Commands;

use App\Models\NotFoundHit;
use App\Services\PublicPages\NotFoundHitTracker;
use Illuminate\Console\Command;

/**
 * Cantiere 24 (programma 100-cantieri Kairus). Espone
 * App\Services\PublicPages\NotFoundHitTracker da riga di comando: elenca
 * i path pubblici che hanno risposto 404 al traffico reale, ordinati per
 * numero di occorrenze. Sola lettura, non e' un gate di rilascio — serve
 * a un editore per decidere quali link rotti meritano un redirect
 * (App\Models\ArticleSlugRedirect copre solo gli articoli; categoria e
 * percorso rinominati restano 404 diretti, vedi Cantiere 23).
 */
class NotFoundHitsReport extends Command
{
    protected $signature = 'pages:not-found-registry
        {--limit=50 : Numero massimo di path da mostrare, ordinati per occorrenze decrescenti}
        {--json : Restituisce il risultato in formato JSON invece della tabella testuale}';

    protected $description = 'Elenca i path pubblici che hanno risposto 404 al traffico reale, aggregati per path e ordinati per occorrenze. Solo lettura.';

    public function handle(NotFoundHitTracker $tracker): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $hits = $tracker->topHits($limit);

        if ($this->option('json')) {
            $this->line(json_encode(array_map($this->toArray(...), $hits), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($hits === []) {
            $this->info('Nessun 404 registrato dal traffico reale.');

            return self::SUCCESS;
        }

        $this->table(
            ['Path', 'Occorrenze', 'Prima vista', 'Ultima vista', 'Ultimo referrer'],
            array_map(fn (NotFoundHit $hit) => [
                $hit->path,
                $hit->hits,
                $hit->first_seen_at->toDateTimeString(),
                $hit->last_seen_at->toDateTimeString(),
                $hit->last_referer ?? '—',
            ], $hits)
        );

        $this->newLine();
        $this->info(sprintf('%d path distinti mostrati (limite: %d).', count($hits), $limit));

        return self::SUCCESS;
    }

    /**
     * @return array{path: string, hits: int, first_seen_at: string, last_seen_at: string, last_referer: ?string}
     */
    private function toArray(NotFoundHit $hit): array
    {
        return [
            'path' => $hit->path,
            'hits' => $hit->hits,
            'first_seen_at' => $hit->first_seen_at->toIso8601String(),
            'last_seen_at' => $hit->last_seen_at->toIso8601String(),
            'last_referer' => $hit->last_referer,
        ];
    }
}
