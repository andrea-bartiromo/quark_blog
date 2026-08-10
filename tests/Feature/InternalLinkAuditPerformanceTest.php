<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\User;
use App\Services\InternalLinking\InternalLinkAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FASE 21/28, missione Internal Linking V2: l'audit fa un solo passaggio
 * sull'intero corpus (vedi InternalLinkAuditService::buildLinkGraph()) per
 * calcolare correttamente gli incoming links — la soglia qui verifica che
 * il costo resti UNA query per il corpus più un numero di query COSTANTE
 * (non lineare nel numero di articoli) per le opportunità, non che sia
 * pari a zero.
 */
class InternalLinkAuditPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedRealisticDataset(int $count = 60, string $prefix = 'articolo-realistico'): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $slugs = [];

        for ($i = 0; $i < $count; $i++) {
            $slugs[] = $prefix.'-'.$i;
        }

        foreach ($slugs as $index => $slug) {
            $linkedSlugs = array_slice($slugs, max(0, $index - 3), 2);
            $body = '<p>'.str_repeat('Un corpo articolo lungo e realistico con molte parole scientifiche. ', 40).'</p>';

            foreach ($linkedSlugs as $linked) {
                if ($linked !== $slug) {
                    $body .= '<p>Vedi anche <a href="/articolo/'.$linked.'">questo approfondimento</a>.</p>';
                }
            }

            $status = match (true) {
                $index % 10 === 0 => Article::STATUS_DRAFT,
                $index % 7 === 0 => Article::STATUS_SCHEDULED,
                default => Article::STATUS_PUBLISHED,
            };

            Article::create([
                'user_id' => $author->id,
                'title' => 'Articolo realistico numero '.$index,
                'slug' => $slug,
                'excerpt' => 'Sommario di un articolo scientifico realistico.',
                'body' => $body,
                'category' => 'energia',
                'status' => $status,
                'published_at' => $status === Article::STATUS_DRAFT ? null : now()->addDays($index % 7 === 0 ? 1 : -1),
            ]);
        }

        // Qualche redirect storico, per esercitare quel ramo di classificazione.
        $target = Article::where('slug', $slugs[0])->firstOrFail();
        ArticleSlugRedirect::create(['old_slug' => 'vecchio-slug-'.$prefix, 'article_id' => $target->id]);
    }

    public function test_the_audit_query_count_stays_bounded_on_a_realistic_dataset(): void
    {
        $this->seedRealisticDataset(60);

        DB::enableQueryLog();
        $report = app(InternalLinkAuditService::class)->audit();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(60, $report->analyzed);
        $this->assertLessThan(
            20,
            $queryCount,
            "L'audit ha eseguito {$queryCount} query su un corpus di 60 articoli — soglia di sicurezza contro una regressione N+1 (un solo passaggio sul corpus è atteso, non una query per articolo)."
        );
    }

    /**
     * La prova che conta: il costo NON CRESCE linearmente con il numero di
     * articoli — confronto diretto tra due dimensioni del corpus, non solo
     * una soglia assoluta.
     */
    public function test_the_query_count_does_not_grow_linearly_with_the_number_of_articles(): void
    {
        $this->seedRealisticDataset(20, 'primo-lotto');
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(InternalLinkAuditService::class)->audit();
        $countWith20 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->seedRealisticDataset(80, 'secondo-lotto');
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(InternalLinkAuditService::class)->audit();
        $countWith100 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $countWith20,
            $countWith100,
            "Con 20 articoli: {$countWith20} query. Con 100: {$countWith100} query. Devono coincidere — un solo passaggio sul corpus, non una query per articolo."
        );
    }

    public function test_the_command_completes_quickly_on_a_realistic_dataset(): void
    {
        $this->seedRealisticDataset(60);

        $start = microtime(true);
        $this->artisan('content:internal-link-audit')->assertExitCode(0);
        $elapsedMs = (microtime(true) - $start) * 1000;

        $this->assertLessThan(5000, $elapsedMs, "L'audit ha impiegato {$elapsedMs}ms su 60 articoli — soglia di sicurezza generosa.");
    }
}
