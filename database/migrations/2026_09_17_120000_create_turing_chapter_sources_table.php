<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turing_chapter_sources', function (Blueprint $table) {
            $table->id();

            // 'chapter' è una stringa, non una FK: i capitoli Turing sono
            // rotte fisse hardcoded in TuringPublicController/
            // TuringPageController (confermato non ancora un modello a DB
            // dal Cantiere 58), non righe di una tabella — non esiste
            // alcuna chiave a cui puntare. Stesso pattern già in uso per
            // turing_chapter_views (Cantiere 68).
            $table->string('chapter', 40);

            $table->string('label', 255);
            $table->string('url', 500);

            // Facoltativo di proposito: non ogni fonte citabile ha un anno
            // univoco attribuibile (es. una raccolta, un sito). Il mandato
            // editoriale in Architettura_Editoriale_v1.0.docx §7 ("sempre
            // attribuita con fonte e anno") resta una guida per l'editor,
            // non un vincolo di schema che rifiuterebbe una fonte reale
            // priva di anno.
            $table->string('year', 20)->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['chapter', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turing_chapter_sources');
    }
};
