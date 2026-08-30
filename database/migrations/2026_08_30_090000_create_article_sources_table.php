<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * EDITORIAL TRUST — fonti strutturate di un articolo (Missione 26).
     *
     * IMPATTO
     * Additiva e isolata: crea una sola tabella nuova, non tocca alcuna
     * colonna esistente. In particolare NON rimuove né migra:
     *   - articles.primary_sources (campo libero del pannello di verifica,
     *     max 500 caratteri, letto da EditorialQualityChecker e
     *     SourceImageAttributionHealthService);
     *   - la convenzione legacy "tutto ciò che segue il primo '---' nel body
     *     è il blocco Fonti" (articolo.blade.php + articles/partials/body).
     * Entrambi restano l'unica fonte di verità per gli articoli già
     * pubblicati finché non esiste una migrazione dati verificata: vedi
     * docs/EDITORIAL_SOURCES_V1.md §"Convivenza con il legacy".
     *
     * ROLLBACK
     * down() elimina la tabella. Nessun dato preesistente viene perso,
     * perché nessun dato preesistente viene spostato qui: le righe create
     * dopo il deploy andrebbero perse, quindi il rollback va eseguito solo
     * prima che la redazione inizi a compilare le fonti strutturate (o dopo
     * un export). Nessuna colonna di articles viene toccata, quindi il
     * rollback non può degradare gli articoli.
     *
     * INDICI / VINCOLI
     *   - FK article_id -> articles.id, cascadeOnDelete: le fonti non hanno
     *     vita propria fuori dall'articolo (stessa scelta già fatta per
     *     article_revisions.article_id).
     *   - index (article_id, position): unica lettura reale — "le fonti di
     *     QUESTO articolo, nell'ordine editoriale" — sia in admin sia nel
     *     rendering pubblico. Nessun unique su (article_id, position):
     *     un riordino a più righe passerebbe per stati intermedi in
     *     conflitto, e l'ordinamento è comunque deterministico grazie al
     *     tie-break su id.
     *   - Nessun unique su url/doi: lo stesso studio può essere citato da
     *     più articoli, e persino due volte nello stesso articolo con note
     *     editoriali diverse. I duplicati sono un WARNING editoriale (UI),
     *     non un errore di integrità.
     */
    public function up(): void
    {
        Schema::create('article_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();

            $table->string('title');

            // "autore o ente": una sola colonna e non due, perché per una
            // fonte istituzionale (ESA, ISS, Nature) non esiste una
            // distinzione utile fra i due — e due colonne quasi sempre
            // vuote a metà non aiutano né la redazione né il lettore.
            $table->string('author_or_org')->nullable();

            // 2048: stesso limite già adottato per articles.canonical_url e
            // articles.cover_source_url, non un numero nuovo.
            $table->string('url', 2048)->nullable();

            // DOI normalizzato in forma nuda (10.xxxx/yyyy), mai come URL:
            // vedi SourceReferenceNormalizer. Il link pubblico viene
            // ricostruito su https://doi.org/ al rendering.
            $table->string('doi', 255)->nullable();

            // Tipo dichiarato solo quando è davvero noto: il default
            // 'unknown' NON viene mai mostrato al lettore come etichetta
            // (Missione 28 — nessuna qualifica inventata).
            $table->string('source_type', 32)->default('unknown');

            // Date, non timestamp: una data di pubblicazione o consultazione
            // editoriale non ha un'ora significativa, e fingere una
            // precisione al secondo sarebbe falsa precisione.
            $table->date('published_on')->nullable();
            $table->date('accessed_on')->nullable();

            $table->text('editorial_note')->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['article_id', 'position'], 'article_sources_article_id_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_sources');
    }
};
