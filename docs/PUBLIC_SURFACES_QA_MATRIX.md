# Accessibilità e responsive trasversali — Matrice delle superfici (Cantiere I, Prompt 213-214)

Le **sette superfici pubbliche** già adottate dal sistema Kairus Editorial
Foundations V1 (Cantieri D-H), oggetto della QA trasversale di questo
cantiere. Nessuna nuova superficie viene aggiunta al perimetro: `autore`,
`turing` e i componenti condivisi `header`/`footer`/`ticker` restano
esplicitamente fuori (vedi `KairusEditorialFoundationsIsolationTest`).

| # | Superficie | Rotta | Vista | Cantiere di adozione |
|---|---|---|---|---|
| 1 | Home | `/` | `home.blade.php` + `home/partials/*` | D |
| 2 | Percorsi (indice) | `/percorsi` | `content-clusters/index.blade.php` | D |
| 3 | Percorso (dettaglio) | `/percorsi/{slug}` | `content-clusters/show.blade.php` | D |
| 4 | Articolo | `/articolo/{slug}` | `articolo.blade.php` + `articles/partials/*` | E, H |
| 5 | Notizie | `/notizie` | `notizie.blade.php` | F |
| 6 | Categoria | `/categoria/{slug}` | `categoria.blade.php` | F |
| 7 | Ricerca | `/ricerca` | `ricerca.blade.php` | G |

## Metodo

Verifica empirica (Playwright + Chromium locale, non axe-core: non
disponibile nell'ambiente — ogni controllo sotto è stato scritto a mano
sul DOM/CSS reale). Dati di prova: 6 articoli con titoli/bio/handle
volutamente lunghi, un autore con bio+social lunghi, un Percorso con
nome lungo che raccoglie quegli articoli, **un articolo marcato
`featured=true`** (necessario per il rendering realistico dell'hero
home — un fixture senza articolo featured produce un falso positivo
"home senza h1", vedi sotto).

## Riepilogo per superficie (dopo le correzioni di questo cantiere)

Tutte le 7 superfici, verificate insieme in un'unica passata:

| Controllo | Home | Percorsi idx | Percorso show | Articolo | Notizie | Categoria | Ricerca |
|---|---|---|---|---|---|---|---|
| Un solo `<h1>` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Nessun salto di livello heading | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `<header>`/`<main>`/`<footer>` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Skip-link presente e funzionante | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Nessun `<a>` annidato in un altro `<a>` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Ogni `<img>` ha l'attributo `alt` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Regola `prefers-reduced-motion` attiva | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Overflow orizzontale a 320px | ✅ 0px | ✅ 0px | ✅ 0px | ✅ 0px | ✅ 0px | ✅ 0px | ✅ 0px |
| Overflow a zoom 200% (equiv. 640px) | ✅ 0px | ✅ 0px | ✅ 0px | ✅ 0px | ✅ 0px | ✅ 0px | ✅ 0px |

## Difetti reali trovati e corretti in questo cantiere

### 1. Overflow orizzontale home a 320px (66px) — CORRETTO

`.kairus-latest-articles__grid` (griglia "Ultimi articoli" della home,
`editorial-system.css`, Cantiere D) usava `grid-template-columns: 1fr`
nudo nel proprio `@media (max-width: 480px)`, stessa causa radice già
isolata per la sidebar/griglie card nel commit precedente di questo
cantiere (Prompt 215): un grid item con `1fr` nudo eredita come minimo
automatico il min-content del proprio contenuto, non zero. Corretto in
`minmax(0, 1fr)`. Per prevenzione (stessa forma, stesso file, nessuna
differenza visiva) applicato anche a `.kairus-home-paths__grid`,
`.kairus-special-banner__grid` e `.kairus-form-shell__layout`, che
condividono lo stesso pattern pur non avendo ancora riprodotto
l'overflow con i dati di prova attuali.

### 2. Salto di heading su Percorsi indice (h1 → h3) — CORRETTO

`content-clusters/index.blade.php` monta `x-kairus.section-heading`
con `headingLevel=1` (scelta esplicita del Cantiere D, Prompt 73,
invariata) seguito dalla griglia di `x-kairus.path-card`, il cui
titolo è sempre un `<h3>` per costruzione del componente (condiviso con
la home) — nessun `<h2>` intermedio nominava la sezione. Aggiunto un
singolo `<h2 class="kairus-visually-hidden">Elenco dei Percorsi</h2>`
(utility già esistente, Missione 14) subito prima della griglia:
nessun cambiamento visivo, nessuna modifica al componente condiviso
`x-kairus.path-card` né alla scelta `headingLevel=1`.

## Falsi positivi (non difetti, fixture del test)

- **Home "0 h1"**: la prima passata di audit usava dati di prova senza
  alcun articolo `featured=true`. `home/partials/hero-trending.blade.php`
  monta l'unico `<h1>` della home dentro `@if($featured)` (scelta
  esplicita e documentata del Cantiere D: "il titolo resta l'unico h1
  della home"). Con un articolo featured realistico (come sempre in
  produzione, dove la redazione cura un featured), l'h1 è presente e
  corretto. Non è stato modificato alcun codice per questo punto.

## Riscontro noto, fuori perimetro, non corretto

- **20 link del ticker (`components/ticker.blade.php`) sotto i 24px di
  altezza** su ogni superficie (stesso componente condiviso a livello
  di layout, come header/footer/turing — esplicitamente fuori dal
  perimetro dell'intera sequenza di cantieri E-J, mai adottato da
  nessun cantiere). I link sono testo in flusso continuo separato da
  `·` (stessa riga, `white-space` naturale): rientrano nell'eccezione
  "inline" del WCAG 2.5.8 Target Size (link il cui perimetro è vincolato
  dall'interlinea di un blocco di testo), non una violazione reale.
  Documentato, non corretto: modificarlo richiederebbe toccare un
  componente esplicitamente fuori dal perimetro di questa sequenza di
  cantieri.

## Prossimi controlli (proseguono in questo stesso cantiere)

Contrasto colore (token già certificati, spot-check mirato), ordine
DOM vs ordine visivo, focus-visible su un campione di controlli
interattivi per superficie, stress-test con testo ancora più lungo,
modalità senza CSS, modalità senza JS, screenshot regression locale —
vedi `docs/PUBLIC_A11Y_RESPONSIVE_HANDOFF.md` per l'esito finale.
