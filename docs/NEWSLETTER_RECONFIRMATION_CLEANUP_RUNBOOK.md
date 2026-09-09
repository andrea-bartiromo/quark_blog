# Runbook operativo — pulizia iscritti newsletter pendenti scaduti (#533)

Audit e hardening operativo di una funzionalità **già in `main`** (PR #533,
"Recupero prudente degli iscritti newsletter pendenti"). Questo documento non
introduce la funzionalità: la documenta per la prima volta in modo
operativo — cosa fa esattamente, cosa cancella, come sospenderla in
emergenza, e come si comporta un rollback — e registra l'hardening aggiunto
in questo audit (interruttore di emergenza, dry-run, log di audit).

## Cosa fa, in una frase

Ogni giorno alle 04:30 UTC, il comando schedulato
`newsletter:reconfirmation-cleanup` cancella dalla tabella `newsletter` gli
iscritti **pendenti** (`confirmed = false`) a cui è già stato inviato
**manualmente** almeno un sollecito di riconferma (`NewsletterReconfirmationService::send()`,
sempre un'azione admin esplicita da `/admin/newsletter`, mai automatica) e il
cui ultimo token è scaduto senza risposta.

**Non tocca mai:**
- un iscritto pendente che non ha mai ricevuto un sollecito (a quello va
  prima offerta la possibilità di riconfermare, non cancellato a priori);
- un iscritto pendente il cui sollecito è ancora valido (non scaduto);
- un iscritto già confermato, anche se ha una riga di riconferma scaduta
  nella sua storia.

## Dati coinvolti — retention, consenso, base legale

- **Cosa viene cancellato**: righe della tabella `newsletter` (l'indirizzo
  email e i metadati dell'iscrizione), per iscritti che non hanno mai
  completato il double opt-in originale nonostante un sollecito esplicito e
  una finestra di risposta (`reconfirmation.expires_after_days`, default 7
  giorni dall'ultimo sollecito).
- **Perché è una cancellazione legittima, non solo una pulizia tecnica**: un
  indirizzo `pending` non ha mai dato un consenso valido e verificato
  all'invio (il double opt-in non si è mai completato). Conservarlo a tempo
  indeterminato dopo aver già offerto — e fallito — un sollecito esplicito
  non ha una base per la conservazione dei dati; la cancellazione automatica
  qui implementata è coerente con un principio di minimizzazione dei dati,
  non un effetto collaterale.
- **Cosa NON viene mai cancellato da questo meccanismo**: nessun iscritto
  `confirmed = true`. Il double opt-in originale (`Newsletter::subscribe()`
  / `NewsletterController::confirm()`) resta un sistema completamente
  separato, mai letto né scritto da `NewsletterReconfirmationService`.
- **Audit trail**: ogni cancellazione registra ora (hardening di questo
  audit) un evento `Log::channel('newsletter_reconfirmation_audit')->info(
  'Rimozione iscritti newsletter pendenti scaduti.', ['subscriber_ids' =>
  [...], 'count' => N])` — mai l'indirizzo email, solo gli ID interni —
  oltre all'output già catturato in
  `storage/logs/newsletter-reconfirmation-cleanup.log`
  (`routes/console.php`, `->appendOutputTo(...)`). Prima di questo audit
  non esisteva alcuna traccia di QUALI righe fossero state rimosse da
  un'esecuzione, solo il conteggio nell'output del comando.
  - **Canale dedicato, non quello di default**: `config/logging.php`
    definisce `newsletter_reconfirmation_audit` con livello fisso a
    `'info'`, indipendente dalla variabile `LOG_LEVEL`. La configurazione
    di produzione documentata (`.env.production.example`) imposta
    `LOG_LEVEL=error`: se questo evento fosse scritto sul canale di
    default, verrebbe scartato in silenzio proprio nell'ambiente dove
    conta di più (revisione Codex su PR #536). File dedicato:
    `storage/logs/newsletter-reconfirmation-audit.log`.
  - **Atomicità selezione/cancellazione**: `deleteExpiredPending()`
    seleziona gli ID eleggibili con `lockForUpdate()` nella STESSA
    transazione della cancellazione, non in due passi separati. Senza
    questo, una cancellazione concorrente tra la selezione e la
    cancellazione avrebbe potuto lasciare nel log ID che quella specifica
    invocazione non aveva realmente rimosso (revisione Codex su PR #536)
    — gli ID registrati sono ora sempre esattamente quelli che
    quell'invocazione ha cancellato.

## Interruttore di emergenza (nuovo in questo audit)

`config('newsletter.reconfirmation.cleanup_enabled')`, valore
`NEWSLETTER_RECONFIRMATION_CLEANUP_ENABLED` — default **`true`** (nessun
comportamento esistente cambia finché non viene impostato esplicitamente a
`false` in produzione). Analogo per pattern a `NEWSLETTER_SEND_ENABLED` per
`newsletter:send`.

- **Cosa copre**: solo l'esecuzione automatica schedulata
  (`newsletter:reconfirmation-cleanup`, sia lanciata dallo scheduler sia
  invocata a mano da riga di comando). Se disattivato, il comando esce con
  successo senza toccare alcuna riga e stampa un avviso esplicito.
- **Cosa NON copre, deliberatamente**: l'azione manuale equivalente
  dell'editor da `/admin/newsletter`
  (`NewsletterController::cleanupExpiredPending()`) chiama il servizio
  direttamente, non passa dal comando — resta sempre disponibile perché è
  già una decisione umana deliberata su un singolo momento, non
  un'attivazione automatica non presidiata che l'interruttore esiste per
  poter sospendere.
- **Quando usarlo**: sospetto di un bug nella logica di eleggibilità, un
  incidente in corso sul sistema di invio email, o qualunque situazione in
  cui si vuole congelare la cancellazione automatica senza disabilitare
  l'intero scheduler Laravel (che governerebbe anche tutti gli altri job
  schedulati).

## Dry-run (nuovo in questo audit)

`php artisan newsletter:reconfirmation-cleanup --dry-run` riporta il
conteggio e gli ID degli iscritti che verrebbero rimossi, senza cancellare
nulla — utile per verificare l'impatto di una modifica ai parametri di
`config('newsletter.reconfirmation')` prima che la pulizia schedulata
successiva li applichi realmente.

## Sicurezza sotto esecuzioni concorrenti

- Lo scheduler applica `withoutOverlapping()` (`routes/console.php`,
  verificato da un test di regressione dedicato in questo audit) — due
  esecuzioni schedulate non partono mai in sovrapposizione.
- Indipendentemente da questo, `NewsletterReconfirmationService::deleteExpiredPending()`
  è idempotente per costruzione: la query di eleggibilità è ricalcolata a
  ogni chiamata, quindi una seconda esecuzione (schedulata, manuale da
  comando, o l'azione admin) che coincida con la prima non trova più nulla
  di eleggibile sulle righe già rimosse e non genera né un errore né una
  doppia cancellazione — provato da un test di regressione dedicato in
  questo audit (due esecuzioni consecutive, la seconda un no-op pulito).

## Piano di migration/rollback

- **Migration**: `2026_09_06_090000_create_newsletter_reconfirmations_table.php`,
  crea la sola tabella `newsletter_reconfirmations` (nuova, non tocca
  `newsletter` né alcuna tabella esistente). `down()` esegue
  `Schema::dropIfExists('newsletter_reconfirmations')` — pulito, testato,
  nessuna dipendenza esterna.
- **Cosa un rollback della migration NON ripristina**: righe di
  `newsletter` già cancellate da `deleteExpiredPending()` **non tornano
  indietro** in nessun caso — un rollback della migration rimuove solo la
  tabella di audit dei tentativi di riconferma, non ha alcun meccanismo per
  ricreare iscritti già cancellati. Questo è un rischio operativo reale da
  comunicare esplicitamente a chi pianifica un eventuale rollback: se lo
  scopo del rollback è "annullare l'effetto della funzionalità", un
  rollback della sola migration **non è sufficiente** — le cancellazioni
  già avvenute restano definitive. L'unica mitigazione preventiva
  disponibile oggi è un backup verificato (Backup V2,
  `php artisan backup:database-v2`) eseguito prima di qualunque deploy che
  porti questa funzionalità in produzione per la prima volta, per
  permettere un ripristino manuale mirato in caso di necessità — non
  automatizzato da questo repository.
- **Sequenza di deploy raccomandata** (quando autorizzato): backup
  MariaDB/MySQL verificato → migration → verifica
  `php artisan migrate:status` → deploy applicativo → monitoraggio del
  primo ciclo schedulato (04:30 UTC) con l'interruttore di emergenza pronto
  all'uso in caso di comportamento inatteso.

## Decisione

**GO condizionato** per il codice applicativo (logica di eleggibilità,
idempotenza, interruttore di emergenza, dry-run, audit trail: tutti
verificati con test di regressione dedicati, nessun finding aperto).

**Condizione non ancora soddisfatta**: nessun deploy di produzione che porti
questa funzionalità (già in `main`, stato di distribuzione reale non
verificato da questo audit — vedi `docs/FINAL_PRE_MERGE_AUDIT_2026_09.md`)
oltre lo stato attuale deve procedere senza un backup MariaDB/MySQL
verificato eseguito immediatamente prima, per il motivo esposto sopra
(cancellazioni non recuperabili da un rollback della sola migration).
Questa condizione è la stessa già registrata per qualunque release che
porti #533 in produzione, non una novità di questo audit — qui viene solo
resa esplicita insieme al meccanismo tecnico che la giustifica.
