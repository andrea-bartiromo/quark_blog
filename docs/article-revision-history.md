# Revision History degli articoli

## Perché esiste

Un salvataggio esplicito riuscito può comunque essere **sbagliato**: un
redattore Redazione cancella per errore diversi paragrafi del corpo e
clicca "Salva", oppure un editor Admin sovrascrive un titolo con un
valore vuoto per un incidente di copia-incolla. In entrambi i casi il
salvataggio va a buon fine — non c'è nulla da "recuperare" nel senso
dell'autosave locale (vedi `docs/editor-autosave.md`), perché non c'era
alcun lavoro non salvato: il contenuto sbagliato È il contenuto salvato.

Prima di questa funzionalità, Kairus non aveva alcun modo di tornare
indietro da questo scenario se non un intervento diretto sul database
(o il ripristino di un intero backup — vedi `ProjectBackup`, uno
strumento operativo per l'intera applicazione, non una funzione
editoriale per un singolo articolo).

## Cosa protegge (e cosa NON protegge)

- **Protegge**: un salvataggio esplicito riuscito ma editorialmente
  sbagliato — il caso descritto sopra.
- **NON protegge** il lavoro non ancora salvato: quello è il compito
  dell'autosave locale (`docs/editor-autosave.md`), un meccanismo
  interamente diverso, mai in conflitto con questo.

## Audit prima dell'implementazione

Prima di costruire qualunque cosa, Kairus è stato verificato per capire
cosa esistesse già:

- **`ActivityLog`** (`app/Models/ActivityLog.php`): registra chi ha
  fatto cosa, quando (`subject_title` è una stringa — solo il titolo al
  momento dell'azione, non un campo strutturato riutilizzabile per un
  ripristino). Nessun body, nessuna categoria, nessun campo SEO. Un log
  di audit, non uno storico di contenuto.
- **`CommunicationTemplateVersion`** (`comm_template_versions`): un
  precedente reale di versioning nel codebase, ma per i template delle
  comunicazioni — un dominio diverso (versioni nominate, create
  esplicitamente dall'utente per bozze non ancora attive, non uno
  snapshot automatico ad ogni salvataggio).
- **Nessuna tabella, colonna, observer o pacchetto** copre oggi lo
  storico di contenuto di un `Article`. Il gap era reale, non
  ipotetico.

## Policy dello snapshot: PRE-CHANGE

Ogni riga di `article_revisions` rappresenta lo stato dell'articolo
**immediatamente PRIMA** di un salvataggio esplicito che lo ha
modificato — mai un pareggiamento della bozza locale, mai uno snapshot
dello stato *dopo* il salvataggio.

Motivo della scelta (vs. post-change): rende il ripristino banale. La
revisione più recente è sempre "come appariva l'articolo prima
dell'ultima modifica" — esattamente ciò che serve subito dopo un
salvataggio incidentale, senza dover ragionare su quale riga tra tante
rappresenti lo stato "buono".

Una revisione viene creata **solo** se il salvataggio cambia
effettivamente almeno uno dei campi coperti (vedi sotto): un doppio
click su "Salva" senza modifiche non deve accumulare righe identiche.

## Campi coperti dallo snapshot

`title`, `excerpt`, `body`, `category`, `status`.

Scelta deliberatamente minima, non una copia cieca di ogni colonna di
`articles`:

- Questi cinque campi sono esattamente il contenuto editoriale che uno
  scenario di "cancellazione accidentale" può distruggere.
- **Esclusi**: i campi SEO/OG/Twitter (metadati secondari, non il
  contenuto editoriale principale), i campi copertina (nomi di file, non
  contenuto binario — ma comunque secondari rispetto al testo), i campi
  puramente derivati (`read_minutes`, `views`). Nessun blob, nessun file
  binario, nessun segreto.
- Se in futuro emergesse un bisogno concreto di coprire anche i campi
  SEO, la tabella può essere estesa con una migrazione additiva — non
  è stato fatto ora per non allargare lo scope oltre l'evidenza
  raccolta.

## Semantica del ripristino (non negoziabile)

`ArticleRevisionService::restore()`, dentro un'unica transazione:

1. **Snapshotta lo stato ATTUALE** dell'articolo come nuova revisione
   (stesso `recordIfChanged()` usato per un salvataggio normale) —
   così un ripristino non distrugge mai in modo irreversibile lo stato
   da cui si ripristina: resta a sua volta recuperabile.
2. **Applica** i valori della revisione selezionata all'articolo.
3. **Registra** in `ActivityLog` chi ha ripristinato cosa (audit trail,
   stesso meccanismo già usato per ogni altra azione editoriale).
4. Tutto **dentro una transazione**: un fallimento a metà non lascia mai
   l'articolo in uno stato parzialmente ripristinato.

Le invarianti del modello `Article` (es. `published_at` forzato a null
per stato draft/review, vedi `Article::booted()`) restano applicate
anche durante un ripristino: i valori storici passano dallo stesso
`$article->update()` di sempre, non da una scrittura diretta che le
aggirerebbe.

## Retention

Nessuna cancellazione automatica in questa v1: il volume stimato è
contenuto (una riga per salvataggio che modifica davvero il contenuto,
non per ogni autosave — che non scrive mai qui) e la tabella non ha
blob né file binari. Se in futuro il volume dovesse diventare un
problema reale, una policy last-N o basata sul tempo può essere
aggiunta con una migrazione/comando dedicato — non costruita ora senza
evidenza del bisogno.

## Autorizzazione

Riusa esattamente l'autorizzazione già esistente per la modifica di un
articolo, senza scorciatoie:

- **Admin**: `middleware(['auth','editor'])` sull'intero gruppo di
  rotte, stesso livello già richiesto da `ArticleController::edit()`.
  Nessun controllo aggiuntivo per-articolo: un editor può già
  modificare qualunque articolo, quindi può già vederne/ripristinarne
  le versioni.
- **Redazione**: `middleware(['auth','redazione'])` + verifica esplicita
  `$article->user_id === auth()->id()` in ogni azione del controller
  (index/show/restore), identica a quella già in
  `ArticleController::edit()`/`update()`. Un collaboratore non vede né
  ripristina le versioni degli articoli altrui.
- **Un collaboratore non può ripristinare un articolo pubblicato** —
  stesso limite già imposto da `ArticleController::edit()`
  ("Gli articoli pubblicati non possono essere modificati. Contatta
  l'editor."). Il ripristino non deve mai aprire una scorciatoia verso
  un privilegio che l'editing normale non concede.

## Interfaccia "Versioni"

Un link "🕘 Versioni salvate" nella testata del form di modifica (solo
per articoli esistenti — un articolo nuovo non ha ancora versioni)
porta a un elenco semplice (data/ora, autore, titolo in quel momento,
azione "Visualizza"). "Visualizza" apre un confronto **campo per
campo** tra la versione e lo stato attuale — un indicatore visivo (●)
segna i campi che differiscono, non un diff carattere per carattere
(sproporzionato per HTML editoriale). Da lì, "Ripristina questa
versione" (con conferma nativa del browser, stesso pattern già in uso
per "Elimina articolo") applica il ripristino.

Deliberatamente escluso: nessun editor parallelo, nessuna timeline
spettacolare, nessun diff in stile GitHub, nessuna modale complessa,
nessun redesign del form articolo esistente.

## Accessibilità

- Tabelle con `<caption>` (screen-reader-only), `<th scope="col">` per
  gli elenchi, `<th scope="row">` per il confronto campo-per-campo.
  Elementi HTML nativi, nessun ARIA custom dove il markup semantico
  basta.
- Il pulsante di ripristino usa `confirm()` nativo del browser (stesso
  pattern già in uso in `admin/articles.blade.php` per "Elimina") —
  annunciato correttamente dagli screen reader senza codice aggiuntivo.
- Nessun contenuto interattivo nascosto dietro solo hover/colore: gli
  indicatori di campo modificato hanno anche un `aria-label` testuale.

## Relazione con l'autosave locale e con un futuro autosave server-side

Vedi `docs/editor-autosave.md` per la matrice completa. In sintesi, tre
livelli non sovrapposti:

1. **Autosave locale** (`localStorage`, esistente): protegge il lavoro
   NON ancora salvato da chiusura accidentale/crash/sessione scaduta.
2. **Revision history** (questo documento): protegge da un salvataggio
   esplicito RIUSCITO ma sbagliato.
3. **Autosave server-side** (non costruito, solo disegnato — vedi
   `docs/editor-autosave.md` §"Percorso futuro"): proteggerebbe da
   cancellazione dei dati del browser / recovery cross-device — un
   problema diverso da entrambi i precedenti, che richiede una tabella
   `article_drafts` separata, mai scritta da questo meccanismo.
