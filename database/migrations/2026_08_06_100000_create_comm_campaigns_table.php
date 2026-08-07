<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->string('status')->default('draft');
            $table->string('title');
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->json('content')->nullable();

            // Nessun vincolo FK: le tabelle comm_templates e comm_sender_profiles
            // non esistono ancora in questo blocco (Blueprint B1). Colonne e
            // indici sono già predisposti per il vincolo FK che verrà aggiunto
            // quando quelle tabelle saranno create in un blocco successivo.
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('sender_profile_id')->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sending_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();

            // Nessun vincolo FK: la tabella editorial_series non esiste ancora
            // (Blueprint Sezione 5.3 — Serie Editoriali è una feature futura).
            $table->unsignedBigInteger('series_id')->nullable();

            // Impedisce il doppio invio se un comando/job viene eseguito due
            // volte per la stessa campagna (Blueprint Sezione 9 dell'audit).
            $table->string('idempotency_key')->nullable()->unique();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('series_id');
            $table->index('template_id');
            $table->index('sender_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_campaigns');
    }
};
