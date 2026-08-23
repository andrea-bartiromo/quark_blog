<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_console_queries', function (Blueprint $table) {
            $table->id();

            // Riga grezza importata da un export CSV di Search Console
            // (dimensioni query+pagina). Nessuna chiamata API — l'unica via
            // di ingestione in questa v1 è l'import manuale da parte della
            // redazione (vedi SearchConsoleCsvImporter).
            $table->string('query', 255);
            $table->string('page_url', 500);

            // Nullable: valorizzato da SearchConsoleQueryArticleMatcher solo
            // quando page_url corrisponde a un articolo pubblicato reale.
            // Restare null è un segnale editoriale legittimo (nessuna
            // landing page dedicata), non un dato mancante da correggere.
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();

            $table->unsignedInteger('clicks');
            $table->unsignedInteger('impressions');

            // CTR cosi' come riportato dall'export (0-1), non ricalcolato:
            // Search Console arrotonda internamente prima dell'export,
            // clicks/impressions locale non sarebbe sempre identico.
            $table->decimal('ctr', 6, 4);

            // Posizione media, frazionaria per costruzione in Search
            // Console (media pesata su piu' impression in periodi/device
            // diversi).
            $table->decimal('position', 6, 2);

            // Intervallo di date del periodo esportato (dichiarato
            // dall'operatore all'import, Search Console non lo include nel
            // CSV): necessario per il confronto "rising query" tra due
            // import successivi.
            $table->date('period_start');
            $table->date('period_end');

            // Raggruppa le righe di uno stesso import per periodo, cosi' un
            // secondo import per lo stesso intervallo di date sovrascrive
            // (non duplica) invece di sommarsi silenziosamente.
            $table->string('import_batch', 64);

            $table->timestamp('imported_at');

            $table->index(['query', 'period_start'], 'scq_query_period_idx');
            $table->index(['article_id', 'period_start'], 'scq_article_period_idx');
            $table->index('import_batch', 'scq_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_queries');
    }
};
