# SEO Metadata Quality Audit

Missione 15 — audit read-only, nessuna generazione/sovrascrittura di metadata.

## Riuso delle policy esistenti

Le verifiche di lunghezza e indexability non vengono duplicate: il servizio riusa `EditorialQualityChecker`, che già formalizza titolo SEO max 70, meta description 50–200 e warning su `noindex` per articoli pubblicati.

## Diagnosi aggiunte

- uso dei fallback `seo_title -> title` e `seo_description -> excerpt/body`;
- duplicate effective title;
- duplicate effective description;
- canonical non HTTP(S) o con query/fragment;
- presenza effettiva dei fallback OG/Twitter.

## Limiti espliciti

Il repository non definisce oggi una soglia minima specifica per la lunghezza del *SEO title*: l'audit non ne inventa una e restituisce `NOT_DEFINED_IN_REPOSITORY` nella policy note.

La scheduled leakage è una proprietà delle superfici renderizzate, non del record metadata isolato: il servizio la marca `REQUIRES_ROUTE_RENDERING_REGRESSION_TESTS` invece di fingere di averla certificata.

## Safety

Read-only; nessun write su Article, nessuna route/controller/view, nessuna migration, nessun copy generato automaticamente.

Prima del merge: focused tests, full PHP suite, Pint e `git diff --check`. `LOCAL GREEN` non è dichiarato dalla sessione online.
