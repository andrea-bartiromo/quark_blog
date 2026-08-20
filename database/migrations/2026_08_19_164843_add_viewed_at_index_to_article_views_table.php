<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S6 — HomeController::index() (la pagina a più alto traffico del
 * progetto) interroga article_views per il "trending 24h" filtrando
 * SOLO su viewed_at (nessun article_id nella WHERE) — unico consumer in
 * lettura di questa tabella oltre al log di scrittura stesso. L'indice
 * esistente, article_views_article_id_viewed_at_index, ha article_id come
 * colonna iniziale: inutilizzabile per un range-scan su un filtro che non
 * vincola affatto article_id, quindi MariaDB era costretta a uno scan
 * completo dell'intero indice (type: "index", non "range").
 *
 * article_views non ha alcuna retention/pulizia (una riga per ogni
 * pageview, per sempre — a differenza dell'aggregato article_daily_views,
 * pensato apposta per le dashboard, che questa query NON usa): il costo di
 * questo scan cresce quindi linearmente e senza limite col tempo, sulla
 * home page. Misurato su MariaDB reale: 14,5ms medi su 15 esecuzioni a
 * 100.000 righe (EXPLAIN: "Using where; Using index; Using temporary;
 * Using filesort", tipo "index" — scan dell'intero indice). Con l'indice
 * su viewed_at: 1,3ms medi (~11x più veloce), EXPLAIN tipo "range" (570
 * righe esaminate, solo la finestra di 24h effettivamente richiesta).
 *
 * Differenza SQLite/MariaDB misurata: su SQLite (10.000 righe) il query
 * planner continua a preferire l'indice composito esistente
 * (article_id, viewed_at) anche dopo l'aggiunta di questo indice, perché
 * lì è un covering index per questa query (contiene sia article_id sia
 * viewed_at, evitando l'accesso alla tabella) — nessun beneficio misurato
 * su SQLite, ma nessuna regressione: il costo aggiuntivo è solo la
 * write amplification di un indice in più (accettabile, vedi report).
 * Il beneficio reale è su MariaDB, il driver di produzione (docs/
 * DEPLOYMENT.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_views', function (Blueprint $table) {
            $table->index('viewed_at', 'article_views_viewed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('article_views', function (Blueprint $table) {
            $table->dropIndex('article_views_viewed_at_index');
        });
    }
};
