# Prestazioni e CSS — Audit (Cantiere J, Prompt 237-248)

Audit di `public/css/editorial-system.css` (l'unico file CSS di
proprietà di questa sequenza di cantieri E-J) e delle prestazioni
locali delle sette superfici pubbliche già adottate, prima di
qualunque intervento di questo cantiere.

## Perimetro

Solo `public/css/editorial-system.css` e le viste già in perimetro
(le 7 superfici). **Non toccati**: nessun file CSS legacy
(`public-premium.css`, `home-fix.css`, `premium-fixes.css`,
`public-unified.css`, `style.css`, ecc.), né `components/ticker.blade.php`,
`components/header.blade.php`, `components/category-bar.blade.php`,
`components/footer.blade.php` — già documentati fuori perimetro nel
Cantiere I.

## Metodo

Ambiente locale: SQLite temporaneo, seed con articoli/autore/Percorso
(stessi fixture del Cantiere I: titoli/bio/handle lunghi, un articolo
`featured=true`), `php artisan serve` locale, Playwright + Chromium.
Nessuno strumento di build/minificazione CSS è in uso in questo
progetto (i file in `public/css/` sono serviti cosi' come sono,
versionati via query string `?v=<mtime>` da `App\Support\VersionedAsset`
— non minificati). Le misure di prestazione sono **locali**: PHP
`artisan serve` a singolo thread, nessun opcode cache di produzione,
nessuna CDN, nessuna concorrenza reale — utili solo per confronto
relativo (percentuale di CSS effettivamente esercitato, numero di
richieste), **non** come valore assoluto di prestazioni reali in
produzione.

## 1. Ordine di caricamento CSS (Prompt 237)

Da `resources/views/layouts/partials/head.blade.php` (globale, fuori
perimetro — non modificato):

```
style.css → frontend-hardening.css → [@yield('home_css')] →
public-premium.css → public-unified.css → premium-fixes.css →
editorial-system.css → [@yield('head') — CSS specifiche di pagina, es.
articolo.blade.php: media-lightbox.css, content-clusters.css]
```

`editorial-system.css` carica **dopo** tutto il CSS pubblico
condiviso (buono: a parità di specificità, le regole `.kairus-*`
vincono per ordine di sorgente) ma **prima** delle stylesheet
specifiche di pagina dichiarate in `@section('head')` (es. articolo:
`media-lightbox.css`, `content-clusters.css`). Questo è un rischio
architetturale già noto (documentato nei Cantieri E/F/G): a parità di
specificità, una regola in quei due file potrebbe silenziosamente
vincere su una regola `.kairus-*` equivalente. **Non risolto qui**:
richiederebbe spostare `@yield('head')` prima di `editorial-system.css`
nel layout condiviso — una modifica globale con impatto su ogni pagina
del sito, fuori dal perimetro "solo editorial-system.css o viste già
adottate" di questo cantiere. Verificato empiricamente (QA visiva
Cantieri E-I, screenshot, contrasto, overflow) che **nessun conflitto
reale è oggi presente** sulle 7 superfici — il rischio è architetturale,
non un difetto osservato.

## 2. `!important` e specificità (Prompt 242)

`editorial-system.css` contiene **4 dichiarazioni** `!important` reali
(un quinto match è testo di commento, non codice):

| Riga | Regola | Motivo |
|---|---|---|
| 164-166 | `@media (prefers-reduced-motion: reduce) { [class^="kairus-"], ... }` | Override universale intenzionale: deve vincere su qualunque `transition`/`animation` già dichiarata da qualsiasi selettore più specifico, per qualunque combinazione futura di componenti. |
| 1673 | `.kairus-sidebar-layout { grid-template-columns: minmax(0,1fr) !important; }` | Cantiere I (Prompt 215): deve vincere sia sulla regola legacy in `public-premium.css` sia sull'ordine di sorgente (vedi punto 1) — commento esplicativo già presente in loco. |

Nessun `!important` non giustificato o "a caso" trovato. Nessuna
modifica necessaria.

**Specificità**: tutte le regole di `editorial-system.css` usano
selettori di classe singola o doppia (`.kairus-x`, `.kairus-tone-navy
.kairus-eyebrow`) — mai ID, mai `!important` fuori dai 2 casi sopra.
Non è stato trovato nessun conflitto di specificità irrisolto tra
regole dello stesso file.

## 3. Consolidamento token (Prompt 243-244)

124 classi `.kairus-*` e 58 custom property `--kairus-*` definite. 5
token risultano non consumati da nessun `var(--kairus-*)` in tutto il
repository (grep su `*.css`/`*.blade.php`/`*.js`, non solo
`editorial-system.css`):

| Token | Dove definito | Stato |
|---|---|---|
| `--kairus-focus-ring` | riga 113 | Predisposto (valore box-shadow per un anello di focus alternativo a `outline`) — mai collegato a una regola |
| `--kairus-focus-ring-on-navy` | riga 114 | Stesso, variante per sfondo navy |
| `--kairus-line-on-navy` | riga 46 | Predisposto (hairline su sfondo navy) — nessun divisore ancora renderizzato su superficie navy |
| `--kairus-space-2xl` | righe 88, 175, 183 | Predisposto con override responsive già scritti (3 punti), mai consumato da una regola di padding/margin/gap |
| `--kairus-transition-fast` | riga 116 | Predisposto, solo `--kairus-transition-base` è attualmente in uso (3 volte) |

**Decisione: nessuna rimozione.** Sono token infrastrutturali
("Foundations", per definizione condivisi e pensati per superfici/
componenti non ancora costruiti) — rimuoverli toglierebbe
predisposizione già pronta senza alcun beneficio prestazionale
misurabile (poche decine di byte). Coerente con l'istruzione esplicita
di questo cantiere: rimuovere solo ciò la cui rimozione è
dimostrabilmente sicura E necessaria, non ogni cosa tecnicamente inerte.

## 4. Mappatura sovrapposizione con CSS legacy (Prompt 245)

Nessuna rimozione di CSS legacy in questo cantiere (fuori perimetro).
Sovrapposizioni note, già documentate nei Cantieri E-I e qui
solo confermate/mappate:

| Area | Legacy | Kairus | Convivenza |
|---|---|---|---|
| Layout a due colonne | `.article-premium__layout`/`.public-premium-layout` (public-premium.css) | `.kairus-sidebar-layout` (marker aggiuntivo) | Il legacy resta il layout di base; Kairus corregge solo il bug di overflow a `max-width:900px` (Cantiere I) |
| Griglie card | `.public-card-grid`/`.related-premium-grid` (public-premium.css) | `.kairus-archive-grid`/`.kairus-related-articles__grid` (solo `list-style:none` + fix overflow) | Stessa convivenza: legacy per il layout, Kairus per il reset semantico + il fix |
| Tipografia corpo articolo | `.article-premium__body p/h2/blockquote/img/a` (public-premium.css, invariato) | `.kairus-article-body h3/ul/ol/li/code/pre/hr/table` (elementi che il legacy non copriva affatto) | Nessuna sovrapposizione reale: Kairus copre solo ciò che il legacy non stilizzava |
| Hero categoria con immagine | Struttura legacy intera (public-premium.css) | Solo `.kairus-tone-navy` + bilanciamento titolo | Kairus non duplica nulla, aggiunge token minimi |

Nessuna regola CSS legacy duplica esattamente una regola Kairus — la
sovrapposizione è sempre "stesso elemento, proprietà diverse", mai
"stessa proprietà ridondante", quindi non c'è nulla da consolidare
senza toccare i file legacy (fuori perimetro).

## 5. Audit CSS inutilizzato (Prompt 246-248)

### Metodo

Due verifiche indipendenti:

**(a) Ricerca statica dei consumatori**: ogni classe `.kairus-*`
definita in `editorial-system.css` cercata in tutto `resources/views/`
e `resources/js/`. 21 classi non trovate da una ricerca letterale.

**(b) Copertura CSS reale (Chrome DevTools Coverage via Playwright)**:
unione delle byte-range effettivamente applicate su tutte le 7
superfici insieme (non singolarmente — una stylesheet condivisa ha
fisiologicamente bassa copertura per-pagina, il dato rilevante è
l'unione). Risultato: 43.3% del file coperto in unione (22012/50857
byte) a viewport 1280px, un solo passaggio.

### Perché il 43.3% NON è la percentuale di "codice morto"

Ispezionando ogni intervallo non coperto (42 intervalli > 40 byte), **nessuno
è codice irraggiungibile**. Si dividono in 4 categorie, tutte previste:

1. **Commenti** (la maggioranza dei byte): un commento CSS non è mai
   "coperto" anche quando la regola adiacente è applicata — un
   artefatto dello strumento, non un segnale.
2. **Stati `:hover`/`:focus-visible`**: mai osservabili da un
   caricamento statico di pagina (nessuna interazione reale simulata
   in questa passata) — tutte le regole `:hover`/`:focus-visible` del
   file risultano "non coperte" a prescindere dal fatto che siano
   davvero usate (già verificate manualmente e con audit dedicato
   focus-visible nel Cantiere I).
3. **Media query per viewport diversi da 1280px** (`max-width:1024px`,
   `min-width:769px`, `max-width:768px`, ecc.): non valutate a un solo
   viewport — già testate su 320-1440px nei Cantieri E-I.
4. **Varianti di componente non esercitate da QUESTI fixture**: le 21
   classi del punto (a) sono in realtà quasi tutte costruite
   dinamicamente da una prop del componente Blade (`$variant`,
   `$icon`, `$ratio`, `$status`, `$state`, `$tone` — verificato
   leggendo ogni componente in `resources/views/components/kairus/`),
   quindi "raggiungibili" ma non invocate con quel valore specifico
   da nessuna delle 7 superfici oggi:

   | Classe | Prop del componente | Componente |
   |---|---|---|
   | `kairus-article-card--compact/--featured/--list` | `variant` | article-card |
   | `kairus-image-frame--card/--hero/--landscape/--square` | `ratio` | image-frame |
   | `kairus-empty-state--error/--notice/--path/--search` | `icon` | empty-state |
   | `kairus-form-shell--error/--success` | `status` | form-shell |
   | `kairus-path-card--compact/--featured` | `variant` | path-card |
   | `kairus-path-step--current/--upcoming` | `state` | path-step |
   | `kairus-tone-sage` | `tone` | page-header |

   Le uniche 3 classi **non** costruite da una prop dinamica —
   `kairus-newsletter-shell`, `kairus-disabled`/`[data-kairus-disabled]`,
   `kairus-transition` — sono utility generiche "Foundations" (stato
   disabilitato, transizione coordinata con `prefers-reduced-motion`,
   wrapper newsletter) pensate per essere applicate manualmente da una
   futura vista, mai ancora necessarie sulle 7 superfici attuali.

### Decisione: nessuna rimozione di CSS in questo cantiere

Nessuna delle classi/regole sopra soddisfa la soglia richiesta
("rimozione dimostrabilmente sicura E necessaria"): sono tutte
raggiungibili tramite un contratto di componente reale (prop Blade)
o rappresentano infrastruttura Foundations condivisa esplicitamente
pensata per superfici non ancora costruite — rimuoverle romperebbe
un'opzione di variante documentata senza alcun beneficio prestazionale
misurabile. **Non è stato eseguito nessun commit di rimozione CSS.**
