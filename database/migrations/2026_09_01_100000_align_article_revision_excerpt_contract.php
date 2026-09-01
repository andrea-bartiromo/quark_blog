<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->text('excerpt')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intenzionalmente non restringere di nuovo a VARCHAR(255): il
        // contratto applicativo accetta sommari fino a 300 caratteri e, dopo
        // l'uso della migration, possono esistere snapshot validi oltre 255.
        // Un rollback distruttivo fallirebbe in strict mode o richiederebbe
        // troncamento/perdita di dati. TEXT resta compatibile anche con il
        // codice precedente e rende il rollback sicuro.
    }
};
