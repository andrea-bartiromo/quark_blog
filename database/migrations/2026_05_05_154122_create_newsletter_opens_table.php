<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_opens', function (Blueprint $table) {
            $table->id();

            // Nessuna foreign key qui (a differenza di com'era scritta
            // originariamente): il vincolo puntava alla tabella
            // 'newsletters' (plurale), che non è mai esistita — la tabella
            // reale è 'newsletter' (singolare, vedi
            // 2026_05_01_085647_create_newsletter_table.php). Su MySQL/
            // MariaDB questo fa fallire la CREATE TABLE con l'errore 1005/
            // 150 ("Foreign key constraint is incorrectly formed"),
            // bloccando qualunque `migrate` da zero — su SQLite (usato in
            // locale/test) il bug restava invisibile perché lì l'esistenza
            // della tabella referenziata non viene validata di default.
            //
            // La migration successiva (2026_05_05_162036_fix_newsletter_
            // opens_table, la vera correzione all'epoca) elimina e
            // ricrea questa tabella SENZA alcuna foreign key su
            // newsletter_id — quello è lo schema realmente in uso in
            // produzione oggi. Questa modifica allinea la CREATE TABLE
            // originale a quello stesso schema, cosi' che riesca anche su
            // un'installazione nuova invece di bloccare l'intera sequenza
            // di migration prima che quella correzione abbia la possibilità
            // di girare. In produzione, dove questa migration risulta già
            // "Ran", il file modificato non viene mai rieseguito: nessun
            // impatto sullo schema esistente.
            $table->unsignedBigInteger('newsletter_id')->nullable();

            $table->string('email')->nullable();
            $table->string('ip_hash')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_opens');
    }
};
