<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Measurement Closeout (Missione 2) — log append-only degli eventi editoriali
 * di continuità sotto contratto canonico versionato
 * (App\Services\Telemetry\EditorialEventContract).
 *
 * PERCHÉ UNA SECONDA TABELLA E NON article_continuation_events.
 * article_continuation_events (Growth S2) resta la fonte di verità del solo
 * funnel "Continua da qui" e NON viene toccata da questa migration: non
 * contiene alcun identificativo di sessione, per scelta esplicita del suo
 * autore. Le metriche richieste da Missione 3 (sessioni con ≥2 articoli /
 * sessioni con ≥1 articolo) e Missione 4 (transizioni seguite / view in cui
 * la transizione era disponibile) sono per definizione metriche DI SESSIONE:
 * non sono ricostruibili, nemmeno in linea di principio, da una tabella che
 * non correla due eventi della stessa sessione. Aggiungere una colonna
 * sessione a article_continuation_events avrebbe cambiato retroattivamente il
 * significato delle righe storiche (tutte NULL, quindi indistinguibili da
 * "una sessione per evento") e rotto la promessa di privacy scritta nel suo
 * docblock. Questa tabella è quindi additiva e i due dataset restano leggibili
 * in modo indipendente.
 *
 * IMPATTO: solo CREATE TABLE. Nessuna tabella esistente viene alterata,
 * nessun dato legacy viene letto, riscritto o migrato. L'assenza della tabella
 * (prima della migration) non rompe nulla: EditorialContinuityRecorder è
 * fail-safe e la lettura pubblica prosegue anche se la scrittura fallisce.
 *
 * ROLLBACK: `down()` esegue un DROP TABLE. Poiché nessun'altra tabella ha una
 * FK verso questa, il drop non ha dipendenze e non lascia orfani. Il rollback
 * perde gli eventi raccolti nel frattempo (dato di telemetria aggregata, non
 * di dominio editoriale: nessun contenuto, nessun iscritto, nessuna decisione
 * redazionale vive qui). Le metriche Measurement Closeout tornano a
 * INSUFFICIENT_DATA, che è esattamente lo stato dichiarato prima della
 * migration — nessuno stato intermedio silenziosamente sbagliato.
 *
 * PIANO DI STAGING: vedi docs/MEASUREMENT_CLOSEOUT.md, sezione "Piano di
 * applicazione in staging". Questa migration NON è stata applicata ad alcun
 * ambiente diverso dai database di test locali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_continuity_events', function (Blueprint $table) {
            $table->id();

            // Nome stabile dell'evento, allowlisted da
            // EditorialEventContract::EVENT_NAMES. 48 caratteri coprono con
            // margine il più lungo nome previsto ('article.transition_available',
            // 29) senza sprecare spazio in indice.
            $table->string('event_name', 48);

            // Versione dello schema di payload con cui l'evento è stato
            // scritto. Un consumer che legge righe di versioni diverse deve
            // poterle distinguere senza indovinare dalla forma dei campi.
            $table->unsignedSmallInteger('schema_version');

            // Pseudonimo di sessione: HMAC-SHA256 dell'id di sessione Laravel
            // con la APP_KEY (vedi ContinuitySessionKey). MAI l'id di sessione
            // in chiaro, mai un IP, mai un cookie applicativo. Non reversibile
            // senza la APP_KEY e comunque privo di valore identificativo: l'id
            // di sessione stesso è già un valore casuale non legato a una
            // persona, e ruota alla scadenza del cookie di sessione.
            $table->char('session_key', 64);

            // Articolo a cui l'evento si riferisce (la pagina vista, o la
            // pagina che ospitava il controllo di transizione).
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();

            // Destinazione della transizione, quando l'evento ne descrive una
            // (article.transition_available). Serve a Missione 4 per derivare
            // se la transizione è poi stata seguita davvero.
            $table->foreignId('target_article_id')->nullable()->constrained('articles')->nullOnDelete();

            // Percorso di contesto, quando applicabile.
            $table->foreignId('content_cluster_id')->nullable()->constrained('content_clusters')->nullOnDelete();

            // 'previous' | 'next' | 'continua_da_qui' | 'pillar' |
            // 'article_in_path' — allowlisted da EditorialEventContract.
            $table->string('transition_type', 24)->nullable();

            // Tassonomia sorgente allowlisted (Missione 5). MAI un referrer
            // completo, mai una query string: solo il canale normalizzato.
            $table->string('source_channel', 16);

            // Posizione 1-based nella sequenza pubblica del Percorso, quando
            // l'evento avviene dentro un Percorso.
            $table->unsignedInteger('context_position')->nullable();

            $table->timestamp('occurred_at');

            // Nomi espliciti e brevi: il nome auto-generato da Laravel per
            // indici compositi su questa tabella supererebbe il limite di 64
            // caratteri per identificatore di MySQL/MariaDB (stessa lezione
            // già documentata in
            // 2026_08_23_120000_create_article_continuation_events_table.php).

            // Missione 3: raggruppa gli eventi di una sessione in ordine
            // temporale — la query di second-read rate scandisce esattamente
            // questo indice.
            $table->index(['session_key', 'occurred_at'], 'ece_session_occurred_idx');

            // Missioni 4/5/6: tutte le aggregazioni filtrano per tipo di
            // evento dentro una finestra temporale esplicita.
            $table->index(['event_name', 'occurred_at'], 'ece_name_occurred_idx');

            // Ultimo evento registrato (data freshness della dashboard) e
            // bounding della finestra senza toccare gli altri indici.
            $table->index('occurred_at', 'ece_occurred_idx');

            // Segmentazione per Percorso (Missione 4/6).
            $table->index(['content_cluster_id', 'event_name', 'occurred_at'], 'ece_cluster_name_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_continuity_events');
    }
};
