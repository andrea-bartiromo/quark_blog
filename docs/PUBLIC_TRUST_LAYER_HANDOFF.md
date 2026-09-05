# Trust Layer pubblico — Handoff (Cantiere H)

Adozione/rifinitura dei componenti Kairus sugli attributi di fiducia
editoriale realmente disponibili sulla pagina articolo.

## SHA

- Base: `feat/kairus-search-refresh` @ `8c29768bb1cd602a93f8924bc15ee97d5a7fb4a1`
- Branch: `feat/kairus-public-trust-layer`
- 3 commit (più i commit di chiusura del Prompt 210-211), nessuna PR,
  nessun merge, nessun deploy.

## Commit

1. `fadfcce` — docs: audit + invarianti (nessuna invenzione).
2. `0381ec0` — feat: bio/social autore, rifinitura crediti cover.
3. `f2fc88e` — test+docs: QA (mobile/tastiera/contrasto/stampa) +
   copertura combinazione completa/nessun blocco vuoto.

## File toccati

**Documentazione (5):** `docs/PUBLIC_TRUST_LAYER_{AUDIT,INVARIANTS,QA,HANDOFF}.md`
+ questo stesso file.

**CSS (1 solo file):** `public/css/editorial-system.css`.

**Viste (2):** `resources/views/articles/partials/{author-card,body}.blade.php`
— entrambe già in perimetro dal Cantiere E, qui estese con dati reali
non ancora surfaced.

**Test (1 nuovo):** `PublicTrustLayerTest` (9 test).

Nessun controller, route, model, migration, config, admin, redazione
toccato — verificato con `git diff --name-only`.

## Matrice: dato reale → origine → rendering → fallback

| Dato | Origine | Rendering | Fallback quando assente |
|---|---|---|---|
| Nome autore | `User::name` | `x-kairus.article-meta` (hero), author-card | Sempre presente (colonna non nullable) |
| Foto autore | `User::photo` | `<img>` in author-card | Iniziali (`mb_substr(name, 0, 2)`) |
| **Bio autore** | `User::bio` | `<p class="kairus-author-card__bio">` | Paragrafo omesso (`filled()`) — mai un testo generico |
| **Twitter autore** | `User::twitter` (handle, es. "@nome") | Link `https://twitter.com/{handle senza @}`, `rel="noopener noreferrer"` | Omesso se vuoto |
| **LinkedIn autore** | `User::linkedin` (URL completo) | Link diretto, `rel="noopener noreferrer"` | Omesso se vuoto |
| Data pubblicazione | `Article::published_at` | `x-kairus.article-meta` | Sempre presente |
| **Data di aggiornamento** | — | **Mai renderizzata** | `updated_at` giudicato inaffidabile (vedi invarianti) — nessun fallback, la funzione è del tutto esclusa |
| Fonti | Testo libero (`explode('---', body)`) | `x-kairus.trust-panel` slot `sources` | Pannello assente se nessun testo dopo `---` |
| **`primary_sources` strutturato** | — | **Non renderizzato** | Dato esiste nel DB ma nessuna vista pubblica in questa catena lo legge — fuori perimetro, sovrapposizione con branch non mergiati |
| Didascalia/credito/fonte/licenza cover | `Article::cover_caption/credit/source/license` | `<figure>` in `<details class="cover-info">`, token via `.kairus-cover-info*` | Ogni riga omessa indipendentemente se vuota; l'intero pannello omesso se tutte e 4 assenti |
| **Revisioni editoriali** | `ArticleRevision` (esiste) | **Non renderizzate** | Mai interrogate da `ArticleController::show()` — fuori perimetro "solo Blade+CSS" |
| **Metodologia pubblica** | — | **Non collegata** | Nessuna route `/metodologia` in questa catena |
| **Disclosure** | — | **Non introdotte** | Nessun campo reale in `Article`/`User` |
| `Person` JSON-LD | `User::name` + `route('autore', ...)` | Minimale, invariato | Mai `sameAs`/`jobTitle`/bio |

## Sovrapposizione — branch Trust Layer non mergiati

Confermata identica a quanto trovato nel Cantiere E: `feat/public-article-sources-v1`
(#516), `feat/public-article-revision-transparency-v1` (#517),
`feat/public-author-pages-v1` (#518), `feat/trust-policy-pages-v1`
(#519) — codice reale, ma solo su branch non mergiati, mai fusi in
questa catena. Nessun merge/rebase/cherry-pick eseguito.

## Verifiche eseguite

- **Suite mirata**: `PublicTrustLayerTest` (9 test) + design system +
  cover/structured-data/image-viewer (103 test complessivi in
  un'esecuzione), 0 fallimenti.
- **QA visiva**: mobile 320/375px senza overflow con dati
  volutamente lunghi (bio, handle Twitter, URL LinkedIn, URL fonte);
  focus da tastiera visibile sui nuovi link; stampa verificata (nessun
  blocco Trust nascosto).
- **Privacy**: nessun dato interno (user_id grezzo, email,
  verification_notes/status, verified_by) esposto in nessuna vista
  toccata.
- **Isolamento**: nessun controller/route/model/migration/config/
  admin/redazione toccato.

## Rischi residui

- Nessuno specifico a questo cantiere. L'overflow pre-esistente della
  sidebar (Cantiere E/F/G) resta presente sulla pagina articolo, non
  aggravato qui.

## Lavoro deliberatamente escluso

- Fonti strutturate (`primary_sources`), trasparenza di revisione,
  pagina autore pubblica arricchita, metodologia, disclosure: nessun
  dato/route reale disponibile in questa catena di branch — vedi
  `PUBLIC_TRUST_LAYER_AUDIT.md`.
- `/autore/{user}`: fuori dalle sette superfici del progetto, mai
  toccata.
