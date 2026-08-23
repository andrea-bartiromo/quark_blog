# Percorsi Scheduling V1 — specifica definitiva

## Stato corrente osservato

`ContentCluster` usa `is_active` come gate pubblico. `lifecycle_status` (`updating|complete`) descrive la maturità editoriale e non deve essere sovraccaricato come stato di pubblicazione. `ContentClusterController::index()` e `show()` applicano entrambi `scopeActive()`. L'admin scrive direttamente `is_active`; non esiste oggi un timestamp di scheduling.

## Decisione di dominio

V1 richiede un nuovo campo nullable `publish_at` (UTC storage, editing/display Europe/Rome) e una sola policy di visibilità centralizzata sul model/service.

La source of truth proposta è:

- `is_active = false` => mai pubblico, anche con `publish_at` passato;
- `is_active = true` + `publish_at = null` => comportamento legacy, pubblico subito;
- `is_active = true` + `publish_at <= now()` => pubblico;
- `is_active = true` + `publish_at > now()` => programmato e non pubblico.

`lifecycle_status` resta ortogonale: descrive se il Percorso è in aggiornamento o completo, non decide la raggiungibilità.

La policy futura deve essere esposta da un unico `scopePubliclyVisible()` / `isPubliclyVisible()`; controller pubblico, ArticlePathNavigation, sitemap, eventuali recommendation e subscription visibility devono riusarla invece di replicare condizioni.

## Backward compatibility

Tutti i Percorsi legacy attivi con `publish_at = null` restano pubblici come oggi. La migration NON deve valorizzare date, attivare/disattivare Percorsi o eseguire backfill production.

## Admin semantics

L'editor potrà:

1. lasciare `is_active=false` per una bozza non pubblicabile;
2. impostare `is_active=true` + `publish_at` futuro per programmare;
3. modificare `publish_at` prima della pubblicazione per riprogrammare;
4. cancellare `publish_at` mantenendo `is_active=true` per pubblicazione immediata legacy;
5. impostare `is_active=false` per annullare la visibilità/scheduling.

Input e visualizzazione devono usare `Article::EDITORIAL_TIMEZONE` (`Europe/Rome`) o una costante condivisa equivalente; storage UTC.

## Pillar e membri

La visibilità del Percorso non rende pubblici articoli non pubblicati. `show()` continua a usare `Article::published()` per membri e pillar. La Publication Readiness deve avvisare se, al momento previsto di pubblicazione del Percorso, pillar/membri utili non saranno ancora disponibili.

## Cache e navigation

Ogni cache che indicizza Percorsi deve includere/rispettare la nuova policy temporale. `ArticlePathNavigation` deve considerare soltanto Percorsi pubblicamente visibili al momento della richiesta. Non serve un job per "attivare" il Percorso: la visibilità è derivata dal timestamp, come per gli articoli, evitando stato duplicato e race condition.

## SEO/public surfaces

Prima del merge runtime devono essere verificati almeno:

- `/percorsi`;
- `/percorsi/{slug}`;
- breadcrumb/canonical/structured data;
- sitemap;
- ArticlePathNavigation;
- recommendation/continuation che usano Percorsi;
- eventuali form subscription legati allo stato pubblico.

Un Percorso futuro deve produrre 404/assenza dai listing pubblici fino a `publish_at`.

## Migration safety

Schema minimo: `content_clusters.publish_at TIMESTAMP/DATETIME NULL`, indicizzato insieme al gate necessario solo se l'EXPLAIN lo giustifica. Nessun ALTER ulteriore, backfill o data migration.

Essendo schema-changing, prima del merge sono obbligatori MariaDB/MySQL reale: `migrate:fresh`, rollback della migration, forward migration, verifica null/default/index e compatibilità timezone. Questo ambiente non dispone di MariaDB/MySQL reale, quindi la migration non viene creata in questa PR.

## Test matrix runtime obbligatoria

- active legacy + `publish_at=null` => pubblico;
- active + futuro => invisibile;
- active + passato => pubblico;
- inactive + passato => invisibile;
- riprogrammazione futuro→futuro;
- annullamento via `is_active=false`;
- pillar draft/review/scheduled dopo la data del Percorso;
- membro scheduled prima/dopo la data del Percorso;
- index/show/sitemap/path-navigation leakage;
- Europe/Rome ↔ UTC ai cambi DST;
- query/index regression.

## Decisione

`DESIGN COMPLETE — SCHEMA IMPLEMENTATION DEFERRED TO REAL MARIADB GATE`.

Non viene introdotta una migration non certificabile e non viene modificato alcun Percorso reale.