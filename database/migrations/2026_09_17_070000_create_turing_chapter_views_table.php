<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turing_chapter_views', function (Blueprint $table) {
            $table->id();

            // Append-only: nessun updated_at, un evento non viene mai
            // modificato dopo la creazione. Deliberatamente NESSUN
            // user_id/ip/session/visitor identifier — vedi il docblock del
            // modello TuringChapterView: privacy-first per scelta di scope
            // (Cantiere 68), stesso principio già in uso per
            // TrustKnowledgeStatementPreviewView (Cantiere 43) e
            // ArticleContinuationEvent.
            //
            // 'chapter' è una stringa, non una FK: i capitoli Turing sono
            // rotte fisse hardcoded in TuringPublicController/
            // TuringPageController (Cantiere 58 li ha confermati non
            // ancora un modello riordinabile a DB), non righe di una
            // tabella — non esiste alcuna chiave a cui puntare.
            $table->string('chapter', 40);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['chapter', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turing_chapter_views');
    }
};
