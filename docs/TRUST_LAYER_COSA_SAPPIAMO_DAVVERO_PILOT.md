# Pilot "Cosa sappiamo davvero" — B-39–B-45

Documento read-only/di design. Nessuna route pubblica, nessuna migration,
nessun contenuto editoriale definitivo. Il prototipo associato vive in
`resources/views/prototypes/cosa-sappiamo-davvero.blade.php`, non
instradato.

## B-39 — Audit contenuti sottili e duplicati

Kairus non genera oggi pagine aggregate automaticamente: articoli sono
editoriali singoli, `ContentCluster` (Percorsi) richiede un
`pillar_article_id` e articoli reali collegati, `Concept` non ha una
pagina pubblica propria (solo `about` nel JSON-LD, vedi
`ContentGraphService::discoverableConceptsForArticle()`). Il rischio
thin/duplicato è quindi oggi un rischio di processo editoriale, non di
codice. Regole anti-thin proposte (verificabili manualmente, non
automatizzate):

1. Ogni pagina pubblica ha almeno una fonte primaria distinta citata
   esplicitamente.
2. Nessuna pagina generata per un Concept/Percorso con meno di 2 articoli
   reali collegati.
3. Nessun contenuto duplicato verbatim tra due formati sullo stesso
   argomento — sintesi ammessa, copia no.
4. Assenza di dati mai presentata come "0" o placeholder numerico: sempre
   uno stato esplicito ("dato non disponibile").

## B-40 — Template "Cosa sappiamo davvero"

Realizzabile con pagina statica server-rendered (stesso pattern di
`chi-siamo.blade.php`/`metodologia.blade.php`), nessuna migration, nessun
CMS nuovo. Campi obbligatori: Domanda, Consenso, Incertezza, Cosa manca,
Fonti (riuso di `<x-article.primary-sources>` quando disponibile),
Ultimo controllo (data manuale, mai `updated_at` tecnico — stesso
principio di `ArticleRevisionTransparencyService`), Concept/Percorso
collegato (Content Graph esistente), CTA, metrica primaria.

## B-41 — Rubrica di review

Checklist umana, nessun punteggio opaco, nessuna pubblicazione
automatica:

- Ogni "consenso" ha una fonte primaria citata.
- Ogni "incertezza" usa un linguaggio calibrato alla confidenza reale.
- "Cosa manca" è onesta, non un riempitivo.
- Data di ultimo controllo plausibile (non futura, non più vecchia della
  fonte).
- Nessun conflitto di interesse non dichiarato.
- Contenuto aggiornabile: nessuna affermazione temporale assoluta fragile.
- Immagini con alt text e attribuzione (stesso standard di
  `cover_credit`/`cover_source`).
- Collegamenti interni reali, non tag decorativi.

## B-42 — Prototipo

`resources/views/prototypes/cosa-sappiamo-davvero.blade.php`: non
instradato, `@section('robots', 'noindex,nofollow')` come ulteriore
difesa in profondità nel caso venisse per errore collegato in futuro,
contenuto interamente segnaposto (`[Esempio]`/`[Testo di esempio]`),
nessuna affermazione scientifica reale. Riproduce staticamente l'output
atteso di `<x-article.primary-sources>` (componente che vive su
`feat/public-article-sources-v1`, non ancora mergiata su questo branch) —
da sostituire con il componente reale al merge, non duplicare oltre il
prototipo.

## B-43 — Audit prestazionale del prototipo

Nessuna richiesta HTTP esterna, nessun JavaScript, nessuna immagine
diversa dal layout esistente (riusa solo classi CSS già caricate
globalmente) — il prototipo stesso non introduce peso aggiuntivo
misurabile oltre alla pagina statica di base già in uso da
chi-siamo/metodologia/rettifiche.

**Budget proposto per il futuro pilot reale** (nessun dato field
disponibile in questo ambiente — **INSUFFICIENT_DATA** per LCP/CLS/INP
reali):
- Nessuna immagine hero pesante obbligatoria per questo formato (a
  differenza della pagina articolo) — se aggiunta, deve rispettare lo
  stesso trattamento `<x-responsive-image>` già in uso altrove.
- Zero JavaScript custom richiesto per la V1 (nessuna interattività
  necessaria per il contenuto descritto in B-40).
- CLS: zero elementi che si inseriscono dopo il caricamento iniziale
  (niente banner/popup ritardati specifici del formato).

## B-44 — Contratto di misurazione del pilot

Nessun tracking invasivo nuovo. Metriche minime, ciascuna con evento,
denominatore, finestra e condizione INSUFFICIENT_DATA esplicita:

| Metrica | Evento | Denominatore | Finestra | INSUFFICIENT_DATA se |
|---|---|---|---|---|
| Visualizzazioni aggregate | pageview pagina pilot | — (conteggio assoluto) | 30 giorni da pubblicazione | Meno di 7 giorni di dati raccolti |
| Lettura successiva | click su un link interno della pagina verso un altro contenuto | Visualizzazioni aggregate nello stesso periodo | 30 giorni | Meno di 50 visualizzazioni totali (campione troppo piccolo) |
| Click CTA | click sul bottone CTA finale | Visualizzazioni aggregate | 30 giorni | Meno di 50 visualizzazioni totali |
| Ritorno | sessione che rivisita la stessa pagina pilot entro 30gg | Visitatori unici nel periodo | 30 giorni | Nessun meccanismo di identificazione cross-sessione già esistente e approvato per questo (da NON costruire ad hoc) |
| Segnalazioni editoriali | messaggio ricevuto via `/contatti` che referenzia esplicitamente la pagina | — (conteggio assoluto) | Continua | Nessuna, è sempre un segnale qualitativo valido anche a campione singolo |

## B-45 — Decisione GO/NO-GO

**Pronto per prototipo**: sì — completato in questo commit (B-42), zero
rischio (non instradato, non pubblico, `noindex`).

**Pronto per pilot manuale**: **NO-GO** in questo momento. Condizioni
mancanti, tutte non colmabili da questo cantiere:
- **Owner editoriale**: nessuna persona assegnata nel repository/processo
  visibile a questo audit — richiede decisione umana.
- **Contenuto sorgente**: nessuna domanda reale con fonti verificate è
  stata approvata editorialmente — il prototipo usa solo segnaposto.
- **Gate Trust Layer**: dipende dal merge delle PR fonti pubbliche
  (`feat/public-article-sources-v1`) per riusare il componente reale
  invece di una riproduzione statica.

Nessuna route pubblica o migration verrà aperta finché queste condizioni
non sono soddisfatte da una decisione editoriale umana esplicita.

## Addendum — Cantiere 38 (programma "100 cantieri Kairus"), decisione esplicita

Un finding Codex reale (PR #595) ha segnalato che questa frase, presa
alla lettera, blocca ANCHE una migration puramente interna — non solo
quella del pilot pubblico. Verificato esplicitamente e deciso dall'utente
(non da questa sessione in autonomia): il NO-GO qui sopra resta valido
per il "pilot manuale" (pubblicazione reale a utenti reali) e per
qualunque route pubblica — nessuna delle tre condizioni mancanti è
soddisfatta da Cantiere 38, che infatti non tenta di soddisfarle.
Cantiere 38 ha aggiunto SOLO `TrustKnowledgeStatement`, un modello dati
interno (`/admin`, dietro autenticazione editor, zero contenuto
editoriale reale inserito) — la stessa categoria di campo di lavoro
interno già esistente per `Article::verification_status`, mai sottoposta
a questo gate. Il gate Trust Layer (terza condizione) è nel frattempo
già stato soddisfatto altrove su `main` (merge del componente Fonti
pubblico). Cantieri 40-44 restano il punto in cui l'intero gate B-45
(owner assegnato, contenuto approvato, decisione editoriale esplicita di
pubblicazione) torna ad applicarsi per intero, prima di qualunque route
pubblica o contenuto editoriale reale.

## Addendum — Cantiere 40 (programma "100 cantieri Kairus"), decisione esplicita

Cantiere 40 ("Preview non indicizzabile pilot Trust") ha aggiunto
`TrustKnowledgeStatementController::preview()` e la vista
`resources/views/admin/trust-knowledge/preview.blade.php`. Prima di
scrivere codice, un audit dedicato (agente di esplorazione, sola lettura)
ha confermato: zero route non autenticate raggiungono oggi un
`TrustKnowledgeStatement`; zero righe reali (non di test) di questo
modello esistono in qualunque ambiente (nessun seeder/factory le crea);
l'addendum Cantiere 38 qui sopra riserva esplicitamente ai Cantieri 40-44
la riapplicazione del gate B-45 per intero prima di una route pubblica o
contenuto reale.

Decisione di scope, portata all'utente via `AskUserQuestion` e delegata
esplicitamente a questa sessione ("scegli tu l'alternativa migliore"):
questo cantiere implementa SOLO un'anteprima di sola lettura, raggiungibile
esclusivamente dentro il gruppo di rotte `auth`+`editor` già esistente
(`/admin/cosa-sappiamo-davvero/{id}/anteprima`) — mai una route pubblica.
Il pattern riusa `Admin\CategoryController::preview()` (Cantiere 11): stesso
layout pubblico reale (`layouts.app`, non `layouts.admin`) per non far
divergere l'anteprima dalla pagina reale nel tempo, con un banner giallo
"Anteprima amministrativa" e `@section('robots', 'noindex,nofollow')` come
difesa in profondità (anche se la route non è comunque mai raggiungibile
da un utente non autenticato).

Questo NON soddisfa nessuna delle tre condizioni mancanti del NO-GO
originale (owner editoriale, contenuto approvato, gate Trust Layer): non
tenta di farlo. Resta vero quanto scritto nell'addendum Cantiere 38 — i
Cantieri 40-44 sono il punto in cui il gate B-45 completo torna ad
applicarsi prima di qualunque route pubblica o contenuto editoriale
reale; questo cantiere specifico resta strettamente interno
all'amministrazione, coerente con Cantiere 38 e 39.

## Addendum — Cantiere 42 (programma "100 cantieri Kairus"), decisione esplicita

Cantiere 42 ("Gate pubblicazione pilot Trust") era il primo dei tre
cantieri (42-44) esplicitamente riservati dall'addendum Cantiere 38 alla
riapplicazione integrale del gate B-45 — quindi il candidato più diretto
a uno scontro reale con il NO-GO. Portato all'utente via `AskUserQuestion`
prima di scrivere codice (non deciso in autonomia da questa sessione),
con tre opzioni: (a) solo una schermata di sola lettura che calcola e
mostra lo stato delle tre condizioni, senza alcun modo di impostarle;
(b) un meccanismo scrivibile per assegnare owner/approvare contenuto;
(c) segnare 42-44 come `blocked` e saltare al Cantiere 45+. L'utente ha
scelto esplicitamente (a).

Implementato: `TrustPilotGateReadinessService::assess()` — calcola le tre
condizioni SENZA MAI scriverle:
1. **Owner editoriale assegnato**: nessun campo o meccanismo di
   assegnazione esiste ancora nel sistema per questo pilot — stato
   esplicitamente "non determinabile automaticamente" (non "falso": la
   distinzione tra "verificato falso" e "non ancora verificabile" è
   dichiarata onestamente, mai appiattita a un booleano).
2. **Contenuto sorgente reale approvato**: `TrustKnowledgeStatement::count()`.
   Zero righe → condizione certamente non soddisfatta. Una o più righe →
   ancora "non determinabile" (mai "soddisfatta"): il modello non ha un
   campo "approvato", quindi la sola presenza di righe non può da sola
   dichiarare il contenuto approvato — solo smentire "zero contenuto".
3. **Componente Fonti pubblico**: verificato controllando che
   `resources/views/components/article/primary-sources.blade.php` esista
   davvero nel codebase (non un booleano fisso) — già soddisfatta,
   coerente con l'addendum Cantiere 38.

Nessuna route pubblica, nessun form, nessun input scrivibile riconducibile
a owner/approvazione/decisione: verificato con test dedicato
(`test_the_page_never_offers_a_form_to_set_any_condition`). L'assegnazione
reale di un owner, l'approvazione reale di contenuto e la registrazione
della decisione GO/NO-GO restano esplicitamente riservate al Cantiere 44
("Admin decisione GO/NO-GO pilot"), che per la sua natura ancora più
diretta richiederà a sua volta un'altra decisione umana esplicita prima
di introdurre qualunque meccanismo di scrittura.
