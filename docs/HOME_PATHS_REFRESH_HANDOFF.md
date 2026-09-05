# Home + Percorsi Visual Adoption — Handoff (Cantiere D)

Prompt 99. Adozione di Kairus Editorial Foundations V1 (10 componenti Blade
+ `public/css/editorial-system.css`, costruiti nel cantiere precedente e
mai montati prima) su home, indice Percorsi e dettaglio Percorso.

## SHA

- Base: `feat/kairus-editorial-foundations` @ `fb5ff3044a0eeec4345aaa8d0c79dac2cc44843a`
- Finale: `feat/kairus-home-paths-refresh` @ `cae1c97178e722b4a9dfc9222015dd83eeddcff8`
- 11 commit, nessuna PR, nessun merge, nessun deploy.

## Commit (in ordine, ciascuno un rollback indipendente con `git revert <sha>`)

1. `1bc5951` — docs: audit di perimetro/conflitti, invarianti, matrice componenti, matrice viewport (nessun file di produzione toccato).
2. `cc33483` — feat(home): riordino layout (Percorsi prima di Newsletter/Speciale), adozione hero + trending.
3. `0367b08` — feat(home): adozione article-card + empty-state su "Ultimi articoli".
4. `4c6e3d7` — feat(home): adozione section-heading + path-card su "Percorsi" in home.
5. `98db6aa` — feat(home): adozione token su Newsletter, Speciale, Categorie.
6. `a1907f5` — fix(home): correzione contrasto AA sul fallback dello Speciale (bug reale trovato via screenshot, non nel piano originale).
7. `946c65e` — adozione sull'indice Percorsi (section-heading, path-card, empty-state).
8. `5d7da51` — adozione sul dettaglio Percorso (path-step sulle tappe reali; rifiniture minime su hero/breadcrumb).
9. `c9bfc2c` — test: integrazione cross-superficie (home + indice + dettaglio Percorsi come un solo viaggio).
10. `1c6f31a` — docs: questo stesso documento di handoff.
11. `cae1c97` — chore: `.gitignore` ignora `playwright.local.config.js` (config locale della sandbox, mai da versionare — vedi sotto).

Ogni commit è isolato e singolarmente revertibile senza rompere i
precedenti: nessuna dipendenza a catena tra un commit e il successivo che
non sia già visibile nel diff.

## File toccati (25 totali, nessuno fuori da questo elenco — verificato
con `git diff --name-status feat/kairus-editorial-foundations...HEAD`)

**Configurazione repository (1):** `.gitignore` — aggiunta una sola riga,
`/playwright.local.config.js`. Quel file (config locale di Playwright,
usato per la QA visiva di questo cantiere: punta all'eseguibile Chromium
già presente in questa sandbox e al proxy locale dell'ambiente) è
volutamente **escluso dal versionamento**: resta `??` (untracked) in
`git status`, mai aggiunto né commesso in nessun commit di questo
cantiere — solo la sua esclusione via `.gitignore` è tracciata.

**Documentazione (5, solo testo):**
`docs/HOME_PATHS_REFRESH_{FILEMAP,INVARIANTS,VIEWPORTS,COMPONENTS,HANDOFF}.md`
(questo stesso file incluso — è parte del delta del branch).

**CSS editoriale (1 solo file):** `public/css/editorial-system.css` — unico
file CSS toccato in tutto il cantiere. Nessuna riga rimossa da alcun CSS
legacy (`style.css`, `home-fix.css`, `home-premium.css`,
`content-clusters*.css` invariati byte per byte). Ogni regola nuova usa
solo classi `.kairus-*` e variabili `--kairus-*` (certificato da
`KairusEditorialFoundationsIsolationTest::test_editorial_css_defines_only_kairus_prefixed_classes_and_variables`,
825 assertion, verde).

**Viste Blade (9):**
`resources/views/home.blade.php` + le sue 6 partial
(`hero-trending`, `latest-articles`, `paths-discovery`, `newsletter-band`,
`turing-teaser`, `category-grid`), `content-clusters/index.blade.php`,
`content-clusters/show.blade.php`.

**Test (9, 8 aggiornati alla nuova microcopy/classe dei componenti + 1 file
di test nuovo):**
`ContentClusterPublicTest`, `ContentClusterIndexNarrativePreviewTest`,
`ContentClusterIndexPaginationTest`, `PathEditorialAdditionsTest`,
`PathVisualIntegrationTest`, `PathVisualLibraryTest`, `PathVisualTimelineTest`,
`KairusEditorialFoundationsIsolationTest` (questi 8, tutti modificati —
l'ultimo perché `home.blade.php` e le directory `home`/`content-clusters`
non sono più nell'elenco "non ancora montato", essendolo ora
legittimamente, l'evoluzione attesa dal cantiere precedente), più
`HomePathsRefreshTest` (nuovo, 530 righe, 20 test — comprende anche il
test di integrazione cross-superficie del commit `c9bfc2c`).

Somma di controllo: 1 (config) + 5 (doc) + 1 (CSS) + 9 (viste) + 9 (test)
= 25, uguale al totale dichiarato in apertura.

Nessun controller, route, model, migration, config, schema, tracking,
consenso cookie, popup Newsletter, dati dei Percorsi/pillar/membership,
ordinamento, form articolo, autosave, controller articolo, fonti pubbliche,
revisioni, autore, metodologia o categorie è stato toccato — verificato con
`git diff --name-only fb5ff304..HEAD` (elenco sopra, esaustivo) più un grep
mirato su quei termini nei path, entrambi senza risultati fuori
perimetro. Nessuna dipendenza, libreria, font o file JavaScript nuovo
(nessuna riga in `package.json`/`composer.json`/i relativi lockfile).

## Componenti Kairus montati

| Superficie | Componenti |
|---|---|
| Home — evidenza | `image-frame` + `article-meta` (markup manuale per l'H1, vedi matrice componenti) |
| Home — Ultimi articoli | `article-card` (variant `featured`/`standard`) + `empty-state` |
| Home — Percorsi | `section-heading` + `path-card` |
| Home — Newsletter | `section-heading` (form invariato byte per byte) |
| Home — Speciale, Categorie | solo classi CSS namespaced (nessun componente dedicato nelle fondamenta V1) |
| Indice Percorsi | `section-heading` (H1) + `path-card` + `empty-state` |
| Dettaglio Percorso | `path-step` su ogni tappa reale; hero/pillar restano CSS legacy con rifiniture minime |

Componenti della V1 non usati in questo cantiere: `page-header` (nessuna
pagina ne aveva bisogno), `trust-panel` (Trust Layer, cantiere separato).

## Verifiche eseguite

- **Viewport/overflow** (320/375/768/1024/1440px) su home, indice Percorsi,
  dettaglio Percorso: nessun overflow orizzontale, in due passate (prima e
  dopo l'adozione del dettaglio).
- **Screenshot visivi** (Playwright + Chromium locale): home (hero,
  trending, Ultimi articoli, Percorsi, Newsletter, Speciale, Categorie),
  indice Percorsi (card, doppio-riquadro verificato assente dopo il fix),
  dettaglio Percorso (tappe, pillar con risalto teal, marcatore "In
  arrivo").
- **Contrasto AA**: bug reale trovato sul fallback dello Speciale
  (sfondo chiaro sotto testo bianco quando manca il record SpecialPage
  'turing') e corretto con uno scrim scuro CSS-only, indipendente dal
  contenuto dinamico.
- **Tastiera/focus**: nessuna regressione; aggiunto focus visibile sui
  link del breadcrumb del dettaglio Percorso (unico punto scoperto da
  CSS legacy).
- **`prefers-reduced-motion`**: coperto dalla regola di sistema già
  presente in Kairus Editorial Foundations V1 (`[class^="kairus-"]`),
  non ridefinita qui.
- **SEO/dati strutturati**: nessuna modifica a title/meta/canonical/JSON-LD
  in nessuna delle tre pagine — solo markup del corpo.
- **Suite di test**: 3943 test, 3932 passati, 11 skip preesistenti, 0
  fallimenti sull'intera suite del repository (non solo i file toccati).
  Un singolo test estraneo al perimetro (`test_autore_avatar_is_loaded_eagerly_not_lazily`,
  pagina Autore, mai toccata da questo cantiere) è risultato flaky una
  volta su ~10 run per un nome Faker casuale con apostrofo che veniva
  doppiamente escapato — riprodotto e confermato pre-esistente/intermittente,
  non un effetto di questo cantiere; non modificato.
- **Pint**: `--dirty` pulito su ogni commit; `--test` sull'intero
  repository mostra solo violazioni preesistenti in file mai toccati da
  questo cantiere (Social Distribution, Newsletter model, EditorialRadar,
  ResponsiveImageVariantServiceTest) — documentate, non corrette (fuori
  perimetro).
- **`git diff --check`**: pulito sull'intero diff del branch.

## Decisioni degne di nota

- **Riformulazione minima del conteggio articoli e del suggerimento
  pillar sull'indice Percorsi**: "N articolo/i pubblicato/i" → "N
  articolo/i" e "Da qui si parte" + titolo → "Si parte da: " + titolo.
  Stesso dato (`published_articles_count`, `pillarArticle->title`), solo
  la formula testuale — allineata a quella già in uso sulla home
  (paths-discovery) o alla API fissa del componente condiviso
  (`x-kairus.path-card`). I test pre-esistenti che assertavano la vecchia
  stringa sono stati aggiornati di conseguenza.
- **Doppio riquadro sulle card dell'indice Percorsi**: la classe legacy
  `.path-card` (necessaria sull'`<li>` per il JS del toggle "Anteprima" e
  per il CSS `:hover`/`:focus-within` che lo governa) applicava un
  riquadro completo che si sommava a quello, distinto, di
  `x-kairus.path-card` annidato. Risolto con `.kairus-path-card-host`
  (selettore raddoppiato `.kairus-path-card-host.kairus-path-card-host`
  per la specificità, restando comunque puramente `.kairus-*`) che
  neutralizza solo lo stile di riquadro legacy, lasciando intatto il
  ruolo funzionale della classe. Il toggle/pannello "Anteprima" portano
  ora anche una classe kairus- propria (`__toggle`/`__preview`, solo
  margini) invece di essere referenziati per nome legacy nel CSS nuovo —
  necessario perché `editorial-system.css` deve restare interamente
  `.kairus-*` (verificato dal test di isolamento).
- **DOM vs. ordine visivo nell'hero**: il riordino del DOM per la
  lettura lineare (testo prima dell'immagine) avrebbe anche invertito
  l'ordine visivo del grid a due colonne legacy; risolto con
  `.kairus-hero-story__media { order: -1; }`, che riporta l'immagine a
  sinistra visivamente a ogni breakpoint senza toccare il CSS Grid
  legacy.
- **Tappe del dettaglio Percorso, adozione piena non "host"**: a
  differenza della card dell'indice, le tappe non avevano un vincolo JS
  stateful da preservare (i tre link fratelli cover/titolo/CTA erano
  ridondanti, mai annidati) — quindi `x-kairus.path-step` sostituisce
  interamente lo stile di riquadro legacy (`.path-step` con la sua
  griglia a colonne fissa/linea di connessione), invece di conviverci
  come nel caso dell'indice. I marcatori decorativi "In arrivo"/"Percorso
  concluso" (aria-hidden, senza href) restano legacy invariati: non hanno
  un href reale, quindi nessun contratto compatibile con `path-step`.
- **Microcopy imposta dal contratto fisso di `path-step`**: il
  componente richiede sempre `label` e mostra sempre uno `state`
  testuale ("Disponibile"/"Tappa attuale"/"Prossimamente"). Il pillar usa
  l'etichetta originale "Punto di partenza" con `state="current"` (stesso
  risalto teal che aveva `.path-step--pillar`); le altre tappe usano
  "Tappa N" con lo stato di default. È l'unica microcopy nuova introdotta
  in questo cantiere, imposta dall'API del componente condiviso — mai un
  dato inventato (numero/titolo/href restano quelli reali).
- **Eyebrow non ritoccati nel dettaglio Percorso**: `.kairus-eyebrow`
  avrebbe avuto specificità inferiore (1 classe) a quella già presente nel
  CSS legacy (`.path-detail .eyebrow`, 2 classi, e
  `.path-detail .path-hero__copy .eyebrow`, 3 classi) — un'aggiunta
  sarebbe stata inerte, mai applicata. Verificato prima di scriverla,
  scartata per non aggiungere classi senza alcun effetto visivo.

## Rischi residui

- Il "cambio di registro" della sequenza tappe (evidenziazione quando il
  Percorso attraversa più categorie) resta invariato nella logica dati,
  ma la sua resa visiva ora dipende interamente dallo stile proprio di
  `x-kairus.path-step__category` — nessuna regressione riscontrata nei
  test, ma non è stato oggetto di uno screenshot dedicato multi-categoria.
- Le microcopy "Tappa N" / "Disponibile" sono nuove per il pubblico:
  un cambiamento di tono minimo ma reale nella pagina di dettaglio,
  imposto dal contratto del componente condiviso (vedi sopra) — vale la
  pena tenerlo presente in un'eventuale revisione editoriale futura, pur
  non essendo né un dato né un'invenzione di contenuto.

## Lavoro deliberatamente escluso

- **Hero home (evidenza)**: markup manuale con i token, non
  `x-kairus.article-card`, perché quel componente renderizza sempre un
  `<h3>` interno — incompatibile con l'H1 unico di pagina richiesto qui.
- **Speciale Turing e Categorie home**: nessun componente dedicato nelle
  fondamenta V1 (banner promozionale, carosello) — solo classi CSS
  namespaced sulla struttura esistente, come previsto dal piano stesso
  ("altrimenti usa classi namespaced senza creare un undicesimo
  componente").
- **Hero e pillar del dettaglio Percorso**: CSS legacy con rifiniture
  puntuali (bilanciamento titolo, focus sul breadcrumb) — non un'adozione
  di componente, per la stessa ragione dell'hero home (hero) e perché il
  markup a due blocchi del pillar non corrisponde al contratto a
  singolo-link di alcun componente della V1.
- **Form "Avvisami quando continua"**: fuori perimetro esplicito (toggle
  JS proprio, `content-clusters/partials/subscribe-form.blade.php` mai
  aperto né modificato).
