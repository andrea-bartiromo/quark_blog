# Autosave e recovery locale dell'editor articoli

## Perché esiste

Un articolo Kairus scritto nell'editor (Admin o Redazione) e non ancora
salvato viene perso se la scheda/finestra del browser viene chiusa per
errore, la pagina viene ricaricata, il browser va in crash, la
connessione cade, la sessione Laravel scade (419) o il salvataggio fallisce
per un errore di rete. Questo è successo davvero — non è un problema
ipotetico.

## Cosa protegge

Ogni volta che un campo del form cambia (con un debounce di ~1,5s), il
form viene serializzato e salvato in `localStorage` del browser:

- `title`, `excerpt`, `body` (il testo TinyMCE, sincronizzato via
  `editor.save()` esattamente come già fa la preview SEO esistente)
- `category`, `secondary_categories[]` (solo Admin)
- `status`, `published_date`, `published_time`, `featured` (solo Admin)
- tutti i campi testuali della copertina (`cover_alt`, `cover_caption`,
  `cover_credit`, `cover_source`, `cover_source_url`, `cover_license`, e
  `cover_image` — il **nome file** testuale dalla libreria media, non
  l'upload)
- SEO/Open Graph/Twitter (`seo_title`, `seo_description`,
  `canonical_url`, `robots`, `og_*`, `twitter_*`)

## Cosa NON protegge (deliberatamente)

- **Il file immagine caricato** (`cover_image_upload`). I browser non
  permettono di reimpostare programmaticamente `input[type=file].files`
  per motivi di sicurezza, e serializzare il contenuto binario in
  base64 in `localStorage` sarebbe sproporzionato per un file di alcuni
  MB contro una quota tipica di ~5-10MB per origine. Se un file era
  selezionato al momento dell'ultimo autosave, il recupero mostra solo
  il suo nome e un avviso: va riselezionato manualmente.
- **Il token CSRF** (`_token`) e il metodo HTTP spoofato (`_method`) —
  mai persistiti, non servono al recupero e non devono mai vivere in
  `localStorage`.
- **Qualunque dato di sessione/autenticazione.**
- **Categorie/percorsi/progetti collegati** che non sono campi diretti
  del form (i "Progetti collegati" mostrati in coda al form Admin sono
  una vista, non un campo modificabile qui).
- **I suggerimenti di collegamento interno accettati**
  (`applied_link_suggestions`) — un'azione leggera, ripetibile in pochi
  click se persa, non essenziale al recupero del contenuto editoriale.

## Identità della bozza (namespace)

```
kairus:editor:v1:{surface}:{context}:{userId}
```

- `surface`: `admin` o `redazione` — evita che una bozza Admin e una
  Redazione per lo "stesso" URL logico si sovrascrivano a vicenda (sono
  form e permessi diversi).
- `context`: `new` per `/admin/articoli/nuovo` (nessun ID server ancora
  esistente) oppure l'ID numerico dell'articolo in modifica.
- `userId`: l'ID numerico dell'utente autenticato (da un
  `<meta name="kairus-user-id">`, mai un dato sensibile — lo stesso ID
  già visibile ovunque nell'interfaccia admin). Necessario per non far
  trapelare la bozza di un utente in quella di un altro su un browser
  condiviso (FASE 18).

Non include titolo/slug articolo, IP, email o altro identificatore
potenzialmente sensibile.

## Comportamento autosave

- Debounce di 1500ms: nessuna scrittura ad ogni singolo tasto.
- Indicatore discreto ("Bozza salvata localmente alle HH:MM"), regione
  `aria-live="polite"` — annunciato dagli screen reader senza dover
  spostare il focus.
- L'autosave **non cambia mai lo stato editoriale server-side**:
  scrive solo in `localStorage`, non invia mai una richiesta al server.
  "Autosave" non significa "pubblica".

## Recovery

Al caricamento della pagina, se esiste una bozza locale per la chiave
corrente e il suo contenuto **differisce** da quanto già renderizzato
dal server (`old()`/valori dell'articolo), viene mostrato un banner con
tre azioni:

- **Ripristina bozza** — applica i campi della bozza al form (incluso
  `tinymce.setContent()` per il corpo). Non elimina la bozza: resta
  comunque sovrascritta dal prossimo autosave, come atteso.
- **Ignora** — chiude il banner senza toccare il form. La bozza
  **non viene eliminata**: resta recuperabile al prossimo caricamento
  (chiudere il banner non è la stessa cosa di scartare il lavoro).
- **Elimina bozza** — rimozione esplicita, azione volontaria dell'utente.

Se il server rileva che l'articolo è stato modificato **dopo** l'ultimo
autosave locale (confrontando lo snapshot di `updated_at` catturato al
momento del salvataggio locale con quello attuale, esposto via
`data-editor-server-updated-at`), il banner mostra un avviso più
esplicito: ripristinare la bozza sovrascriverebbe nel modulo modifiche
più recenti fatte altrove — non un blocco, solo un avvertimento più
chiaro prima di agire.

## Pulizia dopo un salvataggio riuscito

`store()`/`update()` di entrambi i controller (Admin e Redazione)
reindirizzano sempre alla lista articoli con un flash `success` — mai a
una pagina che "conosce" ancora il form appena inviato. Lo script di
autosave vive **solo** sul form (`partials/article-autosave-script.blade.php`,
incluso da `admin/article-form.blade.php` e
`redazione/article-form.blade.php`): non è mai presente sulla pagina di
destinazione del redirect, quindi non può essere lui a fare la pulizia.

La pulizia vive invece in un partial separato,
`partials/kairus-draft-cleanup-script.blade.php`, incluso dai layout
(`layouts/admin.blade.php`, `layouts/redazione.blade.php`) — quindi
potenzialmente presente su **ogni** pagina di quella superficie, incluse
la lista articoli dove il redirect atterra davvero.

**Importante (corretto durante la certificazione, non nella stesura
iniziale):** l'inclusione di questo script è condizionata a un marcatore
di sessione **dedicato**, `kairus_draft_cleanup_context`, impostato
**solo** da `ArticleController::store()`/`update()` — mai dalla sola
presenza del flash `success` generico, che è condiviso da qualunque
azione riuscita su quella superficie (upload di un media, modifica di
una categoria, aggiornamento del profilo...). La prima versione legava
la pulizia alla presenza di un qualunque flash `success`: un'azione non
correlata avrebbe svuotato la bozza "nuovo articolo" di un utente con
un'altra bozza ancora in corso in un'altra scheda — un caso di
cancellazione di una bozza non correlata, esattamente ciò che questa
funzionalità esiste per prevenire. Trovato e corretto durante l'audit,
con un test di regressione dedicato per ciascuna superficie
(`test_an_unrelated_successful_..._action_never_triggers_the_draft_cleanup_script`).

- `store()` flasha `kairus_draft_cleanup_context = 'new'`: svuota solo lo
  slot "nuovo articolo" di questo utente su questa superficie.
- `update()` flasha `kairus_draft_cleanup_context = (string) $article->id`:
  svuota solo la bozza di quell'articolo specifico — **mai** anche lo
  slot "nuovo articolo" (un aggiornamento non è una creazione: non deve
  toccare una bozza di un articolo diverso, ancora in corso altrove).
- Il valore viaggia via un elemento marcatore dedicato e nascosto,
  `#kairus-draft-cleanup-marker` (`data-editor-surface`,
  `data-draft-cleanup-context`), separato dal `#kairus-flash-success`
  visibile all'utente — cambiare il testo del messaggio di successo non
  deve mai poter alterare accidentalmente il comportamento di pulizia.

## beforeunload

Attivo **solo** quando esistono modifiche non ancora scritte
dall'ultimo autosave (finestra tipica: sotto i 1500ms dall'ultimo
tasto). Mai durante un submit intenzionale (un flag `isSubmitting`
impostato nel listener `submit` disattiva l'avviso).

## Storage non disponibile / quota superata

Ogni scrittura è avvolta in try/catch. Se `localStorage` non è
disponibile (modalità privata restrittiva, quota superata) l'editor
mostra "Salvataggio automatico non disponibile in questo browser. Il
salvataggio manuale resta comunque disponibile." e continua a
funzionare normalmente — l'autosave è un miglioramento, mai un
prerequisito per poter scrivere o salvare.

## Multi-tab

Nessuna collaborazione realtime. Se un'altra scheda scrive la stessa
chiave di bozza, questa scheda mostra solo un avviso testuale
("modificato in un'altra scheda"); il comportamento resta
last-write-wins sulla singola chiave di `localStorage` — dichiarato
esplicitamente qui, non un vero merge. Una risoluzione dei conflitti più
robusta (token di revisione, blocco editing concorrente) è fuori scope
per questa notte: **follow-up esplicito**, non implementato.

## TTL

**14 giorni.** Le bozze locali vengono ripulite (di qualunque
utente/superficie/articolo trovate su questo browser, non solo la
chiave corrente) al caricamento di ogni form articolo. Motivazione: il
caso reale che ha motivato questa funzionalità è un crash/chiusura
accidentale — il recupero serve entro ore o giorni, non mesi. 14 giorni
copre comodamente un rientro da un weekend o una breve assenza senza
lasciare contenuto editoriale non pubblicato a vivere indefinitamente su
un browser, anche condiviso. Non viene svuotato al logout: farlo
vanificherebbe la protezione contro lo scenario reale di partenza
(sessione scaduta → login di nuovo → il lavoro deve essere ancora lì).

## Sicurezza/privacy

- `localStorage` è per-origine e per-browser: non condiviso tra
  dispositivi, non inviato al server.
- Namespace per utente (vedi sopra): un secondo utente sullo stesso
  browser condiviso non vede le bozze del primo (chiavi diverse).
- Nessun dato sensibile scritto (niente token CSRF, niente credenziali,
  niente sessione).
- Su un browser condiviso, il contenuto editoriale non ancora pubblicato
  resta comunque leggibile da chiunque abbia accesso agli strumenti di
  sviluppo del browser stesso finché non scade il TTL — stesso livello
  di esposizione di qualunque dato scritto in `localStorage` da
  qualunque sito. Non è un vettore nuovo introdotto da questa
  funzionalità (nessun cambiamento a CSRF/autenticazione).

## Cosa succede in caso di 419/sessione scaduta

Se il salvataggio fallisce per sessione scaduta, l'utente perde la
richiesta corrente ma **non** il lavoro: la bozza è già in
`localStorage` (autosave periodico indipendente dal submit). Dopo un
nuovo login, tornando sull'editor la bozza viene offerta in recupero
esattamente come per un crash del browser. Nessun indebolimento di
CSRF, nessuna estensione della durata della sessione.

## Limitazioni note

- **Un solo slot "nuovo articolo" per utente/superficie.** Se un utente
  ha due bozze di articoli *nuovi* aperte in due schede diverse
  contemporaneamente, condividono la stessa chiave `new` e l'ultima che
  scrive vince (nessun ID per disambiguarle prima del primo
  salvataggio). Editare articoli *esistenti* non ha questo limite (ogni
  ID ha la propria chiave). Follow-up possibile: un identificatore di
  bozza generato al primo focus sul form, non implementato stanotte per
  non allargare lo scope.
- **Multi-tab è un avviso, non una risoluzione dei conflitti** (vedi
  sopra).
- **Nessun backup server-side.** Se l'utente cancella i dati del
  browser prima di recuperare la bozza, il lavoro è perso. Vedi sotto.

## Percorso futuro: autosave server-side

Non implementato stanotte (avrebbe richiesto una modifica architetturale
più ampia — stato bozza server-side, gestione di scritture frequenti,
articoli già `published`/`scheduled`, race condition — fuori scope per
un intervento notturno). Se in futuro servisse:

- **Beneficio**: recovery cross-device, protezione da cancellazione dei
  dati del browser, backup reale.
- **Rischio**: scritture frequenti sul DB, necessità di uno stato
  "bozza" distinto dallo stato editoriale pubblico dell'articolo (un
  autosave non deve mai poter pubblicare/modificare un articolo già
  `published` senza un'azione esplicita), validazione più permissiva di
  quella richiesta per un salvataggio vero.
- **Approccio minimo suggerito**: una tabella `article_drafts` separata
  (mai scrivere direttamente su `articles` per un semplice autosave),
  con lo stesso namespace di identità già usato lato client, popolata
  via un endpoint dedicato (non gli endpoint `store`/`update` esistenti,
  che restano riservati al salvataggio esplicito).

## Percorso futuro: revision history

Kairus oggi ha `ActivityLog` (chi ha fatto cosa, quando — un log di
azioni con `subject_type`/`subject_id`/`subject_title`), **non** uno
storico di contenuto (nessuno snapshot del corpo articolo nel tempo,
nessun diff, nessun ripristino a una versione precedente). Non
costruito stanotte. Design minimo per il futuro:

- una tabella `article_revisions` (`article_id`, `user_id`, snapshot dei
  campi principali, `created_at`) scritta ad ogni salvataggio esplicito
  riuscito (non ad ogni autosave — sarebbe troppo rumoroso);
- una vista "Versioni" sull'editor che elenca le revisioni con
  autore/data, un confronto testuale semplice (non un diff carattere per
  carattere, sproporzionato per HTML editoriale) e un'azione "Ripristina
  questa versione" che precompila il form (non scrive direttamente su
  `articles` — l'utente deve comunque rivedere e salvare esplicitamente);
- integrazione naturale con l'autosave descritto qui: il banner di
  recovery potrebbe in futuro offrire "vedi anche le versioni salvate"
  come alternativa quando la bozza locale non è sufficiente (es. dopo
  aver pulito i dati del browser).
