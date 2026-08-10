# Automazione Piano Editoriale — Kairus

Guida operativa all'automazione del "Piano Editoriale" nel modulo
Progettazione: come marcare un documento come calendario, cosa viene
riconosciuto e collegato in automatico, cosa resta sempre una decisione
umana, e come usare i comandi da riga di comando per sincronizzare e
verificare lo stato. Nasce dalla missione notturna "Kairus Project
Automation / Piano Editoriale Intelligente" di agosto 2026.

## Indice

1. [Obiettivo e principi](#1-obiettivo-e-principi)
2. [Architettura](#2-architettura)
3. [Marcare un documento come calendario](#3-marcare-un-documento-come-calendario)
4. [Il parser del calendario](#4-il-parser-del-calendario)
5. [Matching per titolo](#5-matching-per-titolo)
6. [Riconciliazione e discrepanze](#6-riconciliazione-e-discrepanze)
7. [Collegamento automatico sicuro](#7-collegamento-automatico-sicuro)
8. [Metriche di avanzamento](#8-metriche-di-avanzamento)
9. [Prossima azione](#9-prossima-azione)
10. [Cronologia](#10-cronologia)
11. [Panoramica del progetto](#11-panoramica-del-progetto)
12. [Comandi operativi](#12-comandi-operativi)
13. [Schedulazione automatica](#13-schedulazione-automatica)
14. [Limiti noti e casi non gestiti](#14-limiti-noti-e-casi-non-gestiti)
15. [Rollback](#15-rollback)
16. [Progetti non editoriali](#16-progetti-non-editoriali)

---

## 1. Obiettivo e principi

Ridurre il lavoro manuale necessario a tenere allineato un piano
editoriale (es. "Piano Editoriale Kairus — 50 articoli") con gli articoli
reali del CMS, **senza** rendere il sistema opaco o aggressivo:

- **automatizzare ciò che è deterministico** (collegare un titolo
  identico o quasi identico a un articolo esistente);
- **proporre, mai applicare**, ogni decisione genuinamente ambigua (titoli
  riscritti in modo sostanziale, più candidati plausibili);
- **mai** creare, cancellare o modificare contenuti editoriali reali;
- **mai** scollegare automaticamente un articolo da un progetto;
- **mai** modificare il documento di calendario stesso — solo report e
  suggerimenti.

Ogni servizio descritto qui sotto è **di sola lettura** sul contenuto
editoriale, tranne `EditorialCalendarLinkingService` (§7), che scrive
esclusivamente collegamenti `project_article` sicuri e mai altro.

## 2. Architettura

Tutti i servizi vivono in `app/Services/Editorial/`:

| Classe | Ruolo |
|---|---|
| `EditorialCalendarParser` | Markdown → voci di calendario strutturate (§4) |
| `EditorialCalendarMatchingService` | Voce di calendario → articolo del CMS, per titolo (§5) |
| `EditorialCalendarReconciliationService` | Orchestratore: parsing + matching + classificazione discrepanze, sola lettura (§6) |
| `EditorialCalendarLinkingService` | Applica i collegamenti sicuri sopra un `EditorialCalendarReconciliationReport` (§7) |
| `EditorialCalendarProgress` | Metriche di avanzamento calcolate on-demand da un report (§8) |
| `EditorialCalendarNextActionResolver` | Suggerimento "prossima azione" per progetti editoriali (§9) |

Nessuno di questi servizi è accoppiato a un `Project` o `ProjectDocument`
specifico per ID: tutti operano su qualunque progetto passato loro, e il
parser opera su una stringa di contenuto Markdown qualunque. Questo
permette di riusare l'intera pipeline per più progetti editoriali
indipendenti, ciascuno con il proprio documento di calendario.

`EditorialCalendarReconciliationService::reconcile(Project $project)` è
**l'unica fonte di verità**: comando di sync, comando di audit, scheda
"Piano editoriale" nella panoramica progetto e risolutore della prossima
azione lo chiamano tutti — mai una seconda implementazione della stessa
logica che potrebbe divergere silenziosamente.

## 3. Marcare un documento come calendario

Un `ProjectDocument` diventa "il" calendario editoriale del suo progetto
spuntando "Questo è il calendario editoriale del progetto" nel form del
documento (`is_editorial_calendar`). Al più un documento alla volta può
esserlo per progetto: selezionarlo spegne automaticamente quello
precedente (stessa rete di sicurezza applicativa, a livello di modello,
già usata da `Project::is_default_editorial` — vedi `ProjectDocument::
booted()`). Progetti diversi possono avere ciascuno il proprio documento
calendario.

`Project::editorialCalendarDocument(): ?ProjectDocument` restituisce il
documento marcato, o `null` se il progetto non ne ha uno — questo è il
segnale che ogni servizio usa per decidere se un progetto è "editoriale
automatizzato" o no.

## 4. Il parser del calendario

`EditorialCalendarParser::parse(string $content): EditorialCalendarParseResult`
estrae le voci da un documento Markdown, riga per riga, in questo
formato:

```
DD/MM/YYYY — Titolo previsto — Filone [stato]
```

- **Data**: tollera `/`, `-` o `.` come separatore, cifre non
  zero-imbottite (`8/8/2026`), anno a 2 o 4 cifre (`26` → `2026`). Date
  impossibili (`30/02/2026`) producono un errore esplicito, **mai**
  traboccano silenziosamente al mese successivo.
- **Separatore data→titolo**: `—`, `–`, `--` o ` - `.
- **Filone** (secondo segmento, opzionale): separato dal titolo **solo**
  da `—` o `–` — mai un trattino singolo, che comparirebbe troppo spesso
  dentro titoli reali (es. "GPT-5") spezzandoli per errore.
- **Stato dichiarato** (opzionale): testo tra `[...]` o `(...)` a fine
  riga, estratto **verbatim**, mai validato contro un vocabolario fisso —
  uno stato scritto in un modo imprevisto non viene mai scartato.
- **Sezioni**: un'intestazione Markdown (`#`..`######`) o una riga
  interamente in `**grassetto**` imposta la sezione corrente (tipicamente
  un mese), applicata a ogni voce successiva.
- **Marcatori di lista** (`- `, `12. `) vengono rimossi prima del
  parsing.

Una riga che non inizia con un token simile a una data è **prosa** e
viene ignorata silenziosamente (è così che il parser "salta" le sezioni
non calendario del documento). Una riga che **inizia** con un token
simile a una data ma non può essere interpretata fino in fondo (data
invalida, titolo mancante dopo la data) produce invece un
`EditorialCalendarParseError` esplicito — mai scartata in silenzio, mai
un dato inventato per completarla. Entrambe le liste (voci valide ed
errori) sono sempre disponibili insieme in `EditorialCalendarParseResult`.

## 5. Matching per titolo

`EditorialCalendarMatchingService` confronta ogni voce con il pool di
articoli del CMS **solo per titolo** — mai per data, categoria o autore,
l'unico campo confrontabile direttamente tra documento e record `Article`.

Quattro esiti (`EditorialCalendarMatch::matchType`):

| Tipo | Condizione | Sicuro da collegare in automatico? |
|---|---|---|
| `exact` | Titolo identico byte per byte, candidato unico | Sì |
| `normalized` | Identico dopo normalizzazione prudente, candidato unico | Sì |
| `ambiguous` | Più candidati identici/normalizzati, oppure un candidato "vicino" ma non identico (similarità ≥ 90%, `similar_text()`) | **Mai** |
| `none` | Nessun candidato sopra la soglia di similarità | — |

La normalizzazione (`normalizeTitle()`) è deliberatamente prudente:
minuscolo, apostrofi/virgolette tipografiche riportati alla forma dritta,
spazi multipli collassati, punteggiatura finale (`?!.,;:`) rimossa. Non fa
mai matching per parole condivise: due titoli che condividono anche
diverse parole ma non superano la soglia di similarità del 90% dopo
normalizzazione restano `none`, mai collegati per "assomiglianza
tematica".

Un solo tipo "ambiguo" copre sia i piccoli riformulazioni evidenti sia i
casi davvero incerti: la distinzione non serve, perché in entrambi i casi
la linea di condotta è identica — **mai applicato in automatico**, sempre
e solo un suggerimento (`EditorialCalendarMatch::isSafeToAutoLink()`
torna `false`).

## 6. Riconciliazione e discrepanze

`EditorialCalendarReconciliationService::reconcile(Project $project)`
produce un `EditorialCalendarReconciliationReport`: una voce per ogni
riga di calendario valida, ciascuna con un match (§5) e una
`discrepancyType`:

| Discrepanza | Significato |
|---|---|
| `none` | Titolo allineato, data allineata, nessun conflitto di stato |
| `title_minor_change` | Match `normalized`: solo forma diversa (maiuscole, punteggiatura) |
| `title_major_change` | Match `ambiguous` con un solo candidato: probabile riscrittura, mai confermata da sola |
| `date_early` / `date_late` | L'articolo (match `exact`/`normalized`) risulta programmato/pubblicato prima/dopo la data pianificata |
| `status_mismatch` | Lo stato dichiarato nel calendario (testo libero) non corrisponde allo stato reale — solo quando il testo dichiarato contiene una parola chiave riconosciuta con certezza |
| `missing_article` | Nessun articolo del CMS corrisponde alla voce |
| `requires_review` | Match `ambiguous` con più candidati: nessuno abbastanza certo da proporre |

Il confronto data ignora l'orario (il calendario pianifica giorni, non
minuti) e non produce mai una discrepanza se l'articolo non ha ancora una
data reale (bozza/in revisione — non è un problema, è "non ancora
programmato"). Il confronto di stato non produce **mai** un falso
positivo: un testo dichiarato che non contiene nessuna parola chiave
riconosciuta (`pubblicat*`, `programmat*`/`schedulat*`/`pianificat*`,
`bozza`/`da scrivere`/`da fare`, `revision*`/`da revisionare`/`in
verifica`) non genera mai un `status_mismatch`, anche se lo stato reale è
diverso da quello immaginato dall'autore del calendario.

Il report include anche `articlesOutsidePlan`: articoli collegati al
progetto ma che non corrispondono (più) a nessuna voce del calendario —
utile per accorgersi di un titolo cambiato al punto da rompere il match, o
di un collegamento manuale non più coerente col piano. Non viene mai
scollegato automaticamente.

## 7. Collegamento automatico sicuro

`EditorialCalendarLinkingService` applica **solo** i match
`isSafeToAutoLink()` (exact/normalized, candidato unico, non già
collegato):

- `preview(Project $project)` — sola lettura, calcola cosa verrebbe
  collegato senza scrivere nulla;
- `apply(Project $project)` — collega davvero, registra ogni
  collegamento in Cronologia (§10) con origine "Sync calendario".

**Idempotente per costruzione**: una voce già collegata non compare mai
tra i match sicuri di una riesecuzione successiva, quindi rieseguire
`apply()` più volte non produce mai collegamenti duplicati né voci di
Cronologia ripetute. **Mai uno scollegamento**: questo servizio non
chiama mai `detach()` — un articolo collegato manualmente e non più
coerente col piano resta collegato, visibile in `articlesOutsidePlan`
(§6), mai rimosso da solo.

## 8. Metriche di avanzamento

`EditorialCalendarProgress::fromReport()` calcola, **on-demand, mai
persistito**, un insieme di metriche — deliberatamente non una singola
percentuale, che nasconderebbe differenze importanti:

- `coveragePercent` — quota di voci con **un articolo qualunque**
  associato, in qualsiasi stato;
- `publishedPercent` — quota di voci il cui articolo è **effettivamente
  pubblicato**;
- conteggi per stato reale (`publishedCount`, `scheduledCount`,
  `inProgressCount` = bozza/revisione);
- `missingArticleCount`, `needsReviewCount`.

Deliberatamente separata da `Project::progress`
(`ProjectProgressService`, basato sulle `ProjectTask` completate): un
progetto editoriale può non avere task, o averne per motivi non correlati
al calendario — sono due concetti diversi, mai la stessa colonna.

## 9. Prossima azione

`EditorialCalendarNextActionResolver::resolve(Project $project): ?string`
sostituisce, solo per progetti con un calendario collegato, il generico
`Project::suggestedNextAction()` con un suggerimento concreto e specifico,
in ordine di priorità:

1. una voce che richiede revisione umana (match ambiguo — sempre la
   priorità più alta, perché non risolvibile in automatico);
2. il prossimo articolo pianificato ancora senza un articolo nel CMS, per
   data più vicina;
3. la prima discrepanza di data;
4. la prima discrepanza di stato dichiarato;
5. se il calendario è completamente riconciliato, il suggerimento
   torna al comportamento standard basato sulle task del progetto.

**Per ogni progetto senza un calendario collegato** (compresi tutti i
progetti non editoriali), il fallback è **esattamente**
`Project::suggestedNextAction()`, non toccato: nessuna regressione sul
comportamento esistente (form "Modifica progetto", pulsante "Applica").

## 10. Cronologia

`ProjectActivityLog::SOURCE_EDITORIAL_SYNC` distingue i collegamenti
applicati da `EditorialCalendarLinkingService` da quelli manuali
(`SOURCE_MANUAL`) o dagli altri automatismi esistenti (`SOURCE_SYSTEM`,
es. il collegamento automatico al progetto editoriale predefinito). La
tab "Cronologia" del progetto mostra l'etichetta "Sync calendario" per
queste voci, "Automatico" per `SOURCE_SYSTEM`, "Manuale" per tutto il
resto. Ogni voce registrata nomina la voce di calendario coinvolta
(`voce #N`) e il titolo dell'articolo — mai un evento generico. Nessuna
voce di Cronologia viene mai riscritta retroattivamente, e nessuna voce
viene mai generata da un semplice caricamento di pagina (dry-run,
`preview()`, audit e risoluzione della prossima azione sono tutti di sola
lettura).

## 11. Panoramica del progetto

La tab "Panoramica" di un progetto con un calendario collegato mostra una
scheda compatta "Piano editoriale" (voci pianificate, copertura,
pubblicato, senza articolo, richiedono revisione) — calcolata solo per
quella tab, mai per le altre, per non appesantire il resto della pagina
progetto con un ricalcolo non necessario. È puramente informativa: nessun
pulsante di azione qui, i comandi restano l'unico modo di applicare
collegamenti (§7).

## 12. Comandi operativi

### `project:sync-editorial-calendar`

```
php artisan project:sync-editorial-calendar [--project=ID] [--execute]
```

Confronta il calendario con gli articoli e collega automaticamente solo i
match sicuri. **Dry-run di default** (nessuna scrittura): mostra cosa
verrebbe collegato. `--execute` applica davvero i collegamenti.
`--project=` limita a un singolo progetto (fallisce con un errore
esplicito se il progetto non esiste o non ha un documento di calendario
marcato). Senza `--project`, elabora tutti i progetti con un documento
marcato.

### `project:editorial-audit`

```
php artisan project:editorial-audit [--project=ID] [--json]
```

Fotografa lo stato di riconciliazione di uno o tutti i progetti con
calendario — **esclusivamente in lettura**, sicuro da eseguire in
qualunque momento, anche in produzione. `--json` restituisce un array di
oggetti (uno per progetto) invece del report testuale, utile per
integrazioni o monitoraggio esterno.

## 13. Schedulazione automatica

`routes/console.php` esegue `project:sync-editorial-calendar --execute`
ogni 5 minuti (`withoutOverlapping()`, log in
`storage/logs/projects-editorial-sync.log`), stesso pattern già in uso
per gli altri sync del modulo Progettazione
(`projects:sync-derived-statuses`, `projects:sync-github-tasks`).

**Perché uno scheduler e non un hook sincrono** su `Article::booted()`:
un hook sincrono aggiungerebbe latenza e complessità a un percorso caldo
e già pesantemente testato (creazione/aggiornamento articolo), per un
beneficio marginale — un collegamento applicato con qualche minuto di
ritardo non ha alcun impatto pratico, mentre un bug in un hook sincrono
romperebbe il salvataggio di ogni articolo. Lo scheduler riusa
l'infrastruttura già testata del comando manuale, è idempotente, ed è a
rischio molto più basso.

## 14. Limiti noti e casi non gestiti

- Il matching è **solo per titolo**: due voci di calendario con lo stesso
  titolo pianificato in date diverse producono match ambigui
  indistinguibili tra loro (stesso comportamento di due articoli reali
  con titolo identico).
- Un titolo riscritto oltre la soglia di similarità del 90% diventa
  `none` (nessun suggerimento), non `ambiguous` — non c'è modo di
  distinguere "titolo cambiato radicalmente" da "articolo non ancora
  scritto" senza un identificatore esplicito nel documento, che il
  formato del calendario non prevede.
- Il parser non segue riferimenti esterni (link, inclusioni): opera solo
  sul contenuto testuale del documento marcato.
- Nessuna cache: ogni chiamata a `reconcile()` rilegge il documento e
  ricarica il pool di articoli. Per un catalogo molto grande (migliaia di
  articoli) il costo cresce linearmente — accettabile alla scala attuale,
  da rivalutare se necessario in futuro.

## 15. Rollback

- **Disattivare la sincronizzazione**: rimuovere/commentare la voce in
  `routes/console.php`, o deselezionare "Questo è il calendario
  editoriale del progetto" sul documento (il progetto torna a comportarsi
  come un progetto senza calendario ovunque: comando, audit, prossima
  azione, panoramica).
- **Annullare un collegamento applicato per errore**: rimuovere
  manualmente l'articolo dalla tab "Articoli" del progetto (stesso
  meccanismo di scollegamento manuale già esistente) — la sincronizzazione
  non lo ricollegherà mai da sola, perché non scollega né ricollega
  automaticamente articoli rimossi manualmente.
- **Rimuovere l'automazione interamente**: la migrazione
  `2026_08_10_120000_add_is_editorial_calendar_to_project_documents_table`
  è reversibile (`down()` rimuove la colonna); nessun'altra automazione di
  questa missione modifica lo schema.

## 16. Progetti non editoriali

Un progetto senza un documento marcato `is_editorial_calendar` non è
toccato in alcun modo da questa missione:
`EditorialCalendarReconciliationService::reconcile()` torna un report
vuoto, `EditorialCalendarNextActionResolver` restituisce esattamente
`Project::suggestedNextAction()` come prima, la scheda "Piano editoriale"
non compare in panoramica, e sia il comando di sync sia quello di audit lo
saltano semplicemente quando elaborano "tutti i progetti". Nessuna
`ProjectTask` viene mai creata da questa automazione per nessun progetto.
