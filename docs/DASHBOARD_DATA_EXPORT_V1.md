# Dashboard Data Export V1

## Posizione nella Roadmap tecnica Kairus v13

Missione prioritaria immediatamente successiva a **September Measurement Closeout** e precedente agli sviluppi avanzati di Radar, Social automatico e nuove superfici pubbliche. L'implementazione vive su un branch indipendente derivato da `main`: il branch Measurement Closeout era già ampio e conteneva una migration, quindi l'integrazione non era più ragionevolmente contenuta.

## Audit iniziale

| Sezione | Servizio sorgente | Dati disponibili | Dati sensibili | Esportabile | Formato |
|---|---|---|---|---|---|
| Command Center | `EditorialOperationsDashboardService` | salute, programmati, alert, opportunità, Percorsi | titoli interni | sì, allowlist | JSON |
| Content Health | `ArticleContentHealthService`, via dashboard | finding e motivazioni operative | corpo e note non necessari | sì, allowlist | CSV/JSON |
| Search Opportunities | Radar + Search Console | query, URL, evidenza | query potenzialmente personali | no, finché manca normalizzazione certificata | manifest `unavailable` |
| Editorial Radar | `EditorialRadarProviderGraphService` | opportunità aggregate | motivazioni editoriali | solo totale nel summary | JSON |
| Seconda lettura | `ContinuationAnalyticsService` | impression, second read, rate | eventi/sessioni individuali | sì, solo aggregato sitewide | CSV/JSON |
| Continuation | eventi V1 | impression/second read | possibile granularità articolo | insufficiente per tutti i tipi richiesti | manifest `unavailable` |
| Percorsi | servizi readiness/health | riepiloghi operativi | membership editoriale | solo conteggio nel summary | JSON |
| Newsletter | tabella `newsletter` | iscrizioni e conferme per source | email e token | sì, solo aggregati | CSV/JSON |
| Social | generatore UTM stateless + delivery parziali | nessun calendario completo affidabile | payload/token provider | no | manifest `unavailable` |
| Calendario editoriale | controller e servizi progetto | pianificazione operativa | note e associazioni interne | non incluso in V1 | manifest/limitazioni |
| Export esistente | `NewsletterController::export()` | email confermate | email | non riusabile: viola il contratto aggregato di questa missione | nessuno |

Il repository usa il gruppo `auth` + `editor`; l'export applica `auth` e una autorizzazione più stretta nel Form Request (`role=admin`), così un ruolo autenticato non autorizzato riceve 403. I download esistenti usano normali response Laravel. Non esiste un job asincrono o un framework export comune da riusare.

## Guida amministrativa

L'azione **Esporta dati per analisi** appare nella dashboard Operazioni editoriali soltanto agli amministratori. Selezionare intervallo, sezioni e formato:

- **ZIP**: manifest, dataset richiesti e data-quality;
- **CSV**: una singola sezione, UTF-8 con BOM compatibile Excel;
- **JSON**: documento tecnico completo, versionato e machine-readable.

Le date sono selezionate e rappresentate in `Europe/Rome`; il manifest conserva timestamp ISO 8601. Il limite è 366 giorni. I segmenti con meno di cinque osservazioni sono `insufficient_data`: lo zero del campione resta zero, la metrica non calcolabile resta `null`.

Il pacchetto non include email, IP, cookie, session ID, token, credenziali, payload provider, stack trace, note private o corpi editoriali. Dopo averlo allegato allo strumento di analisi, eliminarlo dal computer locale quando non serve più. Verificare `schema_version` e, per ZIP, confrontare ogni file con `checksums_sha256` del manifest.

## Contratto tecnico

- Schema: `1.0.0`, evoluzione additive-compatible entro la major.
- Dataset: id, versione, stato, schema/header, righe, limitazioni.
- Stati: `available`, `insufficient_data`, `unavailable`, `not_applicable`.
- CSV: `fputcsv`, UTF-8 BOM, virgole/newline quotati, celle che iniziano con `= + - @ TAB CR` prefissate con apostrofo.
- Privacy: allowlist esplicite; nessun `Model::toArray()`, `SELECT *` o serializzazione Eloquent.
- Limiti: 5.000 righe per dataset, 366 giorni, 6 richieste/minuto.
- ZIP: directory `storage/app/tmp/dashboard-exports`, nome casuale, permesso `0600`, `deleteFileAfterSend(true)`, cleanup su eccezione e pruning opportunistico dei residui oltre 60 minuti. Nessun file permanente o deploy backup.
- Audit: log strutturato minimale con admin id, intervallo, sezioni, formato, esito e conteggio; mai contenuti o path.

Per aggiungere un dataset, implementare una trasformazione con allowlist in `DashboardDataExportService`, dichiararne schema/versione/limitazioni, aggiungerlo alla validazione e coprire privacy, campione, CSV/JSON e manifest. Non dipendere dal markup Blade.

## Limiti noti e gate production

Search query, continuation dettagliata e Social Calendar restano esplicitamente indisponibili. Nessun dato production è necessario per certificare codice e fixture. Un eventuale controllo futuro dei conteggi reali deve essere svolto con runbook in sola lettura e classificato `BLOCKED_PRODUCTION_GATE`; non è parte di questa PR.
