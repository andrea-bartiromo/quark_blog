# Articolo pubblico — QA viewport (Cantiere E, Prompt 127)

Verifica empirica (Playwright + Chromium locale) su un articolo di prova
con: titolo molto lungo, sommario vicino al limite, autore con nome
molto lungo, corpo ricco (h2/h3, liste, blockquote, link, codice inline
e a blocco, tabella a 3 colonne, `<hr>`), 3 fonti (una con URL molto
lungo), e 3 articoli correlati/continuazione.

## Overflow orizzontale — 320/375/768/1024/1440px

| Viewport | Risultato |
|---|---|
| 320px | **Overflow reale trovato (35px)** — vedi sotto |
| 375px | OK |
| 768px | OK |
| 1024px | OK |
| 1440px | OK |

### Overflow a 320px — bug reale, PRE-ESISTENTE, fuori perimetro

Trovato tramite ispezione DOM (elemento con `rect.right > viewport` non
contenuto in un antenato con `overflow:hidden`): `.article-premium__aside`
e i pannelli al suo interno (`.premium-sidebar`, `.premium-widget`,
`article-premium__panel`) misurano 339px anche quando lo spazio
disponibile a 320px è ~288px. Causa: `.article-premium__layout` passa a
`grid-template-columns: 1fr` sotto i 900px (`public-premium.css`), ma
`1fr` senza `minmax(0, 1fr)` lascia la colonna crescere fino al
min-content di quanto contiene — qualcosa nella sidebar (probabilmente
`.premium-topic-cloud`/i widget con `overflow:hidden !important` da
`premium-fixes.css`, che a loro volta impedirebbero la riduzione
automatica) non si riduce sotto quella soglia.

**Verificato come pre-esistente**: riproducibile identico (stesso
scarto di 35px) con `git stash` di **tutte** le modifiche di questo
cantiere applicate finora (hero, meta, tipografia corpo, tabelle, fonti,
autore, correlati) — il bug vive in `premium-fixes.css`/
`public-unified.css`/`public-premium.css`, mai toccati da questo
cantiere, e nella struttura `.article-premium__aside`/`.premium-sidebar`
condivisa con `notizie.blade.php`/`categoria.blade.php`/`ricerca.blade.php`/
`autore.blade.php` (fuori perimetro esplicito di questo cantiere).

**Decisione**: non corretto qui (debito preesistente fuori perimetro,
per le regole globali "non correggere debito preesistente fuori
perimetro" e "documenta, non allargare arbitrariamente"). Documentato
qui e ripreso nel Cantiere I (Accessibilità e responsive trasversali,
Prompt 215 "Overflow globale") o in un cantiere CSS dedicato, dove
toccare `premium-fixes.css`/`public-unified.css` rientra nel perimetro
esplicito.

Una causa correlata TROVATA E CORRETTA in questo cantiere (dentro il
proprio perimetro): il blocco autore con un nome molto lungo
contribuiva anch'esso a spingere la riga oltre il contenitore — bug
classico flexbox `min-width:auto` implicito sull'elemento non ristretto.
Corretto con `.kairus-author-card__info{min-width:0}` (commit
"adopt trust-panel, author card, and related-card tokens" seguito da
questo fix). Con questa correzione, la sidebar resta comunque a 339px
per la causa strutturale sopra descritta (non annullata dal solo fix
del nome autore) — il numero non cambia, ma il fix è comunque corretto
e necessario nel proprio perimetro.

## Casi limite verificati visivamente (screenshot 1280px e 375px)

- **Titolo molto lungo**: va a capo su più righe nell'hero, bilanciato
  (`text-wrap:balance` via `.kairus-article-hero__title`), nessun
  overflow, nessuna parola troncata.
- **Sommario vicino al limite**: resta leggibile, non trabocca dal
  proprio `max-width`.
- **Autore con nome lungo**: leggibile nella testata (meta line) e nella
  card sidebar dopo il fix `min-width:0` (vedi sopra).
- **Corpo ricco**: h2/h3/liste/blockquote/link/codice inline e a
  blocco/tabella/`<hr>` tutti visivamente coerenti con il resto del
  sistema (token Kairus), nessuno strutturato solo con stile di default
  del browser.
- **Tabella a 3 colonne**: renderizzata correttamente, wrapper
  `.kairus-table-scroll` presente e pronto a scorrere in orizzontale
  senza mai coinvolgere l'intera pagina (verificato che il wrapper non
  introduce overflow a nessun viewport testato — con 3 colonne strette
  non serve scrollare, comportamento corretto per costruzione: scorre
  solo quando la tabella eccede la colonna).
- **Fonti con URL molto lungo**: il pannello `x-kairus.trust-panel`
  contiene il testo senza spingere la pagina in overflow (il testo va a
  capo naturalmente dentro `<dd>`).
- **3 articoli correlati/continuazione**: "Continua da qui" mostra 1
  articolo, "Continua a leggere" (correlati) mostra il rimanente,
  entrambi con `x-kairus.article-card`/markup coerente, nessuna card
  vuota o placeholder ingannevole.

## Contrasto e focus

- Meta line dell'hero (`x-kairus.article-meta` in contesto
  `.kairus-tone-navy`): testo chiaro su sfondo scuro, token dedicati
  (`--kairus-ink-on-navy`/`-soft`) — nessun testo grigio su grigio.
- Link nel corpo: già AA (colore, sottolineatura sempre visibile,
  focus-visible con outline) — CSS legacy preesistente, non toccato.
- Kicker categoria e breadcrumb: `kairus-focusable` aggiunge un outline
  visibile dove prima non ce n'era uno dedicato.

## Riduzione del movimento

Nessuna animazione decorativa introdotta in questo cantiere. La regola
di sistema `[class^="kairus-"] { transition: none !important; ... }`
sotto `prefers-reduced-motion: reduce` (già presente in Kairus Editorial
Foundations V1) copre automaticamente ogni nuova classe `.kairus-*`
introdotta qui, senza bisogno di una regola dedicata.
