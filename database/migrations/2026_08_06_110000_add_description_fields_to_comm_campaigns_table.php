<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comm_campaigns', function (Blueprint $table) {
            // Stesso paio di campi di Project (description = a cosa serve la
            // campagna, internal_notes = appunti liberi di redazione): non
            // mostrati mai al destinatario, solo in Amministrazione.
            $table->text('description')->nullable()->after('preheader');
            $table->text('internal_notes')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('comm_campaigns', function (Blueprint $table) {
            $table->dropColumn(['description', 'internal_notes']);
        });
    }
};
