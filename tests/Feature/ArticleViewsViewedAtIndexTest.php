<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * S6: guardia di regressione per l'indice introdotto dalla migration
 * 2026_08_19_164843. HomeController::index() (pagina a più alto traffico)
 * interroga article_views filtrando SOLO su viewed_at, ma l'unico indice
 * preesistente (article_id, viewed_at) è inutilizzabile per quel filtro —
 * MariaDB era costretta a uno scan completo dell'intero indice, un costo
 * che cresce senza limite (article_views non ha retention). Benchmarkato
 * su MariaDB reale con 100.000 righe: 14,5ms medi (scan completo) -> 1,3ms
 * medi (range scan sulla sola finestra di 24h) — vedi report S6.
 */
class ArticleViewsViewedAtIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_views_table_has_a_standalone_viewed_at_index(): void
    {
        $indexes = collect(Schema::getIndexes('article_views'))->pluck('columns', 'name');

        $this->assertArrayHasKey('article_views_viewed_at_index', $indexes);
        $this->assertSame(['viewed_at'], $indexes['article_views_viewed_at_index']);
    }
}
