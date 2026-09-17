# Percorsi — Metodo scientifico: readiness assessment

Cantiere 79 (programma "100 cantieri Kairus"). Documento di sola
analisi/audit. Nessuna migrazione, nessuna scrittura su
`content_clusters`/`articles`, nessun `ContentCluster` creato (nemmeno in
stato bozza `is_active=false`) — vedi "Perché non viene creata nemmeno una
riga bozza" per il ragionamento. Non introduce codice applicativo — vedi
"Prossimi passi" per cosa cambierebbe se e quando esisterà contenuto reale.

Stesso schema di audit già applicato in
`docs/PERCORSI_FISICA_FONDAMENTALE_READINESS.md`
(`docs/MISSION_71_FISICA_FONDAMENTALE_DRY_RUN_GATE.md`) per un Percorso
diverso: quel precedente ha stabilito che costruire un nuovo Percorso senza
contenuto editoriale reale equivarrebbe a codificare una proposta
editoriale non verificata — esplicitamente escluso dal mandato di questo
programma ("il sistema prepara/verifica/propone, l'editor umano decide").

## Verdetto

**NEEDS CONTENT.**

Nel repository non esiste, in nessuna forma, un pillar o un contenuto
editoriale dedicato al metodo scientifico: né una categoria dedicata in
tassonomia, né un Percorso, né un membro attivo in
`config/content-clusters-initial.php`. Un solo candidato **debole/condizionale**
esiste, individuato dalla revisione di questa PR (Codex) e verificato negli
snapshot SQLite versionati (stessa categoria di evidenza già usata
dall'audit Fisica Fondamentale per i suoi candidati GPS) — vedi sotto.
Come per Fisica Fondamentale, dove `relativita-speciale` e i candidati GPS
offrivano un punto di partenza condizionale ma non bastavano da soli a
sbloccare il Percorso, un solo articolo adiacente non cambia il verdetto:
costruire oggi un Percorso "Metodo scientifico" — bozza o meno —
richiederebbe comunque scegliere/scrivere un pillar reale e decidere se
questo candidato vi appartiene: decisioni editoriali, non tecniche.

## Inventario contenuti reali (evidenza, non ipotesi)

Fonti verificate leggendo direttamente il repository:

- **`config/laboratorio.php`** — tassonomia canonica delle categorie
  editoriali: `intelligenza-artificiale`, `energia`, `salute`, `societa`,
  `spazio`, `fisica`, `ambiente`. **Nessuna categoria "metodo scientifico"
  esiste**, a differenza del caso Fisica (dove `fisica` era già stata
  aggiunta da PR #253 prima ancora che esistesse un articolo pubblicato).
  Qui la decisione redazionale "categoria a sé vs. sotto-tema di un'altra"
  non è stata presa nemmeno a livello di tassonomia.
- **`config/content-clusters-initial.php`** — l'unica mappa di Percorsi
  reale esistente (non generata da Faker): definisce 4 Percorsi già
  progettati (*IA spiegata*, *Spazio*, *Scienza quotidiana*, *Energia e
  batterie*). Nessuno dei 32 slug membri elencati ha un nome o una
  posizione tematica riconducibile al metodo scientifico (ipotesi,
  esperimento, falsificabilità, peer review, bias cognitivi, pensiero
  critico) — verificato per ispezione diretta del file, non per campione.
- **`database/seeders/DatabaseSeeder.php`** — i 6 articoli editoriali
  realistici che semina (categorie intelligenza-artificiale, energia,
  salute, spazio, società) non toccano il tema: nessun titolo o corpo
  menziona metodo/ipotesi/esperimento/falsificabilità/peer
  review/pensiero critico (verificato per ricerca testuale diretta nel
  file).
- **Snapshot SQLite versionati** `storage/backups/database-2026-05-02-*.sqlite`
  (stessa categoria di evidenza già usata dall'audit Fisica Fondamentale
  per i suoi candidati GPS) — Codex, in revisione a questa PR, ha
  individuato l'articolo pubblicato `ia-ricerca-scientifica-cnr-agenti-2025`
  ("IA nella ricerca scientifica: il CNR e i nuovi strumenti che
  accelerano le scoperte", presente identico in tutti e 3 gli snapshot,
  `published_at` 2025-04-15). Il corpo tratta generazione di ipotesi,
  proposta di esperimenti, riproducibilità e verifica dei risultati — ma
  nel contesto della governance dell'IA nella ricerca, non come
  spiegazione autonoma del metodo scientifico. **Candidato debole/condizionale**,
  nella stessa categoria dei candidati GPS di Fisica Fondamentale: non è
  un pillar, non è membro di alcun Percorso (nessuna tabella
  `content_clusters` esiste ancora in quegli snapshot, precedenti alla
  migrazione che l'ha introdotta), e lo slug non compare in
  `config/content-clusters-initial.php` né nel seeder corrente — verifica
  editoriale del testo, dello stato di pubblicazione attuale e della sua
  reale pertinenza necessaria prima di poterlo considerare.
- **Ricerca repository-wide** (`app/`, `resources/`, `database/`, `tests/`,
  `docs/`) per "metodo scientifico"/"scientific method": l'unica
  occorrenza reale è una stringa di fixture in
  `tests/Feature/CategoryCuratorNoteTest.php` ("La nostra bussola per
  parlare di metodo scientifico.") — un valore di esempio per testare il
  campo `curator_note` di una Categoria, non contenuto editoriale, non un
  candidato membro di Percorso.

## Perché non viene creata nemmeno una riga bozza

Il modello dati (`content_clusters`, vedi sotto) è già completamente
generico e riusabile senza alcuna modifica — questo cantiere non ha quindi
un ostacolo tecnico da rimuovere. L'ostacolo è che una riga `ContentCluster`,
anche con `is_active=false`, richiede comunque `name` e `slug` non nulli
(vedi migrazione) e nella pratica un `short_description`/`description`
sensati per essere utile in Redazione — cioè richiede già una decisione
editoriale minima (che titolo dare al Percorso, come descriverlo) che
questo audit non può prendere al posto della redazione. Creare una riga
segnaposto con testo placeholder produrrebbe lo stesso rischio già
documentato per Fisica Fondamentale: un artefatto "apparentemente
autorevole" nell'admin, senza revisione umana a monte.

## Infrastruttura generica già disponibile (nessun lavoro tecnico necessario)

Confermato per lettura diretta — il modello Percorso in questo repository
non è mai stato accoppiato a un singolo Percorso (es. Turing, che infatti
usa un modello del tutto separato, `SpecialPage`):

- **`content_clusters`** (migrazione `2026_08_13_080610_create_content_clusters_table.php`
  + colonne aggiunte successivamente per `pillar_article_id`,
  `lifecycle_status`, `curator_note`, `publish_at`, vedi
  `docs/PERCORSI_SCHEDULING_V1_SPEC.md`) — schema generico, già riusato da
  4 Percorsi reali.
- **`App\Models\ContentCluster`** — nessun riferimento hardcoded a un
  Percorso specifico.
- **`app/Services/ContentClusters/`** — tooling operativo generico già
  esistente e riusabile per qualunque Percorso, incluso uno futuro sul
  metodo scientifico: `PercorsoCoverageAuditService`,
  `PercorsoPublicationReadinessService`, `PercorsoReorderSimulationService`,
  `PercorsoSubscriberNotificationReadinessService`,
  `PercorsiActivationCalendarService`, `ContentClusterLifecycleReconciler`.
- **`ContentClusterController::show()`** — genera già, per qualunque
  cluster attivo, JSON-LD (`CollectionPage` + `BreadcrumbList` + `ItemList`),
  canonical URL, fallback SEO automatico da `description` se
  `seo_title`/`seo_description` non sono valorizzati. Nessun lavoro SEO
  aggiuntivo necessario quando esisterà contenuto reale.
- **`php artisan content-clusters:backfill-initial`** — già dry-run per
  default (richiede `--apply` esplicito), consuma
  `config/content-clusters-initial.php`. Non è stato eseguito con una voce
  ipotetica per il metodo scientifico in questo audit: aggiungerne una
  richiederebbe comunque inventare gli slug articolo, cioè la stessa
  decisione editoriale esclusa sopra.

## Classificazione dei gap

- **ESSENTIAL** (blocca qualunque Percorso): un pillar article pubblicato
  che introduca il metodo scientifico in modo autonomo — oggi non esiste,
  e a differenza di Fisica Fondamentale non esiste nemmeno una categoria
  dedicata. `ia-ricerca-scientifica-cnr-agenti-2025` (vedi inventario) non
  colma questo gap: tratta la governance dell'IA nella ricerca, non
  introduce il metodo scientifico di per sé.
- **USEFUL**: 2-4 articoli di supporto sullo stesso tema (es. un caso
  concreto di ipotesi/verifica, un errore metodologico comune, una guida
  al pensiero critico) — `ia-ricerca-scientifica-cnr-agenti-2025` è
  l'unico candidato condizionale oggi noto in questo repository (vedi
  inventario), previa verifica editoriale del suo stato reale in
  produzione e della sua pertinenza.
- **OPTIONAL**: contenuti di estensione (storia del metodo scientifico,
  filosofia della scienza) una volta che il nucleo esiste.

## Decisione richiesta alla redazione

Prima di qualunque lavoro tecnico su questo Percorso, una persona deve: (1)
decidere se "metodo scientifico" diventa una categoria editoriale a sé in
`config/laboratorio.php` o resta un tema trasversale ad altre categorie
esistenti; (2) scrivere e pubblicare almeno un pillar article; (3) solo a
quel punto, aggiungere una nuova voce a `content-clusters-initial.php` con
gli slug reali. Senza questi fatti la readiness non può salire a `READY
WHEN SCHEDULED CONTENT PUBLISHES` o `READY NOW`.

## Prossimi passi (editoriali, non tecnici)

1. Decisione redazionale sulla categoria (vedi sopra).
2. Un pillar article pubblicato prima di qualunque lavoro tecnico sul
   Percorso.
3. Solo a quel punto: nuova voce in `content-clusters-initial.php` e
   riesecuzione di questo audit per un verdetto aggiornato.

## Impatto sui cantieri dipendenti

I cantieri 80 ("Gate attivazione Percorso Metodo scientifico"), 81
("Continuità contestuale articolo-concetto-Percorso") e 83 ("Audit
accessibilità/UX articolo-concetto-Percorso") dipendono tutti
dall'esistenza di questo Percorso e restano quindi `blocked` in
`docs/KAIRUS_100_CANTIERI_TRACKING.md` fino a quando questo verdetto non
cambia in seguito a una decisione editoriale reale.
