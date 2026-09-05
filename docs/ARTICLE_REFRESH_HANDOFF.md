# Articolo pubblico — Handoff (Cantiere E)

Adozione di Kairus Editorial Foundations V1 sulla pagina pubblica
dell'articolo (`/articolo/{slug}`).

## SHA

- Base: `feat/kairus-home-paths-refresh` @ `06594007bc4b69a7f2f0f4ba2f215bc805a44f40`
- Branch: `feat/kairus-article-refresh`
- 4 commit (più i commit di chiusura del Prompt 132), nessuna PR, nessun
  merge, nessun deploy.

## Commit (ciascuno un rollback indipendente con `git revert <sha>`)

1. `c65f162` — docs: audit filemap, contratto dati, invarianti SEO/JSON-LD.
2. `b464d43` — test: congela il contratto della pagina prima di ogni modifica.
3. `b01b630` — feat: adozione hero (meta autore/data/lettura + views),
   tipografia mancante del corpo (h3/liste/codice/tabelle/hr), wrapper
   tabelle responsive.
4. `032a071` — feat: pannello Fonti → trust-panel, rifinitura token del
   blocco autore, correlati → article-card.

## File toccati

**Documentazione (4):** `docs/ARTICLE_REFRESH_{FILEMAP,INVARIANTS,VIEWPORTS,HANDOFF}.md`.

**CSS (1 solo file):** `public/css/editorial-system.css` — 217 righe
aggiunte, **0 righe rimosse**, nessun altro file CSS toccato (verificato
con `git diff --stat`). Ogni selettore/variabile nuovi sono `.kairus-*`/
`--kairus-*` (certificato da
`KairusEditorialFoundationsIsolationTest::test_editorial_css_defines_only_kairus_prefixed_classes_and_variables`).

**Viste (5):** `resources/views/articolo.blade.php`,
`articles/partials/{hero,body,author-card,related-articles}.blade.php`.
Non toccate: `breadcrumb`, `toc`, `path-continuation`, `continue-reading`,
`newsletter-band`, `share-card`, `structured-data`, `scripts` (già
adeguate o senza un componente Kairus compatibile — vedi sotto).

**Test (3):** `ArticleCoverImageViewerTest` (aggiornato per la classe
aggiuntiva sull'hero), `ArticleRefreshTest` (nuovo, contratto dedicato),
`KairusEditorialFoundationsIsolationTest` (aggiornato: `articolo.blade.php`
e `articles/` escono dall'elenco vietato — evoluzione attesa, come già
per `home.blade.php` nel Cantiere D; `admin`/`redazione` aggiunte come
vietate per l'intera sequenza E-J).

Nessun controller, route, model, migration, config, schema, form
articolo, autosave, dato editoriale reale toccato — verificato con
`git diff --name-only` sull'intero cantiere (elenco sopra, esaustivo) e
con il nuovo controllo automatico su `admin`/`redazione` nel test di
isolamento.

## Componenti Kairus montati

| Blocco | Componente | Note |
|---|---|---|
| Meta autore/data/lettura | `x-kairus.article-meta` | In contesto `.kairus-tone-navy` (nuovo, riusa il sistema di toni già esistente); le visualizzazioni (dato reale non coperto dal componente) restano un elemento fratello nello stesso stile |
| Tipografia corpo (h3/liste/codice/tabelle/hr) | Nessun componente — token via `.kairus-article-body` | Elementi prima del tutto privi di stile dentro il pannello; link/p/h2/blockquote/img avevano già CSS legacy completo, non toccati |
| Pannello Fonti | `x-kairus.trust-panel` (slot `sources`) | Unica presentazione fonti realmente attiva in questa catena di branch (testo libero legacy — vedi sovrapposizione sotto) |
| Blocco autore | Nessun componente — solo token | Struttura/dati invariati; il trattamento come superficie di fiducia è del Cantiere H |
| Articoli correlati | `x-kairus.article-card` | Algoritmo/quantità/ordine/destinazioni invariati (`ArticleRelatedService`) |

**Esclusi con motivazione** (nessuno "sembra mancante" — verificato e
scartato):

- **Hero** (`x-kairus.page-header`/`x-kairus.image-frame`): nessuno dei
  due supporta un'immagine di sfondo con testo sovrapposto senza perdere
  la cover o capovolgere il layout (stessa ragione già documentata per
  l'hero home nel Cantiere D).
- **"Continua il percorso"** (`path-continuation.blade.php`): CSS legacy
  già completo e accessibile (bordo, hover, focus-visible proprio);
  nessun componente Kairus modella prev/next+progress-meter; un'aggiunta
  di `.kairus-eyebrow` sarebbe stata inerte (specificità pari, CSS legacy
  caricato dopo).
- **"Continua da qui"** (`continue-reading.blade.php`), **breadcrumb**,
  **TOC**, **share-card**, **newsletter-band**: già visivamente coerenti
  con il resto della pagina; nessuna modifica necessaria per l'obiettivo
  di questo cantiere (adozione visiva, non un redesign di ogni pixel).

## Sovrapposizione — Trust Layer pubblico (branch non mergiati)

Confermato dall'audit iniziale (Prompt 104-106): un intero filone
"Trust Layer" con codice reale esiste solo su branch **non mergiati**
(`feat/public-article-sources-v1` #516, `feat/public-article-revision-transparency-v1`
#517, ecc.), assenti da `main` e da questa catena `feat/kairus-*`. Questo
cantiere adotta i componenti Kairus sulla presentazione delle fonti
**realmente attiva oggi**: il pannello Fonti legacy da
`explode('---', $article->body)`. Nessun merge, rebase o cherry-pick
eseguito (vietato dalle regole globali). Il Cantiere H (Trust Layer
pubblico) opererà sullo stesso perimetro reale.

## Verifiche eseguite

- **Suite mirata**: 442 test, 442 passati (design system + tutte le
  suite SEO/JSON-LD/breadcrumb/cover/TOC/related/path-navigation/
  responsive-image/query-budget/Second-Read-Analytics/Content-Graph/
  Percorsi), 0 fallimenti.
- **Viewport QA** (320/375/768/1024/1440px) con dati di prova reali:
  titolo molto lungo, sommario esteso, autore con nome molto lungo,
  corpo ricco (h2/h3/liste/blockquote/link/codice/tabella/hr), 3 fonti
  (una con URL molto lungo), 3 correlati/continuazione. Dettaglio
  completo in `docs/ARTICLE_REFRESH_VIEWPORTS.md`.
- **Overflow a 320px**: trovato un bug reale nella sidebar
  (`.article-premium__aside`/`.premium-widget`, 339px anche con ~288px
  disponibili) — **verificato pre-esistente** (`git stash` di tutte le
  modifiche di questo cantiere riproduce lo stesso overflow identico),
  vive in CSS/struttura condivisa con notizie/categoria/ricerca/autore,
  fuori perimetro. Documentato, non corretto qui.
- **Bug reale trovato E corretto nel proprio perimetro**: il blocco
  autore con nome molto lungo non andava a capo (`min-width:auto`
  implicito su un elemento flex) — risolto con
  `.kairus-author-card__info{min-width:0}`.
- **Contrasto/focus**: meta line dell'hero verificata leggibile su sfondo
  scuro (token `--kairus-ink-on-navy*` dedicati); aggiunto focus visibile
  al link kicker categoria e al breadcrumb-equivalente (prima assente).
- **`prefers-reduced-motion`**: coperto dalla regola di sistema
  `[class^="kairus-"]` già esistente, non ridefinita qui.
- **Pint**: pulito su ogni commit (`--dirty`).
- **`git diff --check`**: pulito sull'intero diff del cantiere.
- **Isolamento**: nessun file admin/redazione/controller/route/model/
  migration/config toccato — confermato via `git diff --name-only` e da
  un nuovo controllo automatico (`admin`/`redazione` ora nell'elenco
  vietato del test di isolamento, per l'intera sequenza E-J).

## Rischi residui

- L'overflow a 320px nella sidebar (vedi sopra) resta presente — non
  introdotto né aggravato da questo cantiere, ma visibile su questa
  pagina. Da correggere in un cantiere con `premium-fixes.css`/
  `public-unified.css` nel proprio perimetro (Cantiere I o un cantiere
  CSS dedicato).
- Le microcopy di data/tempo di lettura nella meta line dell'hero
  cambiano leggermente formato passando a `x-kairus.article-meta`
  ("3 set 2026"/"6 minuti di lettura" invece di "3 settembre 2026"/
  "6 min di lettura") — stesso dato, stessa API già usata altrove nel
  sistema, non un'invenzione.

## Lavoro deliberatamente escluso

- Trust Layer strutturato (fonti/revisioni/autore esteso): esiste solo
  su branch non mergiati, vedi sopra — di competenza del Cantiere H.
- Qualunque tocco a `admin`/`redazione`/form articolo/autosave: mai
  aperto in questo cantiere.
- Ristrutturazione di TOC/breadcrumb/share-card/newsletter-band/
  path-continuation/continue-reading: già coerenti o senza un
  componente Kairus adatto, motivato sopra.
