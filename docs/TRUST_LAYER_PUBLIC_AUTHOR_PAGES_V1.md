# Pagine autore pubbliche (V1)

## Cosa cambia

`AuthorController::show()` e `resources/views/autore.blade.php` esistevano
già (nome, foto, bio, twitter, articoli pubblicati, `Person` JSON-LD). Il
gap identificato nell'audit read-only (B-01/B-03): **nessun filtro di
eleggibilità pubblica** — qualunque `User` esistente in `users`, incluso un
account admin senza articoli, era raggiungibile via `/autore/{id}` e
confermava la propria esistenza (nome/foto/bio se presenti).

## Regola di eleggibilità applicata

`AuthorController::show()` ora richiede `Article::where('user_id',
$user->id)->published()->exists()` prima di renderizzare la pagina —
altrimenti `abort(404)`, indistinguibile da uno slug/id inesistente.

Deliberatamente **non** basata sul campo `role` (es. "editor/admin sempre
visibili anche senza articoli"): userlo avrebbe significato inventare una
regola editoriale ("un ruolo interno implica pagina pubblica") non
verificabile dai dati esistenti. L'unico segnale disponibile senza
introdurre un campo nuovo è "ha pubblicato qualcosa" — coerente col resto
del Trust Layer V1 (mai un'affermazione non dimostrabile dai dati).

## Cosa NON cambia

- Nessuna migration, nessun campo nuovo su `users`.
- Structured data `Person`: `description` solo se `bio` esiste, nessun
  `award`/`credentials`/`affiliation` inventato — verificato da
  `AuthorStructuredDataTest` esistente (incluso un test dedicato
  all'hex-encoding dei terminatori di script nel JSON-LD).
- Nessuna modifica a profilo Admin/Redazione.

## Sintesi con la PR parallela #509 (2026-09-02)

Durante la review è emersa un'altra PR aperta sullo stesso problema
(`feat/author-trust-layer-v1`, #509), con soluzione diversa e file in
conflitto. Confronto e decisione: la eleggibilità 404 di questa PR resta
— #509 usava solo `noindex,follow` senza mai bloccare l'accesso diretto,
lasciando raggiungibile (200) qualunque utente con una semplice bio
compilata anche senza articoli, cioè non chiudeva davvero il gap di
esposizione trovato dall'audit. Le sue altre aggiunte erano però valide
e sono state innestate qui sopra la eleggibilità reale:

- **Etichetta di ruolo veritiera**: "Collaboratore Kairus" per `role
  === 'author'`, "Redazione Kairus" per editor/admin — non più
  "Redattore Kairus" fisso per chiunque (anche nel `<title>`).
- **LinkedIn**: renderizzato solo se HTTP(S) valido (stessa validazione
  già in uso per `cover_source_url`), incluso in `sameAs`.
- **`jobTitle`** nello schema `Person`, coerente con l'etichetta di
  ruolo mostrata in pagina.
- **Email non pubblicata per nessun ruolo**: prima era visibile per
  `role === 'editor'`; ora rimossa del tutto (scelta più conservativa,
  nessun indirizzo email compare mai sulla pagina pubblica).

Test aggiunti: `tests/Feature/AuthorPageRoleAndLinkedinTest.php` (6 test:
etichetta collaboratore/redazione, LinkedIn valido renderizzato e incluso
in `sameAs`, schema non sicuro mai renderizzato, email mai mostrata
nemmeno per un editor, `jobTitle`+`sameAs` corretti nel JSON-LD).

## Rischio noto (non introdotto qui, solo esposto)

`resources/views/redazione.blade.php` (pagina pubblica `/la-redazione`,
fuori scope — non modificata) linka staticamente
`User::where('role','editor')->first() ?? 1`. Se quell'editor avesse zero
articoli pubblicati, il link "Tutti gli articoli" ora romperebbe (404)
invece di mostrare la pagina con lo stato vuoto preesistente. Un test
dedicato (`test_la_redazione_editor_link_resolves_when_the_editor_has_published_articles`)
verifica il percorso atteso in produzione (il fondatore ha già articoli
pubblicati). Non ho modificato `redazione.blade.php`: la query fragile
con fallback hardcoded `?? 1` è un problema preesistente e indipendente,
fuori dal perimetro di questa missione.

## Collegamento autore ↔ articolo

`articles/partials/author-card.blade.php` collega già incondizionatamente
alla pagina autore. Non serve un ramo di fallback "autore non pubblico":
l'autore di un articolo pubblicato è per costruzione eleggibile (quello
stesso articolo è già "almeno un articolo pubblicato"). Questa invariante
è verificata esplicitamente da un test dedicato invece di essere lasciata
implicita.

## Test

`tests/Feature/PublicAuthorPageEligibilityTest.php`:
- Utente inesistente → 404 (comportamento di route-model-binding
  preesistente, confermato).
- Utente con zero articoli pubblicati → 404 (nuovo).
- Utente con solo articoli draft/scheduled → 404 (nuovo — un articolo non
  ancora pubblico non deve rendere pubblica la pagina autore).
- Utente con almeno un articolo pubblicato → 200, nome visibile.
- Email mai mostrata per un autore non editor, anche se eleggibile.
- Account admin senza pubblicazioni → 404 (copre esplicitamente il caso
  di rischio identificato nell'audit).
- Invariante: il link "Profilo autore" su un articolo pubblicato non
  restituisce mai 404.
