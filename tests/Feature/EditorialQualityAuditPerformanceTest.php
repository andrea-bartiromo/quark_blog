<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\EditorialQuality\EditorialQualityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FASE 37/45, missione Editorial Quality Gate: ogni EditorialQualityChecker::
 * check() lavora sui dati già caricati dalla singola query dell'audit
 * (nessuna query aggiuntiva per articolo, nessun Article::all() ripetuto).
 * La soglia qui è una rete di sicurezza contro regressioni N+1 future, non
 * un obiettivo "zero query".
 */
class EditorialQualityAuditPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedRealisticDataset(int $count, string $prefix): void
    {
        $author = User::factory()->create(['role' => 'editor']);

        for ($i = 0; $i < $count; $i++) {
            $status = match (true) {
                $i % 8 === 0 => Article::STATUS_DRAFT,
                $i % 5 === 0 => Article::STATUS_SCHEDULED,
                default => Article::STATUS_PUBLISHED,
            };

            $body = '<h2>Introduzione</h2><p>'.str_repeat('Testo scientifico realistico con molte parole diverse. ', 60).'</p>';

            if ($i % 3 === 0) {
                $body .= '<img src="/img/foto-'.$i.'.jpg">';
            }

            Article::create([
                'user_id' => $author->id,
                'title' => 'Articolo performance '.$prefix.' numero '.$i,
                'slug' => 'articolo-performance-'.$prefix.'-'.$i,
                'excerpt' => $i % 4 === 0 ? null : 'Sommario di lunghezza sufficiente per superare la soglia minima richiesta dal controllo.',
                'body' => $body,
                'category' => 'energia',
                'status' => $status,
                'published_at' => $status === Article::STATUS_DRAFT ? null : now()->addDays($i % 5 === 0 ? 1 : -1),
                'cover_image' => $i % 6 === 0 ? null : 'cover-'.$i.'.webp',
                'cover_alt' => $i % 6 === 0 || $i % 9 === 0 ? null : 'Descrizione cover '.$i,
                'primary_sources' => $i % 3 === 0 ? null : 'https://www.nature.com/articles/'.$i,
            ]);
        }
    }

    public function test_the_audit_query_count_stays_bounded_on_a_realistic_dataset(): void
    {
        $this->seedRealisticDataset(60, 'a');

        DB::enableQueryLog();
        $summary = app(EditorialQualityAuditService::class)->audit();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(60, $summary->analyzed);
        $this->assertLessThan(
            15,
            $queryCount,
            "L'audit ha eseguito {$queryCount} query su 60 articoli — soglia di sicurezza contro una regressione N+1."
        );
    }

    public function test_the_query_count_does_not_grow_linearly_with_the_number_of_articles(): void
    {
        $this->seedRealisticDataset(20, 'b1');
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(EditorialQualityAuditService::class)->audit();
        $countWith20 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->seedRealisticDataset(80, 'b2');
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(EditorialQualityAuditService::class)->audit();
        $countWith100 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $countWith20,
            $countWith100,
            "Con 20 articoli: {$countWith20} query. Con 100: {$countWith100} query. Devono coincidere."
        );
    }

    public function test_the_command_completes_quickly_on_a_realistic_dataset(): void
    {
        $this->seedRealisticDataset(60, 'c');

        $start = microtime(true);
        $this->artisan('content:quality-audit')->assertExitCode(0);
        $elapsedMs = (microtime(true) - $start) * 1000;

        $this->assertLessThan(5000, $elapsedMs, "L'audit ha impiegato {$elapsedMs}ms su 60 articoli.");
    }
}
