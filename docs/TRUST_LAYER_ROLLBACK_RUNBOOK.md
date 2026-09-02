# Runbook rollback — Trust Layer V1 e formati futuri

Applicabile a: fonti pubbliche, pagine Trust (Metodologia), pagina
autore, revision transparency, e a qualunque pilot futuro (Cosa sappiamo
davvero, Atlante visuale) una volta attivato. Nessuna modifica deploy o
produzione eseguita da questo documento — è una guida per un operatore
umano.

## Principio generale

Ogni superficie di questo cantiere è additiva e presentation-only:
disabilitarla significa sempre **rimuovere/nascondere il rendering**, mai
cancellare dati (`primary_sources`, `article_revisions`,
`verification_*` restano intatti indipendentemente dal rollback).

## Fonti pubbliche (`feat/public-article-sources-v1`)

- **Disabilitare la superficie**: rimuovere la riga
  `<x-article.primary-sources :sources="$primarySources" />` da
  `articolo.blade.php` (o avvolgerla in un feature flag se ne verrà
  introdotto uno in futuro — questa V1 non ne ha).
- **Dati**: nessuna perdita — `Article::primary_sources` non viene mai
  scritto da questa PR, solo letto.
- **Verifica post-rollback**: `ArticleStructuredDataTest` e
  `ArticlePublicPrimarySourcesTest` (quest'ultimo andrebbe rimosso o
  aggiornato se il componente viene rimosso permanentemente).

## Pagine Trust (`feat/trust-policy-pages-v1`)

- **Disabilitare**: rimuovere la route `/metodologia` da `routes/web.php`
  e il link corrispondente da `components/footer.blade.php`. La entry in
  `SeoController::staticSitemapPages()` va rimossa nello stesso commit —
  altrimenti la sitemap continuerebbe a dichiarare un URL 404.
- **Verifica**: ricontrollare `sitemap.xml` non contenga più `/metodologia`
  dopo la rimozione (nessun link noto per restituire 404 pubblicato).

## Pagina autore (`feat/public-author-pages-v1`)

- **Rollback parziale possibile**: la sola eleggibilità
  (`abort_unless($articles->total() > 0, 404)`) può essere rimossa da
  `AuthorController::show()` per tornare al comportamento precedente
  (sconsigliato — reintroduce il gap di sicurezza identificato in B-03),
  oppure l'intera pagina va disabilitata rimuovendo la route `/autore/{user}`.
- **Attenzione**: rimuovere la route rompe il link "Profilo autore" già
  presente in `articles/partials/author-card.blade.php` e in
  `redazione.blade.php` — vanno rimossi/aggiornati nello stesso commit di
  rollback.

## Revision transparency (`feat/public-article-revision-transparency-v1`)

- **Disabilitare**: rimuovere il blocco "Aggiornato il" da
  `articles/partials/hero.blade.php` e la riga `dateModified` da
  `articles/partials/structured-data.blade.php`. Nessun dato scritto da
  questa PR — `article_revisions` non viene mai toccato in scrittura.

## Pilot futuri (Cosa sappiamo davvero, Atlante visuale)

Finché restano prototipi non instradati (stato attuale), non serve alcun
rollback: non sono raggiungibili pubblicamente. Se in futuro un pilot
verrà attivato con una route reale, questo runbook va esteso con la
stessa procedura sopra (rimuovere route, link di navigazione, entry
sitemap se presente) prima che il pilot venga considerato "chiuso".

## Checklist comune a ogni rollback

1. Rimuovere il rendering/route interessata.
2. Rimuovere ogni link di navigazione verso quella superficie (footer,
   card articolo, altre pagine statiche).
3. Rimuovere l'eventuale entry sitemap.
4. Verificare canonical/robots delle pagine adiacenti non referenzino più
   la superficie rimossa.
5. Eseguire smoke test sulle pagine toccate (home, articolo, footer) per
   confermare nessun link rotto residuo.
6. Nessuna cancellazione dati in nessun caso — solo rendering/route.
