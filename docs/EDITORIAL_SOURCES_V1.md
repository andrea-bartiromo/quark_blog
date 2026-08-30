# Fonti strutturate (Missioni 26-28)

## Perché esiste

Prima di questo lavoro, Kairus aveva due modi non strutturati per
attribuire una fonte a un articolo:

1. `articles.primary_sources` — un campo di testo libero (max 500
   caratteri) del pannello di verifica editoriale (`admin/verification`),
   pensato per il controllo interno "ho aperto la fonte primaria prima di
   pubblicare?". Letto da `EditorialQualityChecker` e
   `SourceImageAttributionHealthService`, mai mostrato al lettore.
2. Un blocco `Fonti` ricavato dal corpo dell'articolo: tutto ciò che segue
   il primo `---` nel campo `body` viene renderizzato come testo semplice
   (`articles/partials/body.blade.php`), senza link, senza struttura.

Nessuno dei due permette: un link cliccabile e sicuro, un DOI
riconosciuto, una data di consultazione, un tipo di fonte dichiarato, un
ordinamento editoriale esplicito. Il gap era reale, verificato con un
audit del codice esistente prima di scrivere qualunque cosa.

## Cosa introduce questo lavoro

- Tabella `article_sources` (migrazione additiva, FK `cascadeOnDelete` su
  `articles`).
- Modello `App\Models\ArticleSource` con relazione `Article::sources()`
  ordinata per `position`, poi `id` (tie-break deterministico).
- `SourceReferenceNormalizer`: normalizza e valida URL (allowlist
  `https://` in scrittura) e DOI (forma nuda `10.xxxx/yyyy`, riconosce
  `doi:`, `https://doi.org/…`, `http://dx.doi.org/…`).
- `ArticleSourceService`: riconcilia l'elenco completo delle fonti di un
  articolo a partire dal payload dell'editor admin (crea/aggiorna/elimina
  in una transazione).
- Editor admin (`admin/partials/article-sources.blade.php` +
  `article-source-row.blade.php`): righe aggiungibili/rimovibili,
  riordino con pulsanti Su/Giù (nessun drag-and-drop: deve restare
  utilizzabile da tastiera), anteprima live, validazione inline,
  warning (non bloccante) sui duplicati.
- Rendering pubblico (`articles/partials/sources.blade.php`): sezione
  "Fonti" in fondo all'articolo, assente quando non ci sono fonti,
  etichetta di tipo mostrata solo se dichiarata, DOI preferito all'URL
  come destinazione del link.

## Convivenza con il legacy

Questo lavoro **non tocca** `articles.primary_sources` né il blocco
`Fonti` ricavato dal body. Motivi:

- Una migrazione automatica del testo libero di `primary_sources` (una
  stringa qualunque, non strutturata) in righe `article_sources`
  (titolo/URL/DOI separati) non può essere fatta in modo affidabile senza
  intervento umano — un parser euristico produrrebbe dati editoriali
  sbagliati con l'aria di essere corretti, il rischio peggiore per una
  funzionalità che esiste per la fiducia del lettore.
- Rimuovere il rendering del blocco legacy dal body avrebbe fatto
  sparire silenziosamente le fonti dagli articoli già pubblicati che le
  usano in quella forma, finché la redazione non le ricompila a mano
  nel nuovo editor.

L'editor admin avvisa la redazione (banner giallo, non bloccante) quando
un articolo ha ancora fonti in formato legacy, così la migrazione può
avvenire articolo per articolo, a discrezione della redazione.

## Sicurezza

- Allowlist di schema, mai una denylist: solo `https://` è accettato in
  scrittura. Un URL con schema `javascript:`, `data:`, `vbscript:`,
  `blob:`, `file:`, o con caratteri di controllo nascosti nello schema
  (`java\nscript:`), viene rifiutato sia in validazione (400 con errore
  visibile in admin) sia, per difesa in profondità, di nuovo al momento
  della scrittura in `ArticleSourceService::attributesFrom()` — così un
  futuro percorso di scrittura che dimenticasse di validare non può
  comunque persistere un valore pericoloso.
- Il rendering pubblico non fida mai del valore in tabella: chiama di
  nuovo `SourceReferenceNormalizer::isRenderableUrl()` /
  `normalizeDoi()` prima di stampare un `href`. Se un valore imprevisto
  finisse in tabella per qualunque altra via, resterebbe testo, mai un
  link eseguibile.
- `rel="noopener noreferrer"` su ogni link esterno, stesso pattern già
  in uso per `cover_source_url` e `SocialPublication::safeRemoteUrl()`.

## Autorizzazione

Nessun controllo per-articolo oltre al middleware `['auth','editor']`
già applicato all'intero gruppo di rotte `admin.*`: chi può modificare
il corpo di un articolo può già modificarne le fonti. Stesso
ragionamento già documentato per `Admin\ArticleRevisionController`.
L'editor delle fonti non è esposto in Redazione in questa v1: resta
un'estensione futura, non costruita ora senza un requisito esplicito.
