# Quark Blog — Workflow editoriale e verifica delle fonti

Questo documento definisce le regole funzionali da implementare prima di modificare controller, database e interfaccia. L'obiettivo è trasformare Quark Blog in un CMS editoriale collaborativo con un processo di verifica delle fonti esplicito, tracciabile e testabile.

## 1. Principi

1. Nessun contenuto generato automaticamente viene pubblicato senza revisione umana.
2. La pubblicazione e la verifica delle fonti sono operazioni distinte.
3. Ogni transizione importante deve registrare autore, data ed eventuali note.
4. Gli autori lavorano sui propri contenuti; editor e admin supervisionano l'intera redazione.
5. Le regole di accesso devono essere applicate tramite Policy e coperte da test automatici.
6. Le fonti non devono essere conservate come testo libero unico, ma come record strutturati collegati all'articolo.

## 2. Ruoli

### Admin

- gestisce utenti e ruoli;
- accede a tutti i contenuti;
- può approvare, rifiutare, verificare, programmare e pubblicare;
- può riaprire un articolo già approvato o pubblicato;
- può consultare l'intero registro delle attività.

### Editor

- accede a tutti gli articoli;
- assegna articoli e revisori;
- approva o rifiuta gli articoli;
- gestisce la verifica delle fonti;
- programma e pubblica;
- non gestisce privilegi amministrativi globali, salvo decisione successiva.

### Author

- crea articoli;
- modifica soltanto i propri articoli finché sono in bozza o richiedono correzioni;
- aggiunge e aggiorna le fonti dei propri articoli;
- invia un articolo in revisione;
- non approva, verifica o pubblica;
- non modifica articoli appartenenti ad altri autori.

## 3. Stato editoriale dell'articolo

Il campo `status` deve rappresentare esclusivamente il ciclo editoriale.

| Stato | Significato | Modificabile dall'autore |
|---|---|---:|
| `draft` | Bozza in lavorazione | sì |
| `in_review` | Inviato alla revisione editoriale | no |
| `changes_requested` | Restituito all'autore con correzioni richieste | sì |
| `approved` | Approvato editorialmente | no |
| `scheduled` | Approvato e programmato | no |
| `published` | Pubblicato | no |
| `archived` | Ritirato dalla pubblicazione | no |

### Transizioni consentite

```text
draft -> in_review
changes_requested -> in_review
in_review -> changes_requested
in_review -> approved
approved -> scheduled
approved -> published
scheduled -> published
published -> archived
archived -> draft        (solo admin/editor, con motivazione)
approved -> draft        (solo admin/editor, con motivazione)
```

Le transizioni non elencate devono essere rifiutate dall'applicazione.

## 4. Stato di verifica delle fonti

La verifica è indipendente dallo stato editoriale.

| Stato | Significato |
|---|---|
| `not_started` | Verifica non iniziata |
| `in_progress` | Fonti in controllo |
| `changes_required` | Fonti insufficienti, incoerenti o da sostituire |
| `verified` | Verifica completata |
| `expired` | Verifica da ripetere perché non più aggiornata |

### Regola di pubblicazione

Per impostazione predefinita, un articolo può essere pubblicato soltanto quando:

- lo stato editoriale è `approved` o `scheduled`;
- lo stato di verifica è `verified`;
- è presente almeno una fonte primaria o una motivazione editoriale esplicita per la sua assenza;
- titolo, corpo, autore, categoria, slug e data di pubblicazione sono validi.

Le eccezioni devono essere riservate ad admin/editor, richiedere una motivazione e produrre un evento nel registro attività.

## 5. Entità `ArticleSource`

Le fonti devono essere memorizzate in una tabella dedicata, non in un singolo campo testuale dell'articolo.

Campi proposti:

| Campo | Tipo indicativo | Scopo |
|---|---|---|
| `id` | bigint | Identificatore |
| `article_id` | foreign key | Articolo collegato |
| `title` | string | Titolo della fonte |
| `url` | text, nullable | Collegamento originale |
| `publisher` | string, nullable | Ente, rivista o autore |
| `source_type` | enum/string | Tipo di fonte |
| `publication_date` | date, nullable | Data della fonte |
| `accessed_at` | datetime, nullable | Data di consultazione |
| `is_primary` | boolean | Fonte primaria |
| `is_verified` | boolean | Fonte controllata |
| `verified_by` | foreign key, nullable | Utente che l'ha verificata |
| `verified_at` | datetime, nullable | Data della verifica |
| `notes` | text, nullable | Note editoriali interne |
| timestamps | timestamps | Tracciamento tecnico |

### Tipi di fonte iniziali

- `primary_document` — documento, dataset, comunicato o pubblicazione primaria;
- `scientific_paper` — articolo scientifico;
- `institutional` — ente pubblico, università, agenzia, istituzione;
- `expert_interview` — intervista a esperto identificabile;
- `reputable_media` — testata o media secondario affidabile;
- `book` — libro o manuale;
- `other` — eccezione motivata.

Non introduciamo inizialmente un punteggio automatico di affidabilità: la qualità di una fonte non può essere ridotta in modo serio a un numero senza criteri condivisi.

## 6. Dati di revisione sull'articolo

Campi proposti da aggiungere o normalizzare:

- `assigned_to` — autore responsabile;
- `reviewer_id` — editor incaricato;
- `submitted_for_review_at`;
- `reviewed_at`;
- `reviewed_by`;
- `review_notes`;
- `verification_status`;
- `verification_started_at`;
- `verified_at`;
- `verified_by` come foreign key, non nome libero;
- `verification_notes`;
- `scheduled_for`;
- `published_at`;
- `archived_at`;

Il campo attuale `primary_sources` dovrà essere mantenuto temporaneamente durante la migrazione dei dati, poi rimosso quando tutte le fonti saranno state convertite in `article_sources`.

## 7. Registro delle transizioni

Oltre all'eventuale activity log generale, è consigliata un'entità `ArticleStatusHistory` per ricostruire il ciclo di vita di ogni contenuto.

Campi minimi:

- `article_id`;
- `from_status`;
- `to_status`;
- `changed_by`;
- `reason`, nullable;
- `created_at`.

Lo stesso principio può essere applicato alle modifiche dello stato di verifica.

## 8. Servizi applicativi previsti

Per evitare controller troppo grandi, le operazioni principali saranno isolate in classi dedicate.

### `TransitionArticleStatus`

- verifica che la transizione sia consentita;
- controlla il ruolo dell'utente;
- aggiorna date e stato;
- registra la cronologia;
- genera eventuali notifiche.

### `VerifyArticleSources`

- verifica che le fonti richieste esistano;
- controlla che ogni fonte abbia i dati minimi;
- registra verificatore e data;
- aggiorna lo stato di verifica dell'articolo.

### `PublishArticle`

- controlla approvazione e verifica;
- controlla i dati obbligatori;
- preserva la prima data di pubblicazione;
- applica eventuali eccezioni motivate;
- registra l'attività.

## 9. Policy previste

### `ArticlePolicy`

Metodi minimi:

- `viewAny`;
- `view`;
- `create`;
- `update`;
- `delete`;
- `submitForReview`;
- `review`;
- `verifySources`;
- `schedule`;
- `publish`;
- `archive`.

### Regola essenziale

Un autore può aggiornare un articolo soltanto se:

- ne è il proprietario;
- lo stato è `draft` oppure `changes_requested`.

## 10. Interfacce da costruire

### Dashboard redazione

- articoli assegnati;
- bozze personali;
- correzioni richieste;
- scadenze;
- stato editoriale e stato delle fonti mostrati separatamente.

### Coda revisione

- articoli in revisione;
- autore;
- data di invio;
- revisore assegnato;
- numero di fonti;
- esito verifica;
- azioni approva/richiedi modifiche.

### Scheda verifica fonti

Per ogni fonte:

- titolo;
- URL;
- editore/ente;
- tipo;
- data;
- primaria sì/no;
- verificata sì/no;
- verificatore;
- note interne.

### Timeline articolo

Una cronologia leggibile di:

- creazione;
- invio in revisione;
- richieste di modifica;
- approvazione;
- verifica;
- programmazione;
- pubblicazione;
- aggiornamenti e archiviazione.

## 11. Test minimi prima dell'interfaccia

1. Un autore crea una bozza.
2. Un autore non modifica l'articolo di un altro autore.
3. Un autore invia la propria bozza in revisione.
4. Un autore non modifica un articolo `in_review`.
5. Un editor richiede modifiche con una nota.
6. Un editor approva un articolo.
7. Un autore non verifica le fonti.
8. Un editor verifica una fonte.
9. Un articolo non verificato non può essere pubblicato.
10. Un articolo approvato e verificato può essere pubblicato.
11. La prima data di pubblicazione non cambia durante normali aggiornamenti.
12. Ogni transizione crea una voce nella cronologia.

## 12. Ordine di implementazione

### Iterazione 1 — Autorizzazioni e stati

- enum dei ruoli;
- enum degli stati editoriali;
- `ArticlePolicy`;
- test di proprietà e accesso;
- nessuna modifica grafica.

### Iterazione 2 — Transizioni editoriali

- migration dei nuovi campi;
- servizio `TransitionArticleStatus`;
- cronologia degli stati;
- test delle transizioni.

### Iterazione 3 — Fonti strutturate

- migration `article_sources`;
- modello e relazioni;
- CRUD fonti dentro il form articolo;
- test di validazione e autorizzazione.

### Iterazione 4 — Verifica e pubblicazione

- servizio di verifica;
- regole di pubblicazione;
- coda di verifica;
- test end-to-end del workflow.

### Iterazione 5 — Esperienza redazionale

- dashboard per ruolo;
- filtri e paginazione;
- timeline;
- notifiche;
- accessibilità e responsive design.

## 13. Decisioni rinviate

Le seguenti funzioni non fanno parte della prima implementazione:

- punteggio automatico di affidabilità delle fonti;
- fact-checking automatico tramite AI;
- pubblicazione automatica;
- permessi configurabili da interfaccia;
- versionamento completo del testo dell'articolo;
- notifiche in tempo reale;
- integrazione con servizi esterni di reference management.

Queste funzioni potranno essere valutate solo dopo aver stabilizzato e testato il workflow di base.
