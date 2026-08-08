<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_sender_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('from_name');
            $table->string('from_email');
            $table->string('reply_to')->nullable();

            // Solo un'etichetta: nessuna credenziale vive qui (decisione
            // approvata in fase di progettazione — Comunicazione B4A). Il
            // transport reale resta quello già configurato in .env/
            // config/mail.php (SMTP), esattamente come per la Newsletter
            // legacy e per ogni altra integrazione esterna del progetto
            // (GITHUB_TOKEN, ANTHROPIC_API_KEY, ecc. — mai in una riga di
            // database, verificato: nessun precedente di cast `encrypted`
            // in tutto il codebase).
            $table->string('provider')->default('smtp');

            $table->string('status')->default('active');

            // Al più un profilo alla volta è il predefinito — stesso
            // pattern applicativo già in uso per Project::is_default_editorial
            // (unicità garantita nel modello, non da un vincolo DB).
            $table->boolean('is_default')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_sender_profiles');
    }
};
