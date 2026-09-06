# Trasparenza revisioni pubbliche (V1)

## Cosa fa

Mostra "Aggiornato il [data]" sulla pagina articolo pubblica e pubblica
`dateModified` nel JSON-LD `NewsArticle`, ma **solo** quando esiste un
segnale editoriale affidabile — mai una data inventata, mai `updated_at`
tecnico (che resta inaffidabile per le ragioni già documentate in
`articles/partials/structured-data.blade.php`: toccato da
`increment('views')` e dal salvataggio del flusso di verifica).

Legge soltanto `article_revisions`, scritta esclusivamente da
`ArticleRevisionService` (fuori scope, mai modificato qui — vedi
`docs/article-revision-history.md`). Nessuna migration, nessun nuovo
campo, nessun cambiamento a come le revisioni vengono create.

## Perché "ultima revisione" da sola non basta

`ArticleRevisionService::SNAPSHOT_FIELDS` include `status` e
`published_at`, non solo il contenuto editoriale: una revisione scatta
anche su una transizione di solo stato (es. una repubblicazione senza
modifiche). Usare ciecamente `MAX(article_revisions.created_at)`
mostrerebbe "Aggiornato il" anche quando nulla di editorialmente
rilevante è cambiato — un falso segnale di trasparenza è peggio di
nessun segnale.

## Regola applicata (`ArticleRevisionTransparencyService`)

1. Nessuna revisione con `created_at > published_at` → nessun segnale,
   si mostra solo la data di pubblicazione originale.
2. Se esistono revisioni successive alla pubblicazione, si confronta la
   più vecchia di queste con lo stato **attuale** dell'articolo su
   `title`/`excerpt`/`body`/`category`. Se sono identiche (il caso della
   repubblicazione senza modifiche), nessun segnale.
3. Se differiscono, si mostra la data della revisione **più recente** tra
   quelle successive alla pubblicazione — la miglior approssimazione
   disponibile del momento dell'ultima modifica editoriale reale, senza
   introdurre un campo dedicato.

## Fallback e casi limite

- Articolo mai rivisto dopo la pubblicazione → solo data di pubblicazione
  (nessun "Aggiornato il").
- Articolo mai pubblicato (`published_at` nullo) → nessun calcolo, nulla
  da mostrare.
- Revisione datata prima della pubblicazione attuale (es. bozze
  precedenti) → esclusa a priori dal filtro `created_at > published_at`.
- Date incoerenti (es. `published_at` spostato in avanti oltre vecchie
  revisioni) → si autocorregge: quelle revisioni restano fuori dal
  filtro, nessuna data contraddittoria può emergere.

## Nota di correzione manuale — BLOCKED_BY_MISSING_EDITORIAL_FIELD

Nessun campo editoriale esistente è sicuro per una "nota di correzione"
mostrabile al pubblico:

- `Article::verification_notes` esiste ma è testo interno del flusso di
  verifica (chi ha verificato, quando, su quale fonte — vedi migration
  `2026_05_02_062119_add_verification_fields_to_articles_table.php`), mai
  pensato per la lettura pubblica. Riusarlo per questo scopo sarebbe una
  decisione editoriale (e di sicurezza: potrebbe contenere note interne
  non destinate al pubblico), non tecnica.
- `article_revisions` non ha alcun campo `note`/`reason`.

Per questa V1, **nessun campo viene creato, nessun workaround introdotto**
— coerente con l'istruzione esplicita della missione. Una nota di
correzione pubblica resta bloccata finché non viene deciso ed eventualmente
aggiunto un campo editoriale dedicato (fuori scope di questo cantiere: è
un cambiamento di schema, quindi richiederebbe una migration e il
coinvolgimento del flusso Admin/Redazione, entrambi vietati qui).

## Accessibilità e localizzazione

- La data usa lo stesso pattern `<time datetime="...">` già in uso per
  `published_at`, con testo leggibile in italiano
  (`isoFormat('D MMMM YYYY')`), mai un timestamp tecnico in pagina.
- Convertita esplicitamente in `Article::EDITORIAL_TIMEZONE`
  (`Europe/Rome`) prima della formattazione — coerente con l'invariante
  redazionale già definita sul modello `Article` per le altre date
  pubblicate.
- `dateModified` nel JSON-LD resta in ISO 8601 con offset esplicito (dato
  macchina, non testo per il lettore) — stesso trattamento già riservato
  a `datePublished`.

## Test

- `tests/Unit/ArticleRevisionTransparencyServiceTest.php`: nessuna
  revisione, revisione pre-pubblicazione ignorata, revisione di solo
  status non conta, modifica di contenuto reale rilevata, timestamp più
  recente tra quelli qualificati.
- `tests/Feature/ArticlePublicRevisionTransparencyTest.php`: rendering
  end-to-end su `/articolo/{slug}` — fallback, presenza/assenza
  `dateModified`, nessun timestamp UTC grezzo visibile in pagina.
