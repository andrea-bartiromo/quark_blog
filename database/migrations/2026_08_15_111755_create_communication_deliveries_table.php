<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger di consegna GENERICO — prerequisito architetturale per
     * qualunque futura notifica Kairus (Percorsi "Avvisami quando
     * continua", Newsletter, notifiche future), non legato a un singolo
     * consumer. Deliberatamente SEPARATO da comm_sends: comm_sends è
     * strutturalmente vincolato a una campagna (campaign_id NOT NULL,
     * FK con cascadeOnDelete, unique(campaign_id, subscriber_id)) — un
     * evento di notifica per-risorsa come "un Percorso continua" non è
     * mai una campagna, quindi forzarlo dentro comm_sends avrebbe
     * richiesto o una campagna fittizia per ogni Percorso (uso improprio
     * di un concetto già chiaro altrove nel Sistema Comunicazione) o
     * rendere campaign_id nullable e reinterpretare comm_sends come
     * "generico" — una modifica semantica a una tabella già in uso dal
     * modulo Comunicazione (campagne/destinatari), più rischiosa di una
     * nuova tabella ben isolata. comm_subscribers resta invece l'unica
     * fonte di verità per identità/consenso — mai duplicata qui.
     *
     * Difesa primaria contro i duplicati: delivery_key, colonna singola
     * UNIQUE, hash deterministico dell'identità di consegna completa
     * (canale, tipo di notifica, iscritto, risorsa opzionale, versione/
     * evento opzionale — vedi CommunicationDelivery::computeDeliveryKey()).
     * Un hash singolo invece di un vincolo unique multi-colonna evita la
     * ben nota trappola SQL "NULL non è mai uguale a NULL" che altrimenti
     * lascerebbe passare due righe duplicate per un tipo di notifica
     * senza risorsa associata (es. un digest periodico) — la stessa
     * combinazione di colonne con notifiable_id NULL non violerebbe mai
     * un vincolo unique multi-colonna su SQLite o MariaDB, ma un hash
     * sempre non-NULL sì.
     *
     * L'unicità vive nel DATABASE, non solo nel codice applicativo:
     * insertOrIgnore() contro questo vincolo (stesso pattern già
     * collaudato in RecipientSnapshotService per comm_sends, PR #143) è
     * l'unica scrittura che crea una riga — un "claim" applicativo senza
     * vincolo DB (SELECT poi INSERT, o if(!sent) send()) avrebbe una
     * finestra di race reale sotto concorrenza reale, esattamente il
     * difetto che questa tabella esiste per evitare.
     */
    public function up(): void
    {
        Schema::create('communication_deliveries', function (Blueprint $table) {
            $table->id();

            $table->string('delivery_key', 64)->unique();

            $table->string('channel', 40);
            $table->string('notification_type', 80);

            $table->foreignId('subscriber_id')
                ->constrained('comm_subscribers')
                ->cascadeOnDelete();

            // Risorsa opzionale che ha originato la notifica (es. un
            // ContentCluster per "il percorso continua") — polimorfico
            // manuale, non morphs() di default, per poter indicizzare
            // separatamente dalla chiave di unicità (che vive solo su
            // delivery_key) e restare esplicito sul fatto che entrambe le
            // colonne sono nullable insieme (mai l'una senza l'altra).
            $table->string('notifiable_type', 120)->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();

            // Componente libera di "evento/versione" della identity di
            // consegna (es. una settimana ISO per un digest periodico, un
            // marcatore di transizione di stato per un evento puntuale) —
            // il ledger non interpreta questo valore, lo tratta come un
            // componente opaco della identity.
            $table->string('event_key', 191)->nullable();

            // Stato minimo indispensabile (vedi CommunicationDelivery):
            // pending (claim creato, non ancora tentato o sicuro da
            // ritentare) -> sending (tentativo in corso, claim atomico
            // pending->sending) -> sent (terminale, successo) oppure
            // failed (fallimento sincrono pre-invio, ritentabile solo
            // esplicitamente). Una riga bloccata su "sending" oltre il
            // tempo atteso rappresenta ONESTAMENTE una finestra di esito
            // incerto (processo morto dopo l'accettazione del provider ma
            // prima della scrittura DB) — non viene mai auto-ritentata da
            // questo layer, per non rischiare un secondo invio reale.
            $table->string('status', 20)->default('pending');

            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('attempts')->default(0);

            // Stesso campo già presente in comm_sends, stesso scopo:
            // riferimento facoltativo all'id del provider esterno, mai
            // usato per decidere l'idempotenza (solo diagnostica).
            $table->string('provider_message_id')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('subscriber_id');
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['channel', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_deliveries');
    }
};
