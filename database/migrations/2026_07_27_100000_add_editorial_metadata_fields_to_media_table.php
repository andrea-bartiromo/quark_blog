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
        Schema::table('media', function (Blueprint $table) {
            // Didascalia editoriale
            $table->text('caption')->nullable()->after('alt_text');

            // Credito/autore del file
            $table->string('credit')->nullable()->after('caption');

            // Nome della fonte
            $table->string('source')->nullable()->after('credit');

            // URL della fonte (lunghezza allineata al limite di validazione, max:2048)
            $table->string('source_url', 2048)->nullable()->after('source');

            // Licenza del file
            $table->string('license')->nullable()->after('source_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn([
                'caption',
                'credit',
                'source',
                'source_url',
                'license',
            ]);
        });
    }
};
