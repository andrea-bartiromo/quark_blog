# Contratti interni delle entità di contenuto (Cantiere 91)

Programma "100 cantieri Kairus". Documentazione pura: nessun comportamento
cambia con questo documento. Consolida in un unico posto ciò che oggi è
già vero nel codice ma sparso su più file, per chi deve capire "cosa
posso assumere su questa entità" senza rileggere ogni model/service da
zero. `tests/Feature/EntityContractsDriftTest.php` verifica che ogni
classe/metodo/scope citato qui esista ancora davvero.

Copre `Article`, `Category`, `ContentCluster` e la fondazione Content
Graph (`Concept`, `ConceptQuestion`, `ArticleConcept`, `ConceptAlias`).
Esclude deliberatamente ogni entità di comunicazione (Newsletter/social),
già coperta da `docs/SOCIAL_NEWSLETTER_OPERATIONAL_BOUNDARY.md`.

## Perché questo documento esiste

Prima di aprire questo cantiere è stato verificato un caso concreto di
drift: `docs/DOMANDE_DI_SCIENZA_FOUNDATION_DESIGN.md` affermava ancora
"il modello Question/Concept non è su `main`", quando invece `Concept` e
`ConceptQuestion` sono mergiati, wired in `/admin/concetti` e usati da
sette service reali (`app/Services/ContentGraph/*`). Quel documento è
stato corretto nello stesso cantiere (vedi in fondo). Un contratto
scritto una volta e mai riverificato è peggio di nessun contratto:
questo documento accetta esplicitamente quel rischio solo perché è
affiancato da un drift test, sullo stesso principio già in uso per
`docs/TRUST_LAYER_EDITORIAL_PROTOCOL.md` / `TrustEditorialProtocolDriftTest`.

## Principio comune a tutte le entità sotto

Ogni entità qui distingue esplicitamente tra:

- un campo/relazione grezzo, leggibile e scrivibile solo dall'admin
  (es. `Category::featuredArticle()`, `ContentCluster::pillarArticle()`);
- un accessor "per display pubblico" che ri-verifica le condizioni di
  pubblicabilità AL MOMENTO DELLA LETTURA, mai una copia cache di uno
  stato passato (es. `Category::featuredArticleForDisplay()`).

Questo perché un valore scelto editorialmente in un dato momento (un
articolo "in evidenza", un pillar article) può smettere di essere
idoneo più tardi — l'editore lo riporta in bozza, ne cambia la
categoria, lo elimina — senza che nessuno aggiorni esplicitamente la
scelta. Senza il doppio livello, il valore stantio verrebbe comunque
mostrato pubblicamente: un leak editoriale, non solo un dato vecchio.

## Article

- Stati: `Article::STATUS_DRAFT`, `STATUS_SCHEDULED`, `STATUS_PUBLISHED`
  (`app/Models/Article.php`).
- Visibilità pubblica reale: `Article::scopePublished()` — `status =
  'published' AND published_at <= now()`. Uno `status='published'` con
  `published_at` futuro (schedulazione) NON è ancora pubblico: nessun
  consumer di questa entità deve controllare solo `status`.
- Categoria primaria: colonna `category` (slug testuale, non foreign
  key tipizzata). Categorie secondarie: relazione `secondaryArticles()`
  su `Category` / `secondaryCategories()` su `Article` — un articolo può
  appartenere a più categorie, ma ne ha sempre esattamente una primaria.
- Sorgenti citate: tre formati distinti convivono e NON vanno confusi
  (consolidato dal Cantiere 84, `ContentSourcesRadarService`):
  `primary_sources` (campo strutturato) è soppresso pubblicamente in
  `articolo.blade.php` quando esiste una sezione manuale "Fonti" nel
  body (`ArticleManualSourcesDetector::hasManualSourcesSection()`); un
  terzo formato legacy delimitato da `---` è rilevato da
  `EditorialQualityChecker::hasDelimitedSourcesSection()`.

## Category

- Visibilità pubblica reale: `Category::scopePubliclyVisible()` /
  `isPubliclyVisible()` — `is_active=true` AND (`status='published'` OR
  (`status='scheduled'` AND `published_at<=now()`)). Nessun job
  schedulato promuove mai `scheduled`→`published`: la visibilità è
  calcolata dinamicamente a ogni lettura.
- `curator_note` (Cantiere 49): campo editoriale opzionale, sostituisce
  il testo "Editorial Focus" generico quando compilato. Nessuna
  generazione automatica.
- `featured_article_id` (Cantiere 50): selezione manuale per-categoria,
  ortogonale a `Article::featured` (hero sito-wide). Accessor pubblico
  sicuro: `featuredArticleForDisplay()` — richiede `STATUS_PUBLISHED`,
  `published_at` non nullo/futuro, E che l'articolo sia ancora associato
  a questa categoria (primaria o secondaria) ADESSO.

## ContentCluster ("Percorso")

- Visibilità pubblica reale: `ContentCluster::scopePubliclyVisible()` /
  `isPubliclyVisible()` — `is_active=true` AND (`publish_at` nullo O
  `publish_at<=now()`). `lifecycle_status` (`isUpdating()`/
  `isComplete()`) è ortogonale: descrive la maturità editoriale, mai la
  raggiungibilità pubblica.
- Iscrizioni email: `acceptsPathSubscriptions()` — unica definizione di
  "questo Percorso accetta nuove iscrizioni adesso" (`is_active` E
  ancora in aggiornamento). Riusata sia dalla UI pubblica sia dal
  controller di iscrizione: mai duplicata.
- Anteprima admin (Cantiere 48): `Admin\ContentClusterController::preview()`
  riusa la stessa view pubblica con un flag `previewMode` che (a)
  disattiva analytics via `AnalyticsExclusionService::shouldLoadAnalytics($request,
  previewMode: true)` e (b) sostituisce il form di iscrizione live con un
  messaggio statico quando il cluster è già pubblico e in aggiornamento.
- `pillar_article_id`: come `Category::featured_article_id`, mostrato sia
  come sezione dedicata sia nella griglia normale (mai un'esclusione a
  vicenda) — stesso principio riusato dal Cantiere 50.

## Content Graph: Concept / ConceptQuestion (fondazione "Domande di scienza")

Fondazione già mergiata e ammessa solo in admin
(`app/Http/Controllers/Admin/ConceptController.php`,
`ConceptQuestionController.php`, route `/admin/concetti`). **Nessuna
pagina pubblica esiste oggi**: nessuna route pubblica referenzia
`Concept`/`ConceptQuestion` (verificato — vedi
`docs/DOMANDE_DI_SCIENZA_FOUNDATION_DESIGN.md`, aggiornato in questo
stesso cantiere per correggere lo stato stantio "non è su main").

- `Concept`: stati `STATUS_DRAFT`, `STATUS_ACTIVE`, `STATUS_INACTIVE`.
  Relazioni: `aliases()` (`ConceptAlias`), `articleLinks()`
  (`ArticleConcept`), `questions()` (`ConceptQuestion`).
- `ConceptQuestion`: stati `STATUS_DRAFT`, `STATUS_APPROVED`,
  `STATUS_INACTIVE`. Contratto di campi: `question`, `slug` (auto-slug
  dal testo se assente), `concept_id` (relazione `concept()`),
  `target_article_id` (opzionale, relazione `targetArticle()`),
  `answer_summary`, `sort_order`, `status` — esattamente il contratto che
  `docs/DOMANDE_DI_SCIENZA_FOUNDATION_DESIGN.md` anticipava per la PR
  #279.
- Pubblicabilità concettuale (non ancora collegata a nessuna route
  pubblica): `ConceptQuestion::scopePubliclyAnswerable()` — approvata E
  `target_article_id` valorizzato E `answer_summary` non vuoto E
  l'articolo target soddisfa `Article::published()`. Stesso principio
  del doppio-livello sopra: la condizione è verificata a ogni lettura,
  mai una promozione automatica one-shot.
- `ArticleConcept`: pivot tipizzata `relation_type` (`primary`/
  `supporting`) + `weight` — collegamento articolo↔concetto sempre
  esplicito, mai automatico. `ConceptSuggestionService` (Mission 20)
  suggerisce concetti riconosciuti nel testo mentre un editor scrive,
  ma il collegamento avviene solo tramite l'azione "Collega" esplicita
  dell'editore — mai un link automatico.

## Cosa NON consolida questo documento

- Non introduce nessuna nuova entità né nuova migrazione.
- Non implementa la pagina pubblica `/domande/{slug}` né l'hub
  `/domande` descritti (come design, non ancora costruiti) in
  `docs/DOMANDE_DI_SCIENZA_FOUNDATION_DESIGN.md`.
- Non tocca il gate B-45 (`docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md`),
  che resta un'entità distinta (Trust Layer) già documentata altrove.
