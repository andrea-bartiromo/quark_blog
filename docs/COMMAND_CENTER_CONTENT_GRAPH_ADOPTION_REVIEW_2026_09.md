# Adoption review — Command Center / Dashboard Export / Content Graph (Prompt 091-100)

Verifica read-only che le tre superfici siano realmente adottate
nell'unica interfaccia admin (non isole scollegate), e che restino
regression-proof.

## Cosa è già adottato correttamente

- **Command Center** (`/admin/operazioni-editoriali`,
  `EditorialOperationsDashboardController`) è in sidebar
  (`resources/views/layouts/admin.blade.php:195`, etichetta "Operazioni
  editoriali") dentro il gruppo "Analisi".
- **Dashboard Export** non ha una voce di sidebar propria — per design:
  e' incorporato come modulo `<details>`/form direttamente dentro la
  pagina Command Center stessa
  (`resources/views/admin/editorial-operations-dashboard.blade.php:6-36`,
  azione `admin.editorial-operations.export`). Il commento in
  `routes/web.php:142` ("resta collegato all'unico Command Center") conferma
  che questa e' la scelta intenzionale, non un collegamento mancante.
- **Content Graph** (`/admin/concetti`, `ConceptController`) e' in
  sidebar (`resources/views/layouts/admin.blade.php:158`, etichetta
  "Concetti") dentro il gruppo "Contenuti".

Nessuna delle tre e' un'isola: tutte raggiungibili dalla stessa
navigazione admin condivisa da ogni altra funzione editoriale.

## Gap trovato e corretto: nessuna regressione della sidebar era testata

`tests/Feature/Admin/AdminNavigationTest.php` ha test dedicati (etichetta
presente, route corretta, stato "active") per quasi ogni voce piu'
vecchia della sidebar (Dashboard, Articoli, Categorie, Newsletter,
Turing, Statistiche, Attivita, Progettazione, Comunicazione...), ma
**"Concetti" e "Operazioni editoriali" non comparivano in nessuna di
queste liste/test dedicati** — la lista `test_all_previous_navigation_items_are_still_present`
risale a prima della loro adozione e non era mai stata aggiornata.
Un refactor che avesse rimosso silenziosamente una delle due righe dalla
sidebar sarebbe passato inosservato (l'unico test generico,
`test_no_navigation_links_point_to_missing_routes`, verifica solo che le
rotte USATE non siano rotte — non che una riga non sia stata rimossa del
tutto).

**Corretto**: aggiunte entrambe le etichette/route alle liste esistenti,
piu' due nuovi test dedicati allo stato "active"
(`test_the_concepts_link_is_active_for_all_concept_routes`, verificato
sia su `admin.concepts.index` sia su `admin.concepts.create` — lo stesso
link resta attivo su tutte le sotto-rotte concetti, per design
`routeIs('admin.concepts.*')` —
`test_the_editorial_operations_link_is_active_on_the_command_center_page`).

## Content Graph — stato del gate storico

`docs/MISSION_64_CONTENT_GRAPH_PHASE_G_GATE.md` (2026-08-27) elenca
rischi residui dichiarati all'epoca (nessuna suite eseguita sullo SHA
finale in quella sessione, copertura Playwright della coda Operazioni
editoriali solo via HTTP non via browser, normalizzazione Unicode
alias non definita da prodotto, conteggi query non ancora verificati su
MariaDB reale per quella fase). Il codice e' comunque gia' su `main` oggi
e passa la suite completa e i workflow CI MariaDB esistenti — non ho
ri-eseguito da zero quel gate specifico (fuori perimetro di
un'"adoption review": quello era un gate funzionale/dati, questo verifica
solo l'integrazione nell'interfaccia admin). Chi riprende quel gate
specifico dovrebbe farlo come missione dedicata, non presumere che
l'adozione riuscita nella sidebar implichi che ogni rischio residuo li'
elencato sia stato chiuso.

## Cosa questo audit non ha fatto

- Non ha ri-eseguito il gate Fase G di Content Graph (functional/data
  gate, fuori perimetro di un'adoption review dell'interfaccia).
- Non ha aggiunto copertura Playwright per la coda Operazioni editoriali
  (rischio residuo gia' dichiarato altrove, non nuovo).
- Non ha toccato alcun dato di produzione, articolo pubblicato/programmato
  o migration.

## Esito

Un gap reale trovato e corretto: la sidebar Command Center/Content Graph
non aveva alcuna regressione testata. Corretto con due nuovi test dedicati
e l'aggiornamento delle due liste esistenti. Suite completa verde.
