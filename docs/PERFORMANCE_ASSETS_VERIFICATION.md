# Prestazioni e CSS — Asset, font, immagini, cache-busting (Cantiere J, Prompt 249-250)

Verifica (non modifica: tutto già conforme, nessuna azione necessaria)
di asset/font/priorità immagini/cache-busting sulle sette superfici
pubbliche.

## Cache-busting (Prompt 250)

`public/css/editorial-system.css` è già servito tramite
`\App\Support\VersionedAsset::url(...)` (verificato in
`resources/views/layouts/partials/head.blade.php:178`), lo stesso
meccanismo di produzione (SHA di release se presente un file
`REVISION`, altrimenti `filemtime()` in sviluppo/test/CI) già in uso
per tutti gli altri CSS pubblici — nessuna divergenza, nessuna
modifica necessaria. Nessun asset introdotto da alcun cantiere Kairus
bypassa questo meccanismo: nessun `url()` in `editorial-system.css`
(verificato via grep, zero occorrenze) e nessun percorso di file
statico scritto a mano in nessuna vista toccata dai Cantieri D-J.

## Font (Prompt 249 — "nessun nuovo font")

`editorial-system.css` usa solo `'Fraunces'` (`--kairus-font-display`)
e `'Plus Jakarta Sans'` (`--kairus-font-body`/`--kairus-font-interface`)
— gli stessi due font **già caricati** dal tag Google Fonts condiviso
in `head.blade.php` (righe 147-153), invariato da nessun cantiere di
questa sequenza. Nessun font nuovo introdotto, nessun `@font-face`
aggiunto, nessuna nuova richiesta di rete per font.

## Priorità immagini (Prompt 249)

Verificato (`x-responsive-image`, componente pre-esistente, mai
modificato da questa sequenza di cantieri):

- **Hero above-the-fold** (home `hero-trending.blade.php`, articolo
  `hero.blade.php`): `loading="eager" fetchpriority="high"` — corretto
  per LCP, invariato rispetto a prima dell'adozione Kairus (i commenti
  nei rispettivi file lo confermano esplicitamente: "width/height/
  srcset/loading/fetchpriority/sizes invariati").
- **Card in griglia** (ultimi articoli, correlati, archivio, ricerca):
  nessuna prop `loading` passata esplicitamente → usa il default del
  componente, `loading="lazy"` (verificato in
  `components/responsive-image.blade.php:26`) — corretto, nessuna
  immagine sotto la piega richiede il download immediato.

Nessuna modifica necessaria: il comportamento era già corretto prima
di questo cantiere e nessuna vista Kairus lo ha alterato.

## Minificazione (Prompt 249)

Il progetto non usa alcuno strumento di build/minificazione CSS
(nessun Vite/Webpack/PostCSS per i file in `public/css/` — serviti
cosi' come sono, verificato in `composer.json`/`package.json`: nessuna
dipendenza di build CSS presente). `editorial-system.css` non è
minificato (51034 byte, con commenti estesi — la documentazione in
linea è parte integrante della disciplina di questo progetto).
**Nessuna minificazione manuale eseguita**: comprimere il file a mano
(rimuovendo whitespace/commenti) distruggerebbe la tracciabilità delle
decisioni documentate in linea, obbligatoria per l'intera sequenza di
cantieri, e introdurre un minificatore automatico richiederebbe una
nuova dipendenza — esplicitamente vietato. Il costo prestazionale reale
di questo è marginale: 51 KB non compressi, serviti una volta e
cachati dal browser (cache-busting sopra), su un file caricato una
sola volta per intera sessione di navigazione pubblica. Raccomandazione
per un cantiere futuro (fuori da questa sequenza): introdurre una
pipeline di build CSS con approvazione esplicita, come dipendenza
dedicata.
