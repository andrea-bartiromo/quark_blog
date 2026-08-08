<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Storico degli slug articolo: nessun meccanismo di redirect esisteva prima
 * di questa migrazione (verificato — nessuna tabella, nessun modello,
 * nessuna route dedicata). Quando lo slug di un articolo cambia,
 * Article::booted() (vedi app/Models/Article.php) registra qui il vecchio
 * slug; ArticleController::show() lo consulta per un 301 quando lo slug
 * richiesto non corrisponde piu' a nessun articolo pubblicato.
 *
 * Include anche il backfill del caso reale segnalato da Search Console
 * (04/08/2026): il vecchio slug
 * "chirurgia-robotica-e-intelligenza-artificiale-il-futuro-e-sempre-piu-vicino"
 * restituiva 404 invece di reindirizzare all'articolo attuale
 * "chirurgia-robotica-e-intelligenza-artificiale-come-sta-cambiando-la-medicina".
 * Questo cambio di slug e' avvenuto prima che il meccanismo esistesse,
 * quindi non poteva essere catturato automaticamente: va seminato una
 * tantum qui, per dati (non per un redirect hardcoded nel routing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_slug')->unique();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        $article = DB::table('articles')
            ->where('slug', 'chirurgia-robotica-e-intelligenza-artificiale-come-sta-cambiando-la-medicina')
            ->first(['id']);

        if ($article) {
            DB::table('article_slug_redirects')->insert([
                'old_slug' => 'chirurgia-robotica-e-intelligenza-artificiale-il-futuro-e-sempre-piu-vicino',
                'article_id' => $article->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('article_slug_redirects');
    }
};
