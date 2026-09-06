# Revisione critica PR #533 — recupero prudente iscritti newsletter pendenti (Prompt 101-105)

Analisi critica, in questa stessa sessione, del proprio lavoro precedente
(PR #533, squash-mergeata, sha `74483e6`) per rischio di consenso e
reversibilità — non un'autocelebrazione: l'obiettivo esplicito era
trovare cosa non andava, non confermare che tutto fosse a posto.

## Metodo

Riletti per intero, dal `main` attuale (non dai propri ricordi
dell'implementazione): `NewsletterReconfirmationService`,
`CleanupExpiredNewsletterPending`, la migration, `routes/console.php`,
e la convenzione di privacy sui log gia' stabilita altrove nel repository
(`docs/NEWSLETTER_FAILURE_PRIVACY_AUDIT.md` — mai l'indirizzo email in un
log, solo `subscriber_id`).

## Ipotesi di race condition esaminata e scartata

Prima ipotesi: `deleteExpiredPending()` legge gli ID eleggibili con una
query, poi cancella con una seconda query separata (`whereIn('id',
$ids)->delete()`, senza ri-verificare `confirmed=false` al momento della
cancellazione) — un iscritto che confermasse nella finestra tra le due
query verrebbe cancellato comunque nonostante il consenso appena dato?

Verificato a fondo: **no, non è sfruttabile**. Un iscritto entra nel set
eleggibile SOLO se OGNI suo token di riconferma è già scaduto al momento
della query di selezione. Ma `confirm()` rifiuta esplicitamente un token
scaduto (`$reconfirmation->isExpired()`) — quindi nel momento stesso in
cui qualcuno diventa eleggibile per la cancellazione, non possiede più
alcun link che gli permetterebbe di confermare con successo. Le due
condizioni (eleggibile per la cancellazione / poter ancora confermare)
sono per costruzione mutuamente esclusive. Nessuna correzione necessaria
qui — documentato per chi rivedesse questo codice in futuro e si ponesse
la stessa domanda.

## Rischio reale trovato: cancellazione irreversibile, non supervisionata, senza anteprima

Il comando schedulato (`routes/console.php`, ogni giorno alle 4:30,
**mai** guardato da un flag ambiente a differenza del backup SQLite
adiacente) esegue una `DELETE` reale e permanente (nessun soft-delete
sulla tabella `newsletter`) senza:

1. alcun modo di vedere in anticipo chi sarebbe stato rimosso;
2. alcuna traccia di quali righe specifiche un run avesse rimosso, oltre
   a un conteggio nel log;
3. alcuna finestra di conferma — corretto per un cron non supervisionato,
   ma significa che un errore di configurazione (es.
   `expires_after_days` cambiato per sbaglio a un valore troppo basso) si
   traduce immediatamente in cancellazioni reali, silenziose, non
   recuperabili se non da un backup completo del database.

Questo non è un incidente accaduto — nessuna prova che sia mai stato
cancellato un iscritto per errore. È un gap di controllo trovato per
ispezione del codice, coerente con l'istruzione esplicita di questo
blocco di prompt ("dry-run read-only prudente").

## Corretto in questo cambio

- **`php artisan newsletter:reconfirmation-cleanup --dry-run`**: mostra
  conteggio e ID interni di chi verrebbe rimosso, senza eliminare nulla.
  Riusa (`NewsletterReconfirmationService::eligibleForExpiredCleanup()`)
  la stessa identica query della cancellazione reale — un'anteprima che
  usasse una query diversa potrebbe mentire silenziosamente su cosa
  accadrebbe davvero, un rischio distinto ma altrettanto reale di un
  dry-run mal fatto.
- **Log strutturato prima di ogni cancellazione reale**: `subscriber_id`
  (mai l'email) di ogni riga in procinto di essere rimossa. Non rende la
  cancellazione reversibile — resta una `DELETE` reale — ma è l'unico
  modo per un operatore di sapere in seguito quali righe uno specifico
  run ha effettivamente rimosso.

## Cosa NON è stato cambiato, deliberatamente

- **Nessuna soft-delete aggiunta**: cambierebbe lo schema/contratto dati
  della tabella `newsletter` — decisione di prodotto, non un fix di
  auditing read-only, fuori perimetro di questa revisione.
- **Nessuna finestra di conferma interattiva sullo scheduler**: un cron
  non supervisionato per design non può bloccarsi su un prompt; la
  mitigazione corretta per questo contesto è l'anteprima disponibile
  on-demand (`--dry-run`), non un cambiamento al modo in cui lo
  scheduler stesso gira.
- **Nessun cambiamento a chi è eleggibile per la rimozione**: la logica
  di eleggibilità (mai un pendente mai sollecitato, mai un token ancora
  valido) resta esattamente quella della PR #533 originale — solo
  estratta in un metodo condiviso, non modificata nel comportamento
  (stessi test esistenti, tutti verdi, piu' i nuovi).

## Esito

Nessuna vulnerabilità di consenso reale trovata (l'ipotesi di race
condition non regge). Un gap reale di controllo operativo trovato
(cancellazione irreversibile, non supervisionata, senza anteprima) e
corretto con un'anteprima read-only e un log di audit — entrambi additivi,
nessuna modifica al comportamento di eleggibilità esistente. Nessuna
modifica a dati di produzione eseguita da questa sessione.
