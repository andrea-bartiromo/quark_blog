# Mission 23 — Percorsi Subscriber Notification Deduplication

**Stato: VERIFIED_ALREADY_PRESENT.** Nessun file di codice è stato
modificato: la deduplicazione richiesta da questa missione esiste già, a
tre livelli indipendenti, con copertura di regressione già in produzione
su `main`. Costruire un nuovo meccanismo qui duplicherebbe un sistema già
corretto — esattamente ciò che le regole operative di questo batch
chiedono di evitare.

## Il requisito

"Percorsi subscriber notification deduplication": un abbonato a un
Percorso non deve mai ricevere due email per la stessa pubblicazione, sotto
nessuna delle cause plausibili (evento duplicato, job rieseguito, riga di
iscrizione duplicata).

## Evidenza raccolta

### 1. Livello iscrizione — un abbonato non può avere due righe attive per lo stesso Percorso

`database/migrations/2026_08_15_140000_create_content_cluster_subscribers_table.php`:

```php
$table->unique(['subscriber_id', 'content_cluster_id'], 'ccs_subscriber_cluster_unique');
```

Vincolo a livello di database (validato sia su SQLite sia su MariaDB — il
nome esplicito del vincolo esiste proprio perché il nome auto-generato da
Laravel superava il limite di 64 caratteri di MariaDB, un fallimento che
solo un test contro MariaDB reale avrebbe potuto rivelare). Un secondo
tentativo di iscrizione non può mai produrre una seconda riga: `ContentClusterSubscriptionController::activatePathSubscription()`
usa `insertOrIgnore()` per la prima iscrizione e una `UPDATE` guardata per
riattivare una riga disiscritta — mai una seconda riga per la stessa
coppia (subscriber, Percorso).

### 2. Livello registrazione delivery — l'evento duplicato collassa in un'unica riga

`CommunicationDelivery::computeDeliveryKey()` deriva la chiave da
`(channel, notification_type, subscriber_id, notifiable_type,
notifiable_id, event_key)` — **mai** dall'id della riga di iscrizione.
`CommunicationDeliveryService::registerDelivery()` scrive con
`insertOrIgnore()` contro l'UNIQUE su `delivery_key`. Conseguenza diretta,
verificata anche a mente: anche nell'ipotesi (già esclusa dal vincolo
sopra) di due righe di iscrizione per lo stesso abbonato e lo stesso
Percorso, `registerDelivery()` produrrebbe la STESSA `delivery_key` per
entrambe — una sola riga logica di delivery, sempre.

Questo è esattamente il comportamento già provato da
`tests/Feature/ContentClusters/PathContinuationNotificationTest.php::test_scenario_a_the_same_publication_event_dispatched_twice_produces_only_one_logical_delivery`:
`PathContinuationNotifier::notifyIfPublished()` chiamato due volte
sull'identico evento di pubblicazione produce `CommunicationDelivery::count() === 1`.

### 3. Livello esecuzione job — la stessa delivery non invia due volte

`test_scenario_b_the_same_queued_job_executed_twice_sends_only_once`:
`SendPathContinuationNotification` eseguito due volte sulla stessa
delivery invia una sola email (`Mail::assertSentCount(1)`), grazie al
claim atomico `pending→sending` (`UPDATE ... WHERE status = 'pending'`)
già documentato in `CommunicationDeliveryService::attemptSend()`.

### 4. Confine corretto: NON deduplicare tra Percorsi diversi

`test_an_article_in_two_updating_paths_generates_two_distinct_deliveries_and_two_emails`
prova l'altro lato della stessa proprietà: lo stesso articolo pubblicato
in due Percorsi diversi, entrambi in aggiornamento, genera due delivery
distinte e due email — perché `notifiable_id` (l'id del Percorso) è parte
della delivery_key. Deduplicare qui sarebbe un bug, non una funzionalità:
un abbonato a due Percorsi distinti si aspetta legittimamente due
notifiche separate.

### 5. Copertura addizionale già esistente nello stesso file

`test_editing_an_already_published_article_without_a_status_change_does_not_trigger_a_new_delivery`,
`test_scenario_c_retry_after_sent_never_resends`,
`test_scenario_f_a_delivery_stuck_in_sending_is_never_auto_resent_by_this_job` —
tutti scenari di possibile doppio-invio, tutti già coperti.

## Conclusione

La deduplicazione richiesta da questa missione è già implementata a tre
livelli indipendenti (vincolo DB sull'iscrizione, chiave univoca sulla
delivery, claim atomico sull'invio) e già provata da test end-to-end
esistenti che esercitano esattamente gli scenari "evento duplicato" e "job
rieseguito" nominati dal requisito. Aggiungere un nuovo livello di
deduplicazione (es. un lock applicativo, un controllo aggiuntivo in
`PathContinuationNotifier`) non chiuderebbe nessun gap reale — introdurrebbe
solo una seconda fonte di verità da mantenere sincronizzata con quella già
corretta, il tipo di duplicazione che le regole operative di questo batch
chiedono esplicitamente di evitare ("prefer recovery/convergence over
rewrites", "avoid duplicate implementations").

Nessun codice applicativo è stato modificato da questa missione.
