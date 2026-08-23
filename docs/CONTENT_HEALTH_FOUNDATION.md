# Content Health Foundation

## Obiettivo

Content Health e una checklist editoriale trasparente. Non e un punteggio SEO e non puo bloccare salvataggio, review, scheduling o pubblicazione.

La foundation e deliberatamente separata dall'editor Admin per evitare collisioni con le PR attive su Article/Admin. Il consumer futuro puo mostrare i risultati come pannello "Controlli editoriali" senza spostare la logica nel Blade.

## Contratto

`App\Services\ContentHealth\ArticleContentHealthService::evaluate(Article)` restituisce una collection ordinata di check con forma stabile:

- `id`
- `label`
- `status`
- `reason`
- `action_url` (nullable in questa foundation)

Status ammessi:

- `OK`
- `WARNING`
- `NOT_APPLICABLE`

Non esiste uno score aggregato.

## Check V1 implementati

### cover

`OK` quando `cover_image` e presente, altrimenti `WARNING`.

### cover_alt

Se non esiste una cover: `NOT_APPLICABLE`.
Se esiste una cover: `OK` quando `cover_alt` e presente, altrimenti `WARNING`.

### cover_attribution

Se non esiste una cover: `NOT_APPLICABLE`.
Se esiste una cover: richiede un credito e almeno una fonte tra `cover_source` e `cover_source_url`.

Non viene imposto in V1 che `cover_license` sia obbligatorio in ogni caso: il dominio corrente permette valori/licenze differenti e la missione non fornisce una policy universale da inventare.

### summary

`OK` quando `excerpt` e presente. Nessuna soglia arbitraria di lunghezza.

### seo_metadata

`OK` quando sia `seo_title` sia `seo_description` sono valorizzati. Se manca uno dei due, il check e `WARNING` e nomina esattamente il campo mancante.

Il warning riconosce esplicitamente che i fallback SEO del sito continuano a funzionare: non interpreta un campo editoriale mancante come errore di rendering.

### internal_links

`OK` quando il body contiene almeno un link HTML verso `/articolo/...`, relativo o assoluto. Non modifica il body e non usa il sistema di suggerimenti come proxy di un link realmente inserito.

### category

`OK` quando la categoria principale e valorizzata. La foundation non duplica qui la validazione DB/config gia gestita dai workflow articolo.

### percorso

`OK` quando l'articolo appartiene ad almeno un `ContentCluster`. Se la relazione non e gia eager-loaded il servizio esegue al massimo una query per caricarla; se e precaricata, questo check non aggiunge query.

L'assenza di un Percorso resta un `WARNING` editoriale, non un errore: non ogni articolo deve necessariamente appartenere a un Percorso.

### sources

`OK` quando `primary_sources` e valorizzato. Nessuna analisi semantica automatica delle fonti in V1.

### freshness

`NOT_APPLICABLE` in V1. Nel dominio corrente non e documentata una soglia universale di anzianita oltre la quale un articolo diventa automaticamente "stale". Definire 6/12/24 mesi senza evidence trasformerebbe la checklist in uno score arbitrario.

## Check deliberatamente differiti

- secondary categories come check qualitativo: il campo esiste, ma non c'e una regola che le renda obbligatorie;
- `cover_license` obbligatoria in ogni caso: manca una policy universale repository-grounded;
- revision history: PR #261 e ancora fuori da `main`, quindi non e una dipendenza disponibile;
- freshness temporale: manca una soglia editoriale approvata;
- qualita/autorita delle fonti: richiederebbe policy editoriale o servizi esterni non previsti dalla V1;
- Content Graph coverage: PR #279 e fuori da `main` e non viene usata da questa branch.

## Query impact

Tutti i check sono in-memory tranne `percorso`.

- relazione `contentClusters` non caricata: massimo 1 query;
- relazione gia caricata: 0 query aggiuntive.

Nessuna scrittura, nessuna chiamata API, nessun side effect.

## UI

**Deferred.**

Le superfici Admin article edit/show sono attualmente coinvolte da lavoro parallelo (#260, #261, #273). Questa branch non modifica controller, route, form o Blade admin.

Quando quelle PR saranno stabilizzate, l'integrazione raccomandata e un pannello read-only "Controlli editoriali" nell'editor che renderizza `label`, `status` e `reason`; i warning non devono impedire submit o publish.

## Public scope

Nessuno. Il servizio non e cablato in controller o view pubbliche e non modifica metadati/rendering pubblico.

## Gate

Questa foundation non introduce migration ed e database-portable per costruzione, salvo le relazioni Eloquent gia esistenti.

Prima del merge sono comunque richiesti i normali gate repository:

- focused `ArticleContentHealthServiceTest`;
- full PHP suite;
- Pint;
- `git diff --check`.

In una sessione solo repository-online questi gate runtime non possono essere dichiarati `LOCAL GREEN`.
