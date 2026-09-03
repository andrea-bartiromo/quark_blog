# Home + Percorsi Visual Adoption — Contratto di invarianti

Cantiere D, Prompt 04. Ogni missione di questo cantiere è vincolata da
quanto segue: se un'implementazione lo violerebbe, l'implementazione si
ferma e il conflitto viene registrato, mai aggirato.

## URL

- `route('home')`, `route('percorsi.index')`, `route('percorsi.show', $slug)`,
  `route('articolo', $slug)`, `route('categoria', $slug)`,
  `route('newsletter.subscribe')`, `route('percorsi.subscribe', $slug)`,
  `route('turing')` — tutti invariati, nessuna nuova rotta.
- Nessun parametro di query nuovo o rimosso.

## Query e dati

- `HomeController::index()` e il controller dell'indice/dettaglio Percorsi
  restano invariati: stesse query, stesso `limit`, stesso `orderBy`.
- Nessun dato mostrato che non provenga già da una variabile passata dal
  controller alla vista.
- Nessuna nuova query N+1 introdotta dai nuovi componenti (tutti
  presentation-only, verificato in Kairus Editorial Foundations V1).

## Ordine narrativo Percorsi

- L'ordine dei Percorsi restituiti da `$homePaths` (home) e dai `$clusters`
  (indice) resta quello deciso dal controller/model (`ordered()`) — questo
  cantiere riordina solo la **posizione del blocco Percorsi sulla pagina**
  home (Prompt 13), mai l'ordine interno dei Percorsi stessi.
- L'ordine delle tappe (`$articles`) nel dettaglio Percorso resta quello
  del controller — il numero mostrato da `x-kairus.path-step` è un
  complemento visivo, mai una fonte di riordino.

## Responsive images

- Ogni immagine pubblica continua a passare da `<x-responsive-image>` (o
  dal fallback SVG deterministico già esistente) con lo stesso
  `diskName`/`src`, `alt`, `sizes`. `width`/`height`/`srcset` restano
  risolti dal componente esistente, mai duplicati o ricalcolati.
- `loading`/`fetchpriority` restano quelli già impostati per ciascuna
  immagine (`eager`+`fetchpriority="high"` sul hero e sul cover Percorso,
  `lazy` altrove) — mai aggiunti o rimossi da `x-kairus.image-frame`
  (che non li tocca per contratto).

## SEO

- `title`, `canonical`, `description`, Open Graph, Twitter Card,
  `robots`, JSON-LD (`home.partials.structured-data`, i due `@section('head')`
  di indice/dettaglio Percorsi) restano bit-per-bit invariati.
- Nessun H1 aggiuntivo o rimosso; nessun livello di heading alterato oltre
  a quanto già certificato valido nell'audit semantico.

## Cookie / consenso

- Nessun banner cookie, popup Newsletter o meccanismo di consenso è
  toccato: questo cantiere non entra in `resources/views/components/`
  (dove vivono quei componenti) se non nei confini già elencati come fuori
  perimetro.

## Newsletter e tracking

- Il form Newsletter in home (`newsletter-band.blade.php`) mantiene
  `action`, `method`, CSRF, nome dei campi (`email`, `source`) e comportamento
  di invio identici — solo il markup di cornice (titolo, spaziatura, testo di
  contesto) può cambiare.
- Il form "Avvisami quando continua" nel dettaglio Percorso
  (`subscribe-form.blade.php`) non viene toccato in alcun modo (vedi file
  map: fuori perimetro per compatibilità con il proprio toggle JS).
- `data-path-analytics-view`, `data-path-slug`, `data-cluster-id` e lo
  script incluso in `@push('scripts')` di `content-clusters/show.blade.php`
  restano presenti e invariati.
- Google Analytics (gtag), `AnalyticsExclusionService`, il carosello
  categorie (`data-category-carousel` e il relativo script in
  `home.blade.php`) restano invariati negli attributi da cui dipendono.

## Continuità visiva

- Nessun CSS legacy pubblico viene rimosso o modificato in questo
  cantiere — solo `public/css/editorial-system.css` riceve nuove regole,
  sempre `--kairus-*`/`.kairus-*`.
