# Workspace Social Admin V1

## Cosa fa

Un workspace amministrativo interno per preparare, revisionare, approvare
e programmare bozze Social per Facebook e LinkedIn, collegate a un
articolo Kairus esistente. Flusso: **bozza → revisionato → approvato →
programmato**.

## Cosa NON fa (esplicito, non un'omissione)

- **Non pubblica nulla**: nessuna chiamata HTTP verso Meta/Facebook/
  LinkedIn, nessun token, nessun SDK, nessun webhook.
- Non tocca `social_publications` (il ledger di delivery/provider
  esistente, invariato — verificato da `SocialPublicationLedgerTest`,
  `SocialProviderContractTest`, `PublishSocialDistributionJobTest`,
  `ManualSocialRetryTest`, tutti verdi senza modifiche).
- Non modifica l'articolo sorgente in alcun modo (titolo, corpo, cover,
  alt, SEO, categoria, stato, Percorsi, Concetti) — verificato da un test
  dedicato (`SocialDraftHttpTest::test_creating_and_editing_a_draft_never_changes_the_source_article`).
- Non invia email, Newsletter o notifiche.

## Schema

Migration dedicata `2026_09_02_120000_create_social_drafts_table.php`,
tabella `social_drafts`, **separata e mai collegata** a
`social_publications`:

```
id, article_id (FK articles, restrict on delete), channel (facebook|linkedin),
status (draft|reviewed|approved|scheduled — published/failed solo
informativi, mai impostabili da questa V1), copy (text, nullable),
destination_url (text, nullable), use_utm (bool, default true),
utm_campaign (string, nullable), scheduled_at (timestamp UTC, nullable),
created_by/reviewed_by/approved_by (FK users, null on delete),
reviewed_at/approved_at (timestamp, nullable), timestamps.
```

Indici: `article_id`; `(status, scheduled_at)`; `(channel, scheduled_at)`.

## Stati e transizioni

```
draft ⇄ reviewed ⇄ approved ⇄ scheduled
```

Concretamente (`App\Services\SocialWorkspace\SocialDraftStateMachine`):
`draft→reviewed`, `reviewed→draft`, `reviewed→approved`,
`approved→reviewed`, `approved→scheduled`, `scheduled→approved`. Ogni
altra transizione — inclusa qualunque verso `published`/`failed` — è
rifiutata a livello di servizio, non solo di UI (vedi
`SocialDraftHttpTest::test_transition_endpoint_cannot_set_published` e
`_failed`, che verificano anche il mass-assignment diretto).

## Autorizzazione

Stesso middleware canonico del resto di `/admin`: `['auth','editor']`
(nessuna Policy dedicata nel progetto, coerente con l'esistente). Un
utente `role=author` viene rediretto alla propria dashboard, mai
un errore silenzioso lato client.

## Timezone

Input redazionale sempre in Europe/Rome, storage sempre UTC
(`App\Services\SocialWorkspace\SocialDraftScheduleTimeResolver`). Un
orario inesistente (passaggio all'ora legale) o ambiguo (passaggio
all'ora solare) viene **rifiutato esplicitamente** con un messaggio
leggibile — mai un'interpretazione silenziosa. Vedi test dedicati sulle
date reali 2026 (29 marzo, 25 ottobre).

## Collisioni

Stesso canale + stesso istante programmato = collisione. **Mai risolta
spostando automaticamente una data**: lo schema non prevede un campo per
registrare una conferma editoriale della collisione, quindi — scelta
conservativa esplicitamente autorizzata — la programmazione viene
bloccata con un messaggio che chiede un orario diverso, mai un warning
aggirabile.

## UTM

`App\Services\SocialWorkspace\SocialDraftUtmService`: preserva query
string e fragment esistenti, non duplica parametri, campagna di default
deterministica (`{canale}-{slug articolo}`, nessuna data — a differenza
del generatore `UtmLinkGenerator` esistente, qui serve stabilità per una
bozza rivista più volte). `use_utm=false` disattiva esplicitamente.

## URL di destinazione

Se non specificato, l'URL canonico pubblico dell'articolo
(`Article::metaCanonicalUrl()`). Se specificato, deve essere http/https e
sullo stesso host dell'applicazione — mai uno schema pericoloso
(`javascript:`, `data:`), mai un host esterno (ogni bozza rappresenta
sempre un articolo Kairus).

## Preview

Server-rendered, interne, nessun iframe/script/SDK esterno. Dichiarano
esplicitamente "Anteprima editoriale interna: la resa finale può
differire dalla piattaforma". Cover assente → fallback testuale (titolo +
URL), mai un placeholder che simuli un'immagine. Cover presente senza
alt → warning operativo visibile, non bloccante ma esplicito.

## Immutabilità dopo approvazione

`copy` e `destination_url` diventano di sola lettura appena la bozza è
`approved`, finché non torna esplicitamente `reviewed`. Una bozza
`scheduled` è interamente bloccata: va prima riportata `approved`
("Annulla programmazione", che azzera anche `scheduled_at`).

## Audit

Riusa `App\Models\ActivityLog::record()` esistente — nessun secondo
sistema di log. Ogni creazione e transizione scrive una riga
(`subject_type='social_draft'`), mostrata come storico leggibile nella
pagina di dettaglio.

## Privacy

Nessun token, nessun payload provider (non esistono qui). Email e dati
personali dell'autore dell'articolo non compaiono mai nelle viste del
workspace — verificato da test dedicati.

## Limiti noti di questa V1

- Nessun collegamento (nemmeno di sola lettura) a `social_publications`:
  una futura fase provider dovrà collegarle esplicitamente.
- Nessuna ricerca full-text avanzata: filtro titolo articolo con `LIKE`.
- Nessuna notifica in-app per una bozza in attesa di revisione/approvazione.
