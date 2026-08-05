<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published', 'review', 'scheduled'])
                ->default('draft')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Riporta eventuali articoli 'scheduled' a 'draft' prima di restringere l'enum,
        // altrimenti la modifica fallirebbe per violazione del vincolo.
        DB::table('articles')->where('status', 'scheduled')->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        Schema::table('articles', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published', 'review'])
                ->default('draft')
                ->change();
        });
    }
};
