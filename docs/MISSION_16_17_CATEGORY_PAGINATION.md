# Missioni 16–17 — paginazione categorie

## Audit

La pagina pubblica `/categoria/{slug}` disponeva già di paginazione Laravel
server-side a 12 articoli e caricava l'autore con eager loading. Il contratto
`Article::published()` esclude draft, review e scheduled futuri; la ricerca
nelle categorie secondarie usa `EXISTS`, quindi non duplica gli articoli.

L'audit ha individuato tre differenze reali rispetto al contratto già adottato
da `/percorsi`:

1. una pagina oltre l'ultima disponibile restituiva `200` con elenco vuoto;
2. il `<head>` non esponeva `rel="prev"` e `rel="next"`;
3. articoli con lo stesso `published_at` non avevano un ultimo tie-breaker
   esplicito, con rischio di salti o duplicazioni tra pagine concorrenti.

Canonical, visibilità pubblica, componente di navigazione accessibile,
responsive images e query count erano già coperti e non richiedevano un
refactor.

## Correzione minima

- conserva `paginate(12)` e l'ordinamento editoriale per `published_at`;
- aggiunge `id DESC` come ultimo tie-breaker;
- restituisce `404` quando una categoria non vuota riceve una pagina oltre
  `lastPage()`;
- emette link `prev/next` page-aware, con pagina 1 normalizzata senza query
  string;
- non modifica la risposta onesta di una categoria realmente vuota.

## Perimetro invariato

Nessuna migration, modifica dati, contenuto editoriale, categoria, immagine,
servizio canonico, Social o configurazione production. Non vengono caricati
tutti gli articoli in memoria e non viene aggiunto JavaScript.
