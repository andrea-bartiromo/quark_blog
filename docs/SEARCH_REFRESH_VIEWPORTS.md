# Ricerca editoriale — QA viewport (Cantiere G, Prompt 175-178)

Verifica empirica (Playwright + Chromium locale) su 4 stati × 5
viewport (320/375/768/1024/1440px): stato iniziale, risultati (16
articoli, uno con titolo molto lungo, uno senza cover), zero risultati,
filtri avanzati aperti.

## Overflow orizzontale

19/20 combinazioni **OK**. A 320px, tutti e 4 gli stati mostrano lo
stesso overflow di 17px (`scrollWidth: 337` vs `clientWidth: 320`).

**Verificato pre-esistente**: `git stash` di tutte le modifiche di
questo cantiere riproduce lo stesso overflow identico. La causa è la
stessa già documentata in `docs/ARTICLE_REFRESH_VIEWPORTS.md` (Cantiere
E): la struttura condivisa `.premium-sidebar`/`.premium-widget` (da
`components/sidebar.blade.php`, `public-unified.css`) non si riduce
sotto una soglia minima a viewport molto stretti. Qui si manifesta
senza nemmeno gli `!important` di `.article-premium__aside` (assenti su
questa pagina) — conferma che il bug è nella struttura sidebar
condivisa stessa, non in un singolo punto di applicazione, e riguarda
quindi **tutte** le pagine che includono `components/sidebar.blade.php`
(articolo, notizie, categoria, ricerca, autore). Non corretto in questo
cantiere (fuori perimetro — vive in `public-unified.css`, mai toccato
qui); da riprendere nel Cantiere I (Prompt 215, "Overflow globale") o
in un cantiere CSS dedicato con quel file nel proprio perimetro.

## Casi limite verificati visivamente (screenshot 1280px)

- **Form con filtri attivi**: `x-kairus.form-shell` racchiude
  correttamente barra di ricerca + pannello filtri, badge "ATTIVI"
  visibile, tutti i controlli (select categoria/autore, date, pulsanti
  Applica/Reset) leggibili e ben allineati.
- **Titolo di risultato molto lungo**: va a capo su più righe nella
  card senza clipping (nessun `line-clamp`, coerente con
  `x-kairus.article-card`).
- **Risultato senza cover**: fallback `placeholder-1.svg`, card
  allineata alle altre.
- **Query nell'H1**: "Risultati per "onde"" — visualizzata ed escapata
  correttamente (verificato anche da
  `SearchRefreshTest::test_query_is_escaped_in_title_and_heading`).

## Accessibilità (Prompt 164-165, 176)

- Label del campo di ricerca: `<label class="sr-only">` reale
  (associata via `for`/`id`), non il solo `placeholder` — già conforme,
  non modificato.
- Focus visibile: aggiunto `kairus-focusable` a input di ricerca,
  pulsante Cerca, summary "Filtri avanzati", select categoria/autore,
  input data Dal/Al, pulsanti Applica/Reset — **nessuno di questi aveva
  una regola `:focus-visible` dedicata prima** (verificato via grep su
  `public-unified.css`).
- Landmark `search` (già presente come `role`/elemento nel form?
  verificato: nessun `role="search"` esplicito prima o dopo — fuori
  perimetro di questo cantiere aggiungerne uno nuovo, il form resta
  comunque raggiungibile e etichettato).

## SEO (Prompt 177)

- `robots` resta `noindex,follow` su tutte le combinazioni testate —
  verificato anche da `SearchRefreshTest::test_search_page_is_never_indexable`.

## Sicurezza (Prompt 172)

- Query con `<script>` testata: mai renderizzata non escapata (verificato
  da `SearchRefreshTest::test_query_is_escaped_in_title_and_heading`).

## Zero-result tracking (Prompt 178)

Non toccato: `SearchZeroResultDiagnosticsService::record()` continua a
essere chiamato dal controller, mai duplicato né spostato in questo
cantiere (nessuna modifica al controller). Verificato con
`Feature/Search/SearchZeroResultDiagnosticsServiceTest` (verde,
invariato).
