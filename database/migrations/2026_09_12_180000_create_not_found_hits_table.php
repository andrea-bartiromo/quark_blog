<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cantiere 24 (programma 100-cantieri Kairus). Nessun registro dei 404
 * reali esisteva prima di questa migrazione (verificato — nessuna
 * tabella, nessun modello, nessun hook). Il Cantiere 23
 * (RedirectAndCanonicalIntegrityAudit) verifica attivamente un insieme
 * noto di URL attesi; questo registro cattura invece i 404 REALMENTE
 * incontrati dal traffico pubblico, aggregati per path, cosi' un
 * editore puo' scoprire link rotti che nessun audit conosceva in
 * anticipo (referrer esterni, vecchi bookmark, refusi di battitura).
 *
 * La chiave di aggregazione e' `path_hash` (sha256 del path COMPLETO,
 * non troncato), non il path stesso, per due motivi (Codex, PR #572):
 * - la collation di produzione (MariaDB, utf8mb4_unicode_ci) confronta
 *   stringhe senza distinguere maiuscole/minuscole: un unique su `path`
 *   farebbe collidere "/Articolo/Uno" e "/articolo/uno" come se fossero
 *   lo stesso path, incrementando per errore il contatore dell'uno
 *   invece di creare una riga distinta per l'altro;
 * - path piu' lunghi di 255 byte che condividono lo stesso prefisso
 *   collelidono se troncati prima del confronto. sha256 e' calcolato
 *   sul path per intero: nessuna informazione persa per l'unicita', a
 *   prescindere da quanto sia lungo il path originale.
 * `path` resta una colonna `text` (mai troncata) per la sola
 * visualizzazione, senza indice ne' vincolo di unicita' diretto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('not_found_hits', function (Blueprint $table) {
            $table->id();
            $table->string('path_hash', 64)->unique();
            $table->text('path');
            $table->unsignedInteger('hits')->default(1);
            $table->text('last_referer')->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->timestamps();

            $table->index('hits');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_hits');
    }
};
