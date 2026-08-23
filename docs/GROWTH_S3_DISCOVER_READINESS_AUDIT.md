# Growth S3 — Google Search/Discover Readiness Audit & Hardening

Audit tecnico, non di contenuto: nessuna "Discover hack" non verificabile,
solo elementi che Google Search Central documenta esplicitamente come
requisiti/raccomandazioni tecniche.

## Lavoro precedente già mergiato (non riaudità da zero)

Una missione SEO precedente ("S3 SEO") è già stata completata e mergiata
in `origin/main`: PR #216 (JSON-LD HTML-safe + coerenza lastmod), PR #246
(canonical su pagine istituzionali), PR #248 (S2-B: width/height reali
sulle immagini locali nel corpo articolo). Questo audit ha verificato lo
stato *attuale* del codice (non si è fidato del solo storico commit) prima
di decidere cosa restava da fare.

## Matrice di indicizzabilità (stato verificato)

| Superficie | Canonical | Robots meta | In sitemap | Note |
|---|---|---|---|---|
| Articolo pubblicato | ✅ `metaCanonicalUrl()` | ✅ `index,follow,max-image-preview:large` | ✅ | — |
| Articolo programmato/bozza | N/A (404) | N/A | ❌ (mai) | `Article::scopePublished()` esclude sempre `published_at` futuro, verificato anche su lookup diretto per slug — vedi anche il Public Date Leak Audit della Missione 3 di questo batch |
| Categoria | ✅ | ✅ | ✅ | — |
| Percorso (Content Cluster) | ✅ | ✅ | — | `ContentClusterController::show()` |
| `/ricerca` | — | noindex in-page (deliberato) | — | Resta crawlabile (robots.txt non la blocca) così i link interni restano validi, ma non indicizzabile — commento esistente nel codice conferma la scelta |
| Pagine istituzionali | ✅ (PR #246) | ✅ | ✅ | — |

## Elementi verificati e già corretti (nessuna azione)

- **Canonical**: presente su ogni pagina pubblica rilevante.
- **robots.txt**: statico, corretto — blocca `/admin/`, `/redazione/`,
  `/storage/`, `/api/`, dichiara `Sitemap: .../sitemap-index.xml`.
- **max-image-preview:large**: presente di default in
  `Article::metaRobots()`.
- **Sitemap index**: `sitemap.xml` + `news-sitemap.xml` correttamente
  referenziati da `sitemap-index.xml`.
- **Scheduled-date leakage**: nessuna, su nessuna superficie pubblica
  (verificato di nuovo, indipendentemente, anche nella Missione 3 di
  questo stesso batch — Article Calendar V1 — con audit dedicato).
- **JSON-LD**: `NewsArticle` + `BreadcrumbList` validi, HTML-safe
  (`JSON_HEX_*`), nessuna injection possibile via titolo/campi editoriali
  (verificato con test dedicato già esistente).

## Gap trovato e corretto in questa missione

**JSON-LD `ImageObject` senza `width`/`height`** —
`resources/views/articles/partials/structured-data.blade.php`. Google
raccomanda dimensioni dichiarate per l'immagine di un `NewsArticle`. Prima
di questa missione erano deliberatamente omesse perché "non garantite per
ogni upload" — ma verificato che `cover_image` risolve sempre a un file
locale sotto `public/assets/img/` (stesso pattern già usato altrove,
es. `ResponsiveImageVariantService::resolveForMarkup()`), quindi
`getimagesize()` locale (guardia `is_file()`, mai una richiesta di rete)
è sicuro ed economico. Applicato sia alla cover reale sia al fallback
raster globale. Se il file non esiste o non è leggibile, i campi restano
assenti — mai un valore inventato.

## Gap trovati ma esplicitamente NON corretti in questa missione

Il brief limita gli interventi a: dimensioni immagine mancanti,
robots/canonical errati, leak di date programmate, dati strutturati rotti.
Questi due non rientrano in nessuna delle quattro categorie e restano solo
documentati:

- **Citazioni/fonti non ipertestuali**: il blocco "Fonti"
  (`articles/partials/body.blade.php`) esegue `e()` sul testo intero,
  quindi eventuali URL scritti da un redattore non diventano mai `<a>`
  cliccabili/crawlabili. Trasformarli in link richiederebbe
  auto-linking di testo libero scritto da editor — superficie di
  rischio (validazione URL, XSS) che va oltre "hardening" e merita una
  missione propria con validazione esplicita.
- **Immagini esterne nel corpo senza width/height**: `S2-B` (PR #248) ha
  coperto solo le immagini locali per lo stesso motivo per cui questo
  audit non ha tentato un fetch di rete per la cover — ottenere le
  dimensioni di un'immagine esterna richiederebbe una richiesta HTTP
  verso un URL fornito dall'editor, la stessa classe di rischio SSRF già
  auditata e esclusa nella missione S7 Security di questo repository.
- **Assenza totale di `<lastmod>` in sitemap.xml**: scelta deliberata e
  già documentata nel codice (PR #216) — `updated_at` non è un segnale
  editoriale affidabile (viene toccato da ogni singola visualizzazione
  e dal flusso di verifica). Dichiarare comunque un `lastmod` sarebbe
  esattamente la "SEO superstition" che il brief chiede di evitare.
  Servirebbe prima una colonna dedicata (es. `content_updated_at`,
  aggiornata solo da modifiche editoriali reali) — fuori scope per una
  missione di hardening.

## Verdetto

Un solo gap correggibile entro lo scope dichiarato (dimensioni immagine
JSON-LD), corretto con test. Tutto il resto della matrice di
indicizzabilità è già corretto. **1 PR di hardening**, coerente con il
tetto massimo del brief.
