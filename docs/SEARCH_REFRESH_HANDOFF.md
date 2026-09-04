# Ricerca editoriale — Handoff (Cantiere G)

Adozione di Kairus Editorial Foundations V1 su `/ricerca`.

## SHA

- Base: `feat/kairus-archives-refresh` @ `a56d4c5191e1eb6ca3d28ef2f1e3259537fac628`
- Branch: `feat/kairus-search-refresh`
- 4 commit (più i commit di chiusura del Prompt 181), nessuna PR, nessun
  merge, nessun deploy.

## Commit

1. `b582532` — docs: audit filemap + invarianti.
2. `9a20009` — test: congela il contratto prima di ogni modifica.
3. `d633a21` — feat: adozione page-header/form-shell/article-card/empty-state.
4. `698d12a` — docs: QA viewport + note isolamento.

## File toccati

**Documentazione (4):** `docs/SEARCH_REFRESH_{FILEMAP,INVARIANTS,VIEWPORTS,HANDOFF}.md`.

**CSS (1 solo file):** `public/css/editorial-system.css`.

**Viste (1):** `resources/views/ricerca.blade.php`.

**Test (3):** `SearchRefreshTest` (nuovo), `SearchControllerTest`
(2 asserzioni letterali aggiornate alla classe aggiuntiva di
`x-kairus.empty-state`, stesso testo/livello di heading),
`KairusEditorialFoundationsIsolationTest` (`ricerca.blade.php` esce
dall'elenco vietato).

Nessun controller, servizio di ricerca, route, model, migration,
config, admin, redazione toccato — verificato con `git diff --name-only`
sull'intero cantiere.

## Componenti Kairus montati

| Blocco | Componente | Note |
|---|---|---|
| Hero (compact) | `x-kairus.page-header` (`compact`) | Era già una superficie piatta |
| Form di ricerca + filtri | `x-kairus.form-shell` | Il `<form>` reale (method/action/nomi campi) è invariato, avvolto nello slot `form` |
| Risultati | `x-kairus.article-card` + `x-kairus.article-meta` (autore, data, tempo di lettura) | Algoritmo di ricerca, ranking, paginazione invariati |
| Stato iniziale / nessun risultato | `x-kairus.empty-state` | Azione "Rimuovi i filtri" preservata nello slot `action` |

**Non applicabile** (documentato, non un'esclusione per scelta):
risultati Percorso/Concetto (Prompt 169-170) — `ArticleSearchService`
restituisce solo articoli, backend non esteso.

**Rifinitura accessibilità reale**: `kairus-focusable` aggiunto a
tutti i controlli del form (input, select, pulsanti, link Reset) —
nessuno aveva un `:focus-visible` dedicato prima (verificato via grep
su `public-unified.css`).

## Verifiche eseguite

- **Suite mirata**: 146 test, 146 passati (design system + ricerca +
  responsive-image + query-budget), 0 fallimenti.
- **Viewport QA**: 19/20 combinazioni pulite. Un overflow a 320px
  trovato su tutti e 4 gli stati, **verificato pre-esistente** (stesso
  bug della sidebar già documentato nel Cantiere E, qui riprodotto
  senza gli `!important` specifici della pagina articolo — conferma che
  vive nella struttura sidebar condivisa stessa,
  `components/sidebar.blade.php`/`public-unified.css`, non in un punto
  isolato). Non corretto qui, segnalato per il Cantiere I.
- **Sicurezza**: query con `<script>` verificata sempre escapata.
- **SEO**: `robots noindex,follow` invariato su tutti gli stati.
- **Zero-result tracking**: invariato, non toccato.
- **Isolamento**: nessun controller/servizio/route/model/migration/
  config/admin/redazione toccato.

## Rischi residui

- L'overflow a 320px nella sidebar (vedi sopra) resta presente su
  questa pagina come sulle altre che includono
  `components/sidebar.blade.php` — non introdotto né aggravato qui.

## Lavoro deliberatamente escluso

- Risultati Percorso/Concetto: dati non disponibili in questa catena
  di branch, backend non esteso.
- Un landmark `role="search"` esplicito: non esisteva prima, fuori
  perimetro di questo cantiere aggiungerne uno nuovo.
