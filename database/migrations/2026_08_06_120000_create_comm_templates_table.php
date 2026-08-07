<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Nullable: un template senza tipo è proponibile per qualunque
            // tipo di campagna (Blueprint Sezione 7). Non un vincolo FK,
            // solo uno dei valori di CommunicationCampaign::typeOptions().
            $table->string('type')->nullable();

            $table->string('status')->default('active');

            // La versione "attiva" è un puntatore, non un booleano su ogni
            // riga versione: evita di dover ri-flaggare atomicamente N righe
            // quando cambia la versione corrente. La FK viene aggiunta in
            // coda, dopo che comm_template_versions esiste.
            $table->unsignedBigInteger('active_version_id')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_templates');
    }
};
