# Riconciliazione Trust — Fonti primarie e Revisioni qualificate

Ultimo passo della "Integrazione controllata della ristrutturazione
Kairus": porta sulla catena Kairus (già interamente in `main`) le due
funzionalità Trust Layer che esistevano solo su branch non mergiati —
`feat/public-article-sources-v1` (#516) e
`feat/public-article-revision-transparency-v1` (#517) — mai fuse in
nessun cantiere Kairus (E-J), come documentato esplicitamente da
ciascuno di essi (`ARTICLE_REFRESH_HANDOFF.md`,
`PUBLIC_TRUST_LAYER_HANDOFF.md`: "nessun merge/rebase/cherry-pick
eseguito, sovrapposizione confermata solo via audit").

## Origine

- **#516** `feat/public-article-sources-v1` — SHA `d8fb5eaeb5cdf7eec56476a9af9478d484468a5a`, base `a094e7e0` (SHA iniziale di tutta questa integrazione). Branch intatto, non toccato, non eliminato.
- **#517** `feat/public-article-revision-transparency-v1` — SHA `6777525215807de672c52353eba8efe528302127`, stessa base. Branch intatto, non toccato, non eliminato.

Entrambi predatano l'intera sequenza Kairus (Foundations + Cantieri
D-J): nessun cherry-pick diretto era possibile (ancestry incompatibile
dopo gli squash-merge di questa integrazione), quindi ogni file è stato
letto per intero da entrambi i branch e riportato a mano sulla catena
Kairus attuale, risolvendo i conflitti semantici emersi.

## Conflitti semantici e come sono stati risolti

1. **Meta line dell'hero** (`hero.blade.php`): #517 assumeva il markup
   legacy pre-Kairus (`<time>` accanto a "Aggiornato il" scritto a
   mano). Cantiere E l'ha sostituito con `x-kairus.article-meta`.
   Soluzione: il componente aveva **già** un prop `updatedAt` dormiente
   (Missione 06, Foundations — mai invocato da nessuna vista prima
   d'ora), che omette l'intero `<li>` quando il valore è null — la
   stessa condizione voluta da #517, zero modifiche al componente
   condiviso.
2. **Pannello Fonti primarie** (`primary-sources.blade.php`): non unito
   a `x-kairus.trust-panel` (Cantiere E) nonostante il nome simile —
   per esplicita scelta già documentata dagli autori originali di #516
   ("Fonti" legacy testo libero e `primary_sources` strutturato sono
   dati diversi, non garantito coincidere per lo stesso articolo).
   Reso invece un pannello separato, riusando `article-premium__panel`
   (stessa base visiva già condivisa da `.kairus-cover-info`, Cantiere
   H) con un marcatore `kairus-primary-sources`.
3. **Formato data "Aggiornato il"**: #517 assumeva
   `isoFormat('D MMMM YYYY')` (mese esteso, es. "12 gennaio 2026").
   `x-kairus.article-meta` usa `translatedFormat('d M Y')` (mese
   abbreviato, es. "12 gen 2026") — stesso formato già in uso per
   `publishedAt` su ogni pagina articolo dal Cantiere E. Il test
   `ArticlePublicRevisionTransparencyTest::test_updated_date_is_shown_in_italian_locale_not_as_a_raw_utc_timestamp`
   aveva comunque una regex abbastanza generica da accettare entrambi i
   formati; adattata solo la posizione di "Aggiornato il" nel markup
   (dentro lo stesso `<time>` insieme alla data, per costruzione del
   componente Kairus — non un `<time>` che avvolge solo la data nuda
   come nel markup legacy).
4. **`Article::primary_sources`**: nessuna migration necessaria — la
   colonna esiste già nello schema (migration verification-fields,
   feature indipendente, mai collegata a nessuna vista pubblica prima
   d'ora).

## Cosa introduce

- `ArticlePrimarySourcesParser` (presentation-only): classifica ogni
  riga di `Article::primary_sources` come link (URL assoluto http/https
  o DOI, normalizzato a `doi.org`) o testo semplice — mai un'estrazione
  parziale da testo misto.
- `<x-article.primary-sources>`: pannello "Fonti primarie", separato dal
  pannello Fonti legacy, `rel="nofollow noopener noreferrer"` sui link
  esterni, escaping HTML garantito da Blade.
- `ArticleRevisionTransparencyService::lastEditorialUpdate()`
  (presentation-only): null a meno che esista una revisione con
  `created_at > published_at` **e** contenuto
  (title/excerpt/body/category) davvero diverso dallo stato attuale —
  mai dedotta dal solo `updated_at` (inaffidabile: toccato anche da
  `increment('views')` e dal flusso di verifica editoriale).
- "Aggiornato il" integrato in `x-kairus.article-meta` (prop `updatedAt`
  già esistente); `dateModified` aggiunto in
  `structured-data.blade.php` con la stessa condizione — chiave del
  tutto assente quando null.

## File toccati (19, 3 commit applicativi + 1 di documentazione)

| Categoria | File | Commit |
|---|---|---|
| Servizi (2, nuovi) | `ArticlePrimarySourcesParser.php`, `ArticleRevisionTransparencyService.php` | fonti / revisioni |
| Controller (1) | `ArticleController.php` (+2 righe view-data, +2 import) | fonti + revisioni |
| Viste (3) | `articolo.blade.php` (+componente), `hero.blade.php` (+prop), `structured-data.blade.php` (+dateModified) | fonti / revisioni / revisioni |
| Componenti (1, nuovo) | `components/article/primary-sources.blade.php` | fonti |
| Seeder (1) | `BrowserTestSeeder.php` (+fixture `primary_sources`) | fonti |
| Test Feature (4) | `ArticlePublicPrimarySourcesTest` (nuovo), `ArticlePublicRevisionTransparencyTest` (nuovo, 1 regex adattata), `PublicTrustLayerTest` (1 test → 3), budget +1 in 3 file | fonti / revisioni / componenti-test |
| Test Unit (2, nuovi) | `ArticlePrimarySourcesParserTest`, `ArticleRevisionTransparencyServiceTest` | fonti / revisioni |
| Test browser (1, nuovo) | `public-primary-sources.spec.js` | fonti |
| Documentazione (3) | `TRUST_LAYER_PUBLIC_SOURCES_V1.md`, `TRUST_LAYER_REVISION_TRANSPARENCY_V1.md`, questo file | fonti / revisioni / documentazione |

Nessun controller diverso da `ArticleController`, nessuna route, model,
migration, config toccati. `x-kairus.trust-panel` e
`x-kairus.article-meta` (componenti condivisi) **non modificati** — solo
un prop già esistente del secondo, mai invocato prima, ora usato.
`kairus-sidebar-layout`, il wrapper tabelle responsive, i crediti/focus
cover restano invariati (nessun file di quel perimetro toccato).

## Budget query (+1, una query bounded su `article_revisions` per pagina articolo)

- `GrowthS2E2ECertificationTest`: 16 → 17
- `PublicPageQueryBudgetTest` (pagina articolo): 15 → 16
- `SecondReadAnalyticsTest`: 16 → 17

## Decisione editoriale "Aggiornato il" — implementata come specificato

- Mai `updated_at` grezzo mostrato o usato per `dateModified`.
- "Aggiornato il" mostrato solo quando `ArticleRevisionTransparencyService`
  trova una revisione post-pubblicazione qualificata.
- Stessa prova (evidenza qualificata) usata per `dateModified`, formato
  ISO 8601.
- Integrata in `x-kairus.article-meta`, mai ripristinato
  `.article-premium__meta`.
- `PublicTrustLayerTest` aggiornato: il divieto assoluto sostituito da
  tre test specifici (nessuna data senza revisione qualificata; data
  mostrata con revisione qualificata; `updated_at` da solo non basta).

## Test

Suite mirata di questa riconciliazione: 97 test, 97 passati (fonti
primarie + revisioni qualificate + isolamento + article-refresh +
i tre budget aggiornati + cover-image-viewer), 0 fallimenti.

Suite completa: 4058 test, 4047 passati, 11 skip (pre-esistenti, non
correlati), **0 fallimenti** — 34 test in più rispetto a fine Cantiere J
(4024), corrispondenti esattamente ai nuovi test di questa
riconciliazione.

Pint pulito su tutti i file toccati, ad ogni commit. `git diff --check`
pulito sull'intero branch.

## Nessuna funzione simulata o inventata

Ogni dato mostrato proviene da colonne/relazioni già esistenti
(`Article::primary_sources`, `article_revisions`) scritte da flussi
editoriali già in produzione, mai da questa riconciliazione. Nessun
dato di fallback o placeholder introdotto.

## Isolamento

Nessun controller diverso da `ArticleController`, nessuna route, model,
migration, config, `admin`/`redazione` toccati —
`KairusEditorialFoundationsIsolationTest` verde, nessuna nuova voce
necessaria.

## Rischi residui

- Nessuno specifico a questa riconciliazione. I rischi residui già
  noti dalla catena Kairus (ordine di caricamento CSS, possibile bug
  "1fr nudo" altrove in `public-premium.css`) restano invariati — vedi
  `docs/KAIRUS_PUBLIC_RESTRUCTURING_FINAL_HANDOFF.md`.

## Esito per #516 e #517

Questa PR **sostituisce** (non "rifiuta") #516 e #517: la stessa
funzionalità, riconciliata sulla catena Kairus, con conflitti semantici
risolti a mano. I branch originali restano intatti e non eliminati; le
due PR vengono chiuse con riferimento esplicito a questa.
