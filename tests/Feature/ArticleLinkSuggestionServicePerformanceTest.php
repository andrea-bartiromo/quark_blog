<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use App\Services\ArticleLinkSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FASE 7 (missione qualità ranking collegamenti interni) — il motore dei
 * suggerimenti (App\Services\ArticleLinkSuggestionService::analyzeForSource())
 * non ha mai avuto una prova dedicata di scala, a differenza dell'audit
 * (vedi InternalLinkAuditPerformanceTest). Il candidate set è già limitato
 * per costruzione (ArticleLinkSuggestionService::MAX_CANDIDATES = 300, più
 * MAX_SCHEDULED_SAFE_CANDIDATES = 50 come tetto separato) tramite un LIMIT
 * SQL — qui si verifica empiricamente che quel limite si comporti come
 * atteso su un catalogo realistico: query e candidati caricati restano
 * piatti, non crescono con la dimensione dell'archivio.
 */
class ArticleLinkSuggestionServicePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function bulkInsertPublishedArticles(int $count, int $author, string $prefix): void
    {
        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'user_id' => $author,
                'title' => 'Articolo '.$prefix.' numero '.$i,
                'slug' => 'articolo-'.$prefix.'-'.$i.'-'.uniqid('', true),
                'excerpt' => 'Sommario di un articolo scientifico realistico numero '.$i.'.',
                'body' => '<p>'.str_repeat('Testo scientifico generico con parole comuni di riempimento. ', 30).'</p>',
                'category' => ['spazio', 'energia', 'salute', 'intelligenza-artificiale'][$i % 4],
                'status' => Article::STATUS_PUBLISHED,
                'featured' => false,
                'read_minutes' => 4,
                'views' => 0,
                'published_at' => $now->copy()->subMinutes($i),
                'verification_status' => 'unverified',
                'created_at' => $now->copy()->subMinutes($i),
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('articles')->insert($chunk);
        }
    }

    private function analyze(int $catalogSize): array
    {
        // Isolamento tra chiamate successive nello stesso test: senza
        // questo, la source di una chiamata precedente (stesso testo
        // esatto) resterebbe nel pool della chiamata successiva come
        // candidato che combacia quasi perfettamente con la nuova source —
        // un artefatto del test, non una vera query per candidato.
        ArticleLinkSuggestion::query()->delete();
        Article::query()->delete();

        $author = User::factory()->create(['role' => 'editor']);

        $this->bulkInsertPublishedArticles($catalogSize, $author->id, 'catalogo-'.$catalogSize);

        $source = Article::create([
            'user_id' => $author->id,
            'title' => 'Un paragrafo genuinamente specifico su token e Transformer',
            'slug' => 'sorgente-perf-'.$catalogSize.'-'.uniqid('', true),
            'body' => '<p>Un modello linguistico basato su token, Transformer, pretraining e generazione '.
                'del testo autoregressiva, con parametri appresi su enormi quantità di testo.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
            'read_minutes' => 4,
            'verification_status' => 'unverified',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = microtime(true);

        $suggestions = app(ArticleLinkSuggestionService::class)->analyzeForSource($source->fresh());

        $elapsedMs = (microtime(true) - $start) * 1000;
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [
            'query_count' => $queryCount,
            'elapsed_ms' => $elapsedMs,
            'suggestions_count' => $suggestions->count(),
        ];
    }

    public function test_query_count_does_not_grow_between_a_small_and_a_1000_article_catalog(): void
    {
        $small = $this->analyze(20);
        $large = $this->analyze(1000);

        $this->assertSame(
            $small['query_count'],
            $large['query_count'],
            "Con 20 articoli: {$small['query_count']} query. Con 1.000: {$large['query_count']} query. Devono coincidere — il pool di candidati è limitato da un LIMIT SQL (MAX_CANDIDATES), non dipende dalla dimensione del catalogo."
        );
    }

    public function test_analysis_completes_quickly_on_a_1000_article_catalog(): void
    {
        $result = $this->analyze(1000);

        $this->assertLessThan(
            3000,
            $result['elapsed_ms'],
            "L'analisi su un catalogo di 1.000 articoli ha impiegato {$result['elapsed_ms']}ms — soglia di sicurezza generosa."
        );
    }

    /**
     * FASE 7 — "se ragionevole localmente, ~10.000 articoli": eseguito qui
     * per dimostrare che il costo resta piatto ben oltre l'ordine di
     * grandezza minimo richiesto, non solo nel caso comodo a 1.000.
     */
    public function test_query_count_and_timing_stay_flat_at_a_10000_article_catalog(): void
    {
        $small = $this->analyze(20);
        $huge = $this->analyze(10000);

        $this->assertSame(
            $small['query_count'],
            $huge['query_count'],
            "Con 20 articoli: {$small['query_count']} query. Con 10.000: {$huge['query_count']} query. Devono coincidere."
        );

        $this->assertLessThan(
            10000,
            $huge['elapsed_ms'],
            "L'analisi su un catalogo di 10.000 articoli ha impiegato {$huge['elapsed_ms']}ms — soglia di sicurezza generosa."
        );
    }
}
