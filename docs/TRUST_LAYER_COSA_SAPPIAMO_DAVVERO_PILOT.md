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
