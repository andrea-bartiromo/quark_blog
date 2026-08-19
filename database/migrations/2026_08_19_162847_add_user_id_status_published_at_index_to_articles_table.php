<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S6 — misurato su MariaDB reale con 10.000 articoli, tutti dello stesso
 * autore: AuthorController::show() (WHERE user_id = ? AND status =
 * 'published' ORDER BY published_at DESC) usava articles_user_id_foreign
 * (indice implicito creato da MariaDB per il vincolo FK — SQLite non ne
 * crea uno equivalente, la stessa query lì faceva SCAN completo, nessun
 * indice su user_id) ma con "Using filesort": 19,6ms medi su 15 esecuzioni,
 * EXPLAIN "Using index condition; Using where; Using filesort" (SQLite:
 * "USE TEMP B-TREE FOR ORDER BY", 11,9ms medi). Con l'indice composito:
 * 0,58ms medi su MariaDB (~34x più veloce), 0,05ms su SQLite (~220x),
 * EXPLAIN "Using where" senza più filesort su entrambi i driver.
 *
 * down() non è un semplice dropIndex(): vedi il suo docblock per il motivo
 * (rischio reale di rollback, riprodotto su MariaDB, non solo teorico).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'published_at'], 'articles_user_id_status_published_at_index');
        });
    }

    public function down(): void
    {
        // Riprodotto su MariaDB reale: creare l'indice composito sopra fa sì
        // che InnoDB rimuova automaticamente l'indice singolo preesistente
        // su user_id (articles_user_id_foreign, ora ridondante — coperto
        // dalla colonna iniziale del composito) e riassegni il vincolo FK
        // al nuovo indice composito. Un semplice dropIndex() del composito
        // fallirebbe quindi con l'errore MariaDB 1553 ("Cannot drop index
        // ... needed in a foreign key constraint"): va prima ripristinato un
        // indice che copra da solo user_id, poi si può rimuovere il
        // composito in sicurezza. Su SQLite questo passo è un no-op
        // innocuo (SQLite non lega gli indici ai vincoli FK in questo
        // modo), ma mantiene comunque lo stato equivalente a prima di up().
        Schema::table('articles', function (Blueprint $table) {
            $table->index('user_id', 'articles_user_id_foreign');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('articles_user_id_status_published_at_index');
        });
    }
};
