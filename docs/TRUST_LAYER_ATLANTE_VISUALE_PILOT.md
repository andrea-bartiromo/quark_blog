# Atlante visuale Kairus — B-61–B-65

Documento di design/audit. Nessuna route pubblica, nessuna migration.
Prototipo associato: `resources/views/prototypes/atlante-visuale-tavola.blade.php`,
non instradato.

## B-61 — Design

Vedi tabella dei requisiti nel messaggio di missione B-61 (obiettivo
singolo, alt obbligatorio, fonte, versione, mobile, stampa, performance,
relazione con Articoli/Concept/Percorsi). Nessuna canvas interattiva,
nessuna nuova route in questa fase. Precedente riutilizzabile:
`components/special/hotspot-diagram.blade.php` — pattern accessibile
CSS-only (radio+label, nessun JS, dimensioni immagine risolte da file
reale per evitare layout shift) già in produzione per un caso d'uso
diverso (Turing), riusabile come riferimento di accessibilità.

## B-62 — Prototipo

`resources/views/prototypes/atlante-visuale-tavola.blade.php`: non
instradato, `noindex,nofollow` come difesa in profondità, contenuto
interamente segnaposto. Nessuna immagine reale, nessun JavaScript. Test
dedicato (`AtlanteVisualePrototypeNotRoutedTest`) verifica che nessuna
route lo raggiunga.

## B-63 — Audit accessibilità del prototipo

Verificato sul solo markup (nessun browser headless eseguito in questa
missione, coerente con "prototipo non pubblico" — una verifica browser
reale è compito di un pilot futuro instradato):

- **Tastiera**: nessun elemento interattivo nel prototipo (nessuna canvas,
  nessun hotspot in questa V1) — nulla da navigare oltre i link standard
  della pagina, già coperti dal layout esistente.
- **Screen reader**: `<figcaption>` associata semanticamente alla
  `<figure>` — descrizione, fonte e versione tutte leggibili in sequenza.
  **Blocker noto**: il prototipo usa un segnaposto `<div>` invece di
  un'immagine reale, quindi non testa l'`alt` effettivo — un pilot reale
  deve verificare che l'alt descriva il contenuto informativo dello
  schema, non solo "immagine di [soggetto]".
- **Ingrandimento/contrasto**: eredita gli stessi token di colore già in
  uso da chi-siamo/metodologia (nessun colore nuovo introdotto).
- **Reduced motion**: non applicabile in questa V1 (nessuna animazione).
- **Fallback testuale**: la didascalia stessa funge da riassunto testuale
  minimo se l'immagine non carica — ma non sostituisce un'immagine con
  informazione codificata solo visivamente: un pilot reale deve garantire
  che l'informazione chiave sia anche nel testo dell'articolo collegato,
  mai *solo* nello schema.

**Verbale**: nessun blocker bloccante nel prototipo stesso (è
segnaposto); un blocker reale è rimandato al pilot: verificare l'alt text
di un'immagine reale prima di qualunque pubblicazione.

## B-64 — Audit prestazionale del prototipo

Nessuna immagine reale caricata in questo prototipo (segnaposto CSS puro)
→ **INSUFFICIENT_DATA** per un peso reale misurato. Budget proposto per
il futuro pilot (lab, non field):

- Peso massimo per tavola: stesso ordine di grandezza di una cover
  articolo esistente (nessun dato peso reale disponibile in questo
  ambiente per fissare un numero preciso — **INSUFFICIENT_DATA**).
- Zero JavaScript per la resa base (V1 statica).
- CLS zero: dimensioni immagine sempre risolte da file reale prima del
  render, stesso pattern già usato da `hotspot-diagram.blade.php` e da
  `structured-data.blade.php` (`getimagesize()` locale, mai assunto).

Nessun dato field production disponibile in questo ambiente —
**INSUFFICIENT_DATA** esplicito, non un numero stimato a caso.

## B-65 — Decisione

**Pronto per prototipo**: sì — completato in questo commit, zero rischio
(non instradato, `noindex`, nessuna immagine reale).

**Pronto per pilot manuale**: **NO-GO**. Condizioni mancanti:
- **Owner editoriale**: nessuno assegnato — decisione umana necessaria.
- **Fonte/articolo base**: nessun articolo reale scelto per la prima
  tavola.
- **Percorso/metrica**: nessuna metrica di successo definita per questo
  formato specifico (a differenza di "Cosa sappiamo davvero", che ha già
  un contratto di misurazione in B-44 — l'Atlante ne richiederebbe uno
  analogo, non ancora scritto).
- **Rollback**: nessun meccanismo di rimozione ancora necessario finché
  non esiste una route pubblica — vedi il runbook generale B-68, che
  coprirà anche questo formato quando (e se) verrà attivato.

Nessuna route pubblica o migration verrà aperta finché una decisione
editoriale umana non fissa owner, articolo sorgente e metrica.
