# Content Clusters / Percorsi — Editorial Quickstart

Questa guida è pensata per product owner/editor. Non serve leggere il codice per iniziare a usare i Percorsi.

**Stato di riferimento:** release `4afdb310d085e0b4e92b6f54fbe1d2bfff54dd9e`  
**Regola di sicurezza:** costruisci e controlla ogni Percorso da **inattivo**; attivalo solo alla fine.

## 1. Dove trovare i Percorsi

Accedi all'admin con un account editor e apri:

`/admin/percorsi`

Da qui puoi creare e modificare i Percorsi. Le suggestion hanno una pagina dedicata nello stesso spazio admin.

## 2. Come creare un Percorso

Crea il Percorso con:

- nome;
- slug;
- descrizione breve/editoriale se disponibili;
- eventuali metadata SEO/cover previsti dal form;
- stato **inattivo** durante la preparazione.

Per la prima attivazione usa come base il piano in `docs/CONTENT_CLUSTERS_FIRST_EDITORIAL_ACTIVATION.md`.

Non attivare un Percorso vuoto: oggi un Percorso `is_active=true` può avere una detail page valida anche con zero articoli pubblici, pur restando fuori sitemap.

## 3. Come modificare i metadata

Apri il Percorso dall'elenco admin e modifica i campi editoriali. Cambiare metadata del Percorso non pubblica articoli e non crea automaticamente nuove membership.

Per i quattro Percorsi iniziali, il mapping versionato definisce nome/slug, ordine, pillar e membership; non definisce le descrizioni editoriali, che devono essere approvate umanamente.

## 4. Come aggiungere membership

Nel dettaglio admin del Percorso usa la gestione membership per selezionare gli articoli desiderati.

Per la prima sessione:

- parti solo dagli articoli `EXACT_MAPPING` del piano iniziale;
- verifica che lo slug corrisponda davvero all'articolo previsto;
- verifica che l'articolo sia pubblicato se deve comparire nel Percorso pubblico;
- non trasformare una suggestion dubbia in membership solo per “riempire” il Percorso.

Una membership non cambia lo stato editoriale dell'articolo: una bozza resta bozza, uno scheduled resta scheduled.

## 5. Come ordinare gli articoli

Ogni membership ha una posizione editoriale. L'ordine manuale è quello usato:

- nella pagina pubblica del Percorso;
- per il conteggio `N di M`;
- per i link precedente/successivo nell'articolo.

Gli articoli non pubblici non entrano nella sequenza pubblica, anche se sono membri.

## 6. Come scegliere il primary

Il `primary` identifica il Percorso principale di un articolo quando un articolo appartiene a più Percorsi. Il sistema mantiene l'invariante del primary e non deve essere usato come scorciatoia per pubblicare o promuovere un articolo.

Per i quattro Percorsi iniziali, il primo articolo del mapping è già la raccomandazione primary versionata.

## 7. Come scegliere il pillar

Il pillar è l'articolo di riferimento/partenza del Percorso.

Per la prima attivazione:

- scegli solo un articolo pubblicato e verificato;
- preferisci il pillar definito nel mapping versionato;
- controlla che appartenga al Percorso;
- non forzare un draft/scheduled come pillar pubblico.

Il servizio membership mantiene coerenti membership, pillar e primary; non cambia però lo stato di pubblicazione dell'articolo.

## 8. Dove vedere le suggestion

Apri la pagina suggestion da `/admin/percorsi`.

Le suggestion mostrano evidence, confidence, motivazioni e stato. Sono un aiuto editoriale, non un sistema di assegnazione automatica.

Stati principali:

- `pending` — da valutare;
- `rejected` — rifiutata dall'editor;
- `stale` — l'evidence è cambiata e la vecchia suggestion non è più attuale;
- `accepted` — accettata e applicata tramite il normale servizio membership.

## 9. Come accettare o rifiutare una suggestion

Usa le azioni esplicite **Accetta** o **Rifiuta**.

- **Accetta:** il sistema rivalida l'evidence e applica la membership tramite il normale servizio editoriale.
- **Rifiuta:** la suggestion resta rifiutata finché l'evidence non cambia.
- Se l'evidence cambia, una vecchia suggestion può diventare `stale` invece di essere riutilizzata silenziosamente.

Nessuna suggestion viene accettata automaticamente durante un normale salvataggio articolo.

## 10. Cosa accade dopo una modifica articolo

Dopo modifiche rilevanti all'articolo, Phase 2C aggiorna le suggestion **in modo article-scoped**: rivaluta l'articolo interessato senza lanciare un regenerate globale dell'intero catalogo.

Questo aggiornamento è fail-open rispetto al normale flusso editoriale: la suggestion non deve diventare un motivo per impedire un normale salvataggio articolo.

## 11. Cosa accade quando cambia la category

Se cambia la categoria di un articolo, vengono aggiornati anche i cohort di categoria rilevanti per le suggestion. Questo serve a evitare suggestion vecchie basate su segnali di categoria non più validi.

La category resta un **segnale**: non crea automaticamente membership.

## 12. Come attivare un Percorso

Attiva un Percorso solo dopo aver controllato:

- metadata comprensibili;
- almeno un articolo pubblicato;
- ordine;
- primary;
- pillar pubblicato, se previsto;
- assenza di articoli sbagliati;
- risultato pubblico atteso.

`is_active=true` rende il Percorso eleggibile per la superficie pubblica. L'attivazione va quindi fatta come ultimo passo deliberato.

## 13. Cosa diventa pubblico

Quando un Percorso è attivo:

- può apparire in `/percorsi`;
- la detail `/percorsi/{slug}` diventa raggiungibile;
- solo i suoi articoli effettivamente pubblicati compaiono nella lista pubblica;
- solo gli articoli pubblicati partecipano a previous/next e `N di M`;
- un pillar non pubblicato non viene mostrato pubblicamente;
- il Percorso entra in sitemap solo quando è attivo e possiede almeno un articolo pubblico.

Gli articoli con membership mostrano la continuation del Percorso solo quando il Percorso scelto per la navigazione è attivo.

## 14. Cosa NON diventa pubblico automaticamente

L'uso dei Percorsi non:

- pubblica bozze;
- rende pubblici articoli scheduled prima del tempo;
- attiva automaticamente un Percorso;
- crea automaticamente un pillar;
- crea membership a partire da una suggestion senza accettazione esplicita;
- trasforma una confidence alta in accettazione automatica;
- esegue un backfill production da solo.

## First-use: cosa aspettarsi con zero dati

### Nessun Percorso

L'admin deve essere usato per creare il primo Percorso. La pagina pubblica `/percorsi` resta valida anche senza Percorsi attivi.

### Percorsi esistenti ma inattivi

Restano gestibili in admin ma non sono raggiungibili come detail pubbliche. È lo stato consigliato durante la preparazione.

### Nessuna suggestion

Non è un errore: significa che il motore non ha evidence actionable corrente. Non creare membership solo per ottenere suggestion.

### Suggestion pending

Valuta evidence, reasons e articolo/Percorso prima di accettare o rifiutare.

### Percorso senza pillar

È supportato. Il pillar è nullable; puoi completarlo in seguito. Prima della prima attivazione è comunque consigliabile decidere esplicitamente se il Percorso debba avere un pillar.

### Percorso senza articoli

Può esistere in admin. Lascialo inattivo. Non attivarlo solo per provarne la pagina pubblica.

### Percorso con soli draft/scheduled

Può esistere in admin, ma gli articoli non diventano pubblici e non entrano nella sequenza pubblica. Lascialo inattivo fino a quando esiste contenuto realmente pubblicato e approvato.

## Come leggere la confidence

La confidence è evidence editoriale, non una soglia prodotto automatica.

- **90–100** — evidence forte da verificare. Il mapping esatto versionato produce oggi `100`.
- **70–89** — fascia utile per revisione, ma le regole canoniche attuali non devono necessariamente produrre punteggi in questo intervallo.
- **<70** — evidence di supporto. Il segnale category-only attuale produce `65` e non dovrebbe essere accettato senza controllo editoriale.

`NO_PRODUCT_THRESHOLD_DEFINED`

Non esiste una soglia prodotto approvata che autorizzi l'auto-accept.

## Percorso consigliato per la prima sessione di domani

1. Apri `/admin/percorsi`.
2. Scegli **un solo** Percorso iniziale da preparare.
3. Mantienilo inattivo.
4. Controlla i cinque slug `EXACT_MAPPING` nel piano editoriale.
5. Inserisci/approva descrizioni.
6. Costruisci membership e ordine.
7. Verifica primary e pillar.
8. Guarda le suggestion separatamente; non espandere il Percorso in automatico.
9. Controlla che esista almeno un articolo pubblicato.
10. Attiva soltanto dopo una decisione editoriale esplicita.
11. Dopo l'attivazione osserva pagina Percorso, continuation sugli articoli e segnali di seconda lettura prima di costruire automazioni più profonde.

## Se qualcosa non torna

Fermati e lascia il Percorso inattivo se:

- uno slug non esiste;
- uno slug punta all'articolo sbagliato;
- un articolo è draft/scheduled ma era atteso pubblico;
- non è chiaro quale articolo debba essere pillar/primary;
- una suggestion è basata solo su category signal e non convince editorialmente;
- l'ordine non ha senso pedagogico;
- la descrizione non è pronta.

Questi casi sono `HUMAN_DECISION_REQUIRED`, non errori da risolvere con automatismi.
