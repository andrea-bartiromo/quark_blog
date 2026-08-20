<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * S6: guardia di regressione per l'indice composito introdotto dalla
 * migration 2026_08_19_162847. Benchmarkato su MariaDB reale con 10.000
 * articoli dello stesso autore: AuthorController::show() passava da 19,6ms
 * medi (filesort, indice singolo su user_id) a 0,58ms medi (nessun
 * filesort) — vedi report S6 per i dettagli EXPLAIN prima/dopo su
 * SQLite e MariaDB.
 */
class ArticlesUserIdStatusPublishedAtIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_articles_table_has_the_composite_user_id_status_published_at_index(): void
    {
        $indexes = collect(Schema::getIndexes('articles'))->pluck('columns', 'name');

        $this->assertArrayHasKey('articles_user_id_status_published_at_index', $indexes);
        $this->assertSame(['user_id', 'status', 'published_at'], $indexes['articles_user_id_status_published_at_index']);
    }
}
