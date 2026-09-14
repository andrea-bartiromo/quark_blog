# Protocollo editoriale "Cosa sappiamo davvero" (Cantiere 45)

Documento operativo per chi usa oggi, in `/admin`, la macchina interna
costruita dai Cantieri 38-43 del programma "100 cantieri Kairus". Non è
un nuovo design: consolida in un protocollo passo-passo quanto già
specificato in `docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md` (B-39–B-45)
e già costruito nel codebase, così che un editore possa seguirlo senza
dover ricostruire il contesto leggendo otto pull request.

**Questo documento non cambia lo stato del gate B-45.** Il pilot pubblico
reale resta **NO-GO** — nessuna delle sue tre condizioni (owner
editoriale, contenuto sorgente approvato, decisione GO/NO-GO) è
soddisfatta da questo protocollo, che descrive solo l'uso interno
già ammesso della macchina esistente. La decisione GO/NO-GO stessa
(Cantiere 44, "Admin decisione GO/NO-GO pilot") non esiste ancora nel
codebase e richiederà, quando si arriverà a costruirla, una propria
escalation `AskUserQuestion` esplicita — questo protocollo non la
anticipa e non la sostituisce.

## Chi può seguire questo protocollo

Solo utenti autenticati con ruolo `editor` (stesso gate di autenticazione
di tutto il resto di `/admin`). Nessuno step qui sotto è raggiungibile da
un visitatore anonimo o da una route pubblica.

## I sei passi

### 1. Creazione della voce (`TrustKnowledgeStatement`)

Route: `admin.trust-knowledge.create` → `admin.trust-knowledge.store`
(Cantiere 38-39, `app/Http/Controllers/Admin/TrustKnowledgeStatementController.php`).

Campi obbligatori per la validazione (`StoreTrustKnowledgeStatementRequest::rules()`):
**Domanda, Consenso, Incertezza**. Campi descritti in B-40 ma opzionali a
livello di validazione — raccomandati editorialmente, non bloccanti al
salvataggio: Cosa manca, Concept/Percorso collegato, Ultimo controllo
(data manuale — mai `updated_at` tecnico). La rubrica B-41 al passo 2
resta il luogo dove un reviewer verifica che i campi raccomandati siano
stati comunque compilati prima che la voce si consideri pronta, non la
validazione stessa. Il form offre solo Concept/Percorso già attivi (più
quello già collegato se nel frattempo archiviato) — si legga il docblock
del controller per il perché.

### 2. Review editoriale (rubrica B-41)

Checklist umana, applicata manualmente prima o durante la modifica della
voce (`admin.trust-knowledge.edit`) — non automatizzata, non un
punteggio opaco:

- Ogni "consenso" ha una fonte primaria citata.
- Ogni "incertezza" usa un linguaggio calibrato alla confidenza reale.
- "Cosa manca" è onesta, non un riempitivo.
- Data di ultimo controllo plausibile (non futura, non più vecchia della
  fonte).
- Nessun conflitto di interesse non dichiarato.
- Contenuto aggiornabile: nessuna affermazione temporale assoluta
  fragile.
- Immagini con alt text e attribuzione, se presenti.
- Collegamenti interni reali, non tag decorativi.

### 3. Anteprima (Cantiere 40)

Route: `admin.trust-knowledge.preview` (mai una route pubblica — resta
dentro `auth`+`editor`, `noindex,nofollow` come difesa in profondità).
Riusa il layout pubblico reale (`layouts.app`) con un banner giallo
"Anteprima amministrativa", così l'anteprima non diverge nel tempo
dall'aspetto che avrebbe la pagina reale.

Ogni apertura di questa pagina registra un evento aggregato e anonimo
(Cantiere 43, `TrustPilotPreviewMetricsService::recordView()`) — nessun
identificativo di visitatore/sessione/utente/IP, verificato
strutturalmente (`TrustPilotPreviewMetricsControllerTest::test_the_preview_views_table_has_no_visitor_identifying_column`).

### 4. Accessibilità del componente (Cantiere 41)

Il markup di consenso/incertezza/cosa manca vive nel componente
`<x-trust-knowledge-summary>` (`resources/views/components/trust-knowledge-summary.blade.php`),
condiviso tra anteprima e (in futuro) pagina pubblica reale — nessuna
duplicazione di markup da mantenere in sync manualmente.

### 5. Verifica dello stato del gate (Cantiere 42)

Route: `admin.trust-knowledge.gate-readiness`
(`TrustPilotGateReadinessService::assess()`). Mostra, in sola lettura,
lo stato delle tre condizioni del NO-GO B-45:

| Condizione | Come viene calcolata | Stati possibili |
|---|---|---|
| Owner editoriale assegnato | nessun meccanismo di assegnazione esiste ancora | sempre "non determinabile" |
| Contenuto sorgente reale approvato | `TrustKnowledgeStatement::count()` | "non soddisfatta" (zero righe) o "non determinabile" (una o più — il modello non ha un campo "approvato") |
| Componente Fonti pubblico disponibile | esistenza reale di `resources/views/components/article/primary-sources.blade.php` | "soddisfatta" o "non soddisfatta" |

Questa pagina non offre **nessun** modo di impostare una di queste
condizioni — nessun form, nessun input scrivibile riconducibile a
owner/approvazione/decisione. Se tutte e tre risultassero "soddisfatta"
contemporaneamente, questo protocollo non cambierebbe comunque: la
decisione di procedere resta riservata al passo 6.

### 6. Decisione GO/NO-GO (Cantiere 44 — non ancora costruito)

Questo passo **non esiste ancora nel codebase**. Quando verrà costruito,
sarà l'unico punto in cui: un owner editoriale viene assegnato, un
contenuto viene approvato editorialmente, e la decisione di procedere (o
no) verso il pilot pubblico reale viene registrata — sempre con audit
trail, mai silenziosamente. Costruirlo richiede una propria escalation
`AskUserQuestion` esplicita all'utente, per lo stesso motivo per cui i
Cantieri 38, 40 e 42 l'hanno richiesta prima di toccare codice
gate-adiacente: il nome stesso di questo passo implica la decisione
GO/NO-GO vera.

## Cosa questo protocollo NON permette, mai

- Nessuna route pubblica per `TrustKnowledgeStatement` o le sue pagine
  correlate.
- Nessun modo di assegnare un owner o approvare contenuto al di fuori del
  passo 6 (che non esiste ancora).
- Nessuna pubblicazione, invio, o modifica automatica di contenuto,
  fonte, o data editoriale — ogni passo sopra è manuale, guidato da un
  editore autenticato.
- Nessun identificativo di visitatore/sessione/utente/IP in qualunque
  metrica raccolta (passo 3).

## Perché questo cantiere non ha richiesto un'altra escalation

A differenza dei Cantieri 38/40/42 (che introducevano codice
gate-adiacente — un modello dati nuovo, una preview, un calcolo delle
condizioni) e diversamente da un ipotetico Cantiere 44 (che introdurrebbe
la decisione vera), questo cantiere non aggiunge alcun codice applicativo
nuovo: consolida in un unico documento operativo ciò che B-39–B-45 e i
Cantieri 38-43 hanno già specificato o costruito, riafferma esplicitamente
che il NO-GO resta in vigore, e non tenta in alcun modo di soddisfare le
tre condizioni mancanti. Stesso principio "documenta la macchina interna
già ammessa, mai il pubblico" già seguito senza escalation per i Cantieri
38-41 e 43.
