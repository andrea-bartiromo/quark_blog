<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Testo alternativo per l'immagine di copertina
            $table->string('cover_alt')->nullable()->after('cover_image');

            // Didascalia editoriale
            $table->text('cover_caption')->nullable()->after('cover_alt');

            // Credito/autore dell'immagine
            $table->string('cover_credit')->nullable()->after('cover_caption');

            // Nome della fonte
            $table->string('cover_source')->nullable()->after('cover_credit');

            // URL della fonte (lunghezza allineata al limite di validazione, max:2048)
            $table->string('cover_source_url', 2048)->nullable()->after('cover_source');

            // Licenza dell'immagine
            $table->string('cover_license')->nullable()->after('cover_source_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn([
                'cover_alt',
                'cover_caption',
                'cover_credit',
                'cover_source',
                'cover_source_url',
                'cover_license',
            ]);
        });
    }
};
