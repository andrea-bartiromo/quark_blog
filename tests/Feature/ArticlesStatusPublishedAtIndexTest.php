<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * S4: guardia di regressione per l'indice composito introdotto dalla
 * migration 2026_08_17_180000. Benchmarkato su MariaDB reale con 10.000
 * articoli (~90% published): la query di Article::scopePublished() passava
 * da 19,2ms medi (filesort su ~9.000 righe) a 0,83ms medi (nessun
 * filesort) — vedi report S4 per i dettagli EXPLAIN prima/dopo.
 */
class ArticlesStatusPublishedAtIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_articles_table_has_the_composite_status_published_at_index(): void
    {
        $indexes = collect(Schema::getIndexes('articles'))->pluck('columns', 'name');

        $this->assertArrayHasKey('articles_status_published_at_index', $indexes);
        $this->assertSame(['status', 'published_at'], $indexes['articles_status_published_at_index']);
    }
}
