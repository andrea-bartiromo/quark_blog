# Recupero prudente degli iscritti newsletter pendenti

Flusso admin per ri-sollecitare gli iscritti con `confirmed = false`
("pendenti") senza mai attivarli automaticamente, e per rimuovere in modo
sicuro quelli che non hanno risposto in tempo. Il double opt-in
originale (`Newsletter::subscribe()` → email di conferma →
`NewsletterController::confirm()`) resta **completamente invariato**:
questa funzionalità non tocca mai la colonna `newsletter.token` né quel
controller.

## Principio guida

Un indirizzo email non confermato non ha mai dato un consenso verificabile.
Questa funzionalità non lo presume mai valido:

- **Nessuna attivazione automatica.** L'unico modo per marcare
  `confirmed = true` resta cliccare un link di conferma reale (originale
  o di riconferma) — nessun comando, job o cron attiva mai un iscritto.
- **Ogni invio è un'azione volontaria di un editor**, mai un batch o un
  automatismo. Il comando di pulizia programmato **elimina**, non invia
  né conferma mai nulla.
- **Reinvii limitati** (numero massimo a vita + cooldown tra un invio e
  il successivo, `config/newsletter.php`), per non trasformare il
  recupero in un sollecito insistente.
- **Un solo link valido alla volta.** Ogni nuovo invio invalida
  immediatamente il token dell'invio precedente non ancora confermato.

## Componenti

| Componente | Ruolo |
|---|---|
| `newsletter_reconfirmations` (migration) | Un tentativo di invio = una riga (token, invio, scadenza, conferma). Mai una colonna sovrascritta — è l'audit trail. |
| `NewsletterReconfirmation` (model) | Accesso/scope sulla tabella sopra. |
| `NewsletterReconfirmationService` | Unica logica di business: `send()`, `confirm()`, `deleteExpiredPending()`. |
| `NewsletterReconfirmationIneligibleException` | Motivo esplicito di rifiuto di un invio (già confermato / tentativi esauriti / cooldown attivo). |
| `NewsletterReconfirmationMail` | Email di sollecito (stesso branding Kairus, mai lo stesso testo dell'email di conferma originale). |
| `Admin\NewsletterController::sendReconfirmation()` | Azione admin: un invio, un iscritto. |
| `Admin\NewsletterController::cleanupExpiredPending()` | Azione admin: pulizia manuale on-demand. |
| `NewsletterController::reconfirm()` | Consumo pubblico del token (route separata da `newsletter.confirm`). |
| `newsletter:reconfirmation-cleanup` (comando) | Stessa pulizia, schedulata ogni giorno alle 4:30 (`routes/console.php`). |

## Revisione critica (Prompt 101-105, 150-prompt program): dry-run e audit

Questa pulizia è una `DELETE` reale e **irreversibile** (nessun
soft-delete su `newsletter`), eseguita ogni giorno **senza supervisione
umana**. Prima di questa revisione non esisteva alcun modo di vedere chi
sarebbe stato rimosso senza eseguire davvero la cancellazione, né alcuna
traccia di quali righe specifiche un run avesse rimosso oltre a un
conteggio.

Aggiunto in questa revisione, senza cambiare chi è eleggibile o quando
gira lo scheduler:

- **`php artisan newsletter:reconfirmation-cleanup --dry-run`** — mostra
  quanti iscritti e quali ID interni verrebbero rimossi, senza eliminare
  nulla. Riusa la stessa identica query di eleggibilità della
  cancellazione reale
  (`NewsletterReconfirmationService::eligibleForExpiredCleanup()`), così
  l'anteprima non può mai divergere da cosa accadrebbe davvero.
- **Log strutturato prima di ogni cancellazione reale**: gli ID interni
  (mai l'indirizzo email — stessa convenzione già in uso per gli altri
  fallimenti di invio newsletter di questo repository) delle righe che
  stanno per essere rimosse vengono scritti nel log applicativo. Non
  rende la cancellazione reversibile, ma è l'unico modo per un operatore
  di sapere in seguito quali righe uno specifico run ha effettivamente
  rimosso.

**Cosa resta invariato, deliberatamente**: nessuna soft-delete aggiunta
(cambierebbe lo schema e il contratto dei dati, fuori perimetro di una
revisione read-only/di auditing), nessuna finestra di conferma
interattiva aggiunta allo scheduler (che gira senza supervisione per
design), nessun cambiamento a chi è eleggibile per la rimozione.

## Chi viene eliminato dalla pulizia

Solo i pendenti a cui è stato **già inviato almeno un sollecito** e il
cui **ultimo** token è scaduto senza conferma. Un pendente mai
sollecitato non viene mai toccato: gli va prima data la possibilità di
riconfermare.

## Configurazione (`config/newsletter.php`)

```php
'reconfirmation' => [
    'expires_after_days' => 7,   // validità di un token di riconferma
    'max_attempts' => 3,         // solleciti massimi per iscritto, a vita
    'cooldown_hours' => 24,      // ore minime tra un invio e il successivo
],
```

## Invio reale durante test/deploy

- **Test**: ogni test usa `Mail::fake()` — nessuna email reale è mai
  inviata durante la suite. Le migration non inviano mai posta (creano
  solo schema).
- **Deploy**: nessuna migration, seeder o comando eseguito in deploy
  invia email — il comando di pulizia (`newsletter:reconfirmation-cleanup`)
  **elimina righe**, non invia mai un sollecito. L'unico punto da cui
  parte una `NewsletterReconfirmationMail` reale è
  `NewsletterReconfirmationService::send()`, raggiungibile solo da un
  clic esplicito di un editor autenticato in `/admin/newsletter`.

## Privacy

- **Nessun nuovo dato personale raccolto.** La tabella
  `newsletter_reconfirmations` contiene solo un token casuale e
  timestamp — nessun indirizzo IP, user agent o contenuto aggiuntivo.
- **Minimizzazione**: un token è a uso singolo e scade; una volta
  confermato o scaduto non permette più alcuna azione.
- **Retention**: i pendenti che non rispondono a un sollecito entro la
  scadenza vengono eliminati (non conservati indefinitamente "in
  attesa"). Le righe `newsletter_reconfirmations` di un iscritto
  eliminato vengono rimosse a cascata (`cascadeOnDelete` sulla foreign
  key).
- **Diritto di cancellazione**: invariato — resta possibile in
  qualunque momento tramite il link di disiscrizione o il pannello
  admin (`Elimina`), come da GDPR già documentato in
  `resources/views/admin/newsletter.blade.php`.
- **Nessuna riattivazione implicita**: se un iscritto risulta già
  `confirmed = true` per un'altra via prima che un sollecito venga
  aperto, `confirm()` non fa nulla — mai un "doppio consenso" o una
  sovrascrittura di uno stato già valido.

## Rollback

Ogni pezzo è isolato e reversibile senza toccare il double opt-in
originale:

1. **Disabilitare senza rimuovere**: rimuovere (o commentare) la voce
   `Schedule::command('newsletter:reconfirmation-cleanup')` in
   `routes/console.php` ferma la pulizia automatica; i pulsanti admin
   restano funzionanti finché non si rimuovono anche le route.
2. **Rimozione completa del codice applicativo**: eliminare
   - `app/Console/Commands/CleanupExpiredNewsletterPending.php`
   - `app/Exceptions/NewsletterReconfirmationIneligibleException.php`
   - `app/Mail/NewsletterReconfirmationMail.php`
   - `app/Models/NewsletterReconfirmation.php`
   - `app/Services/NewsletterReconfirmationService.php`
   - `resources/views/newsletter-reconfirmed.blade.php`
   - i 4 file di test in `tests/Feature/` (incluso `tests/Feature/Console/`)
   e rimuovere le route/azioni aggiunte in `routes/web.php`,
   `app/Http/Controllers/NewsletterController.php`,
   `app/Http/Controllers/Admin/NewsletterController.php`,
   `resources/views/admin/newsletter.blade.php`, il metodo
   `reconfirmations()`/`scopePending()` in `app/Models/Newsletter.php`,
   e la voce di scheduling in `routes/console.php`.
3. **Rollback dello schema**: `php artisan migrate:rollback` sulla
   migration `2026_09_06_090000_create_newsletter_reconfirmations_table`
   rimuove la tabella (`down()` fa `dropIfExists`) — nessun'altra
   tabella o colonna esistente viene toccata da questa funzionalità, quindi
   il rollback non ha effetti collaterali sul resto del sistema
   newsletter (`newsletter.token`/`confirmed`/`unsubscribe_token`/`source`
   restano esattamente come prima).
4. **Nessun dato da migrare all'indietro**: non essendoci alcuna colonna
   aggiunta alla tabella `newsletter` esistente, il rollback non richiede
   alcuna trasformazione di dati pre-esistenti.

## Test

- `tests/Feature/Admin/NewsletterReconfirmationTest.php` — eleggibilità
  (già confermato, tentativi esauriti, cooldown), invalidazione del
  token precedente, accesso riservato a un editor autenticato.
- `tests/Feature/NewsletterReconfirmationConfirmTest.php` — conferma
  pubblica (valido, scaduto, sconosciuto, mancante), isolamento tra
  iscritti diversi.
- `tests/Feature/NewsletterReconfirmationDuplicationTest.php` —
  anti-duplicazione: doppio invio ravvicinato, doppia visita dello
  stesso link, nessun sollecito dopo la conferma, un solo token mai
  valido alla volta.
- `tests/Feature/Console/CleanupExpiredNewsletterPendingTest.php` —
  il comando/l'azione admin eliminano solo i pendenti realmente
  scaduti e già sollecitati, mai un pendente mai sollecitato o un
  iscritto confermato.

Suite mirata: 23 test, tutti verdi. Suite newsletter esistente (double
opt-in, tracking, invio settimanale, ecc.): invariata, tutta verde.
