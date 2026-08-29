# Percorsi — Fisica Fondamentale: readiness assessment

Documento di sola analisi/design. Nessuna migrazione, nessuna scrittura su
`content_clusters`/`articles`, nessun deploy. Non introduce codice — vedi
"Prossimi passi" per cosa cambierebbe se e quando esisterà contenuto reale.

> **Aggiornamento (rebase pre-merge):** la PR #253, mergiata dopo la stesura
> originale di questo audit, ha aggiunto `fisica` a
> `config('laboratorio.categories')` — quindi `fisica` è ora una categoria
> editoriale reale e selezionabile in Redazione. Questo **non** cambia il
> verdetto: la categoria esiste, ma non esiste ancora nessun articolo di
> fisica pubblicato realmente (solo fixture sintetiche di test locali). Le
> sezioni sotto sono state corrette di conseguenza; il resto dell'audit
> resta valido così com'era.

## Verdetto

**NEEDS CONTENT.**

La categoria `fisica` esiste ormai nella tassonomia (vedi aggiornamento
sopra), ma non esiste, in questo repository, né un Percorso di fisica né
articoli di fisica pubblicati al di fuori di dati di test sintetici creati
durante lo sviluppo (vedi sotto). Costruire un Percorso "Fisica
Fondamentale" oggi richiederebbe inventare articoli — cosa esplicitamente
esclusa da questa missione.

## Missione 70 — ricertificazione current-main (2026-08-27)

**Stato: NEEDS CONTENT / REQUIRES PRODUCTION FACT. Human review
obbligatoria.** L'audit corrente conferma che `main` non contiene un dump
del catalogo editoriale reale. La mappa versionata prova l'esistenza di
intenzioni editoriali e slug, non lo stato, la completezza o la qualità
degli articoli corrispondenti in produzione. Nessun Percorso è stato creato
e nessuna relazione è stata modificata.

### Copertura dei temi richiesti

| Tema | Evidenza reale su `main` | Esito per il Percorso |
|---|---|---|
| Relatività | slug `relativita-speciale`, membro non primario di *Spazio* | candidato condizionale; contenuto e stato da verificare nel DB reale |
| GPS | gli snapshot SQLite versionati `storage/backups/database-2026-05-02-*.sqlite` contengono gli slug pubblicati `lugre-primo-esperimento-italiano-luna-gps-2025` e `galileo-gps-europeo-italia-navigazione-satellitare-2025` | candidati condizionali: gli snapshot sono evidence versionata, ma contenuto, attualità e stato production richiedono revisione umana |
| Orologi atomici | slug `orologi-atomici`, membro primario di *Scienza quotidiana* | candidato condizionale; forte ponte applicativo, con rischio duplicazione |
| Tempo | `guardare-lontano-nello-spazio-guardare-indietro-nel-tempo` e `come-i-telescopi-vedono-il-passato`, entrambi in *Spazio* | candidati di contesto, non nucleo automaticamente trasferibile |
| Onde | `microonde` e `wi-fi` in *Scienza quotidiana* offrono un possibile ingresso alle onde elettromagnetiche | candidati deboli/condizionali; taglio editoriale da verificare |
| Ottica | nessuna evidenza editoriale versionata dedicata | gap essenziale per una progressione completa |
| Entanglement | nessuna evidenza editoriale versionata | gap di fisica moderna |
| Laser | nessuna evidenza editoriale versionata | gap applicativo |

Le occorrenze in `tests/Feature/InternalLinkingQualityTest.php` (relatività,
GPS, orologi atomici) sono fixture sintetiche create per provare il motore
di linking: non sono articoli candidati e non aumentano la readiness.

### Pillar candidate

**Nessun pillar evidence-backed disponibile su `main`.**
`relativita-speciale` è il candidato più vicino a un contenuto fondamentale,
ma è troppo specifico per essere dichiarato automaticamente introduzione
generale alla fisica e risulta già assegnato a *Spazio*. Può diventare pillar
solo dopo revisione umana del testo reale, del suo stato pubblico e del suo
ruolo rispetto al Percorso esistente.

### Candidate members e ordine proposto

Ordine esclusivamente condizionale, da applicare solo dopo verifica nel DB
reale e revisione editoriale:

1. **Nuovo/vero overview pillar di fisica fondamentale** — oggi mancante.
2. `microonde` e/o `wi-fi` — fenomeni osservabili per introdurre le onde,
   solo se il testo spiega davvero la fisica e non soltanto la tecnologia.
3. `orologi-atomici` — misura del tempo e ponte verso le applicazioni.
4. `relativita-speciale` — struttura concettuale prima delle applicazioni
   relativistiche.
5. Uno dei due candidati GPS presenti negli snapshot versionati — solo dopo
   verifica editoriale e confronto con il catalogo production — come applicazione
   concreta di relatività e orologi atomici.
6. Ottica e laser — entrambi mancanti — prima di passare alle frontiere.
7. Entanglement/fisica quantistica — mancante, adatto a una tappa avanzata.

Gli articoli sul passato osservato dai telescopi possono essere rimandi
laterali o tappe avanzate, ma non sostituiscono un'introduzione autonoma al
tempo fisico.

### Gaps e rischi di duplicazione

- **Bloccante:** nessun pillar verificato e nessuna prova versionata dello
  stato pubblico dei candidati nel catalogo reale.
- **Contenuto:** GPS ha due candidati negli snapshot versionati ma non ancora
  certificati rispetto al catalogo production; ottica, laser ed entanglement non hanno candidati
  editoriali reali su `main`; la copertura delle onde è soltanto inferita
  dagli slug e va letta prima di essere accettata.
- **Sovrapposizione con *Spazio*:** `relativita-speciale` e i due contenuti
  sul tempo cosmologico sono già membri di quel Percorso. Duplicarli può
  essere legittimo, ma richiede una decisione su primary membership,
  narrazione e CTA; non vanno spostati automaticamente.
- **Sovrapposizione con *Scienza quotidiana*:** `microonde`, `wi-fi` e
  `orologi-atomici` sono già tappe primarie. Riutilizzarli in blocco
  indebolirebbe l'identità di entrambi i Percorsi.
- **Coerenza pedagogica:** una sequenza costruita solo riusando contenuti
  esistenti salterebbe da tecnologie quotidiane a relatività senza coprire
  meccanica, onde/ottica in modo sistematico e fisica quantistica.

### Decisione richiesta alla redazione

Prima di qualunque builder o backfill, una persona deve verificare nel CMS
reale titolo, slug, stato, qualità e sovrapposizioni dei candidati; scegliere
un pillar; decidere quali articoli possano appartenere a più Percorsi; e
approvare l'ordine. Senza questi fatti la readiness non può salire a
`READY WHEN SCHEDULED CONTENT PUBLISHES` o `READY NOW`.

## Inventario contenuti reali (evidenza, non ipotesi)

Fonti realmente presenti nel repository, verificate leggendo il codice:

- **`config/content-clusters-initial.php`** — l'unica mappa di Percorsi reale
  esistente (non generata da Faker), documentata come "explicit mapping
  source" in `docs/CONTENT_CLUSTERS_PHASE_2A.md`. Definisce 4 Percorsi già
  progettati: *IA spiegata*, *Spazio*, *Scienza quotidiana*, *Energia e
  batterie*. Nessuno dei quattro è un Percorso di fisica dedicato. Due slug
  fisica-adiacenti compaiono però come membri **non primari** di *altri*
  cluster: `relativita-speciale` (dentro *Spazio*, posizione 40) e
  `lintelligenza-artificiale-fisica` (dentro *IA spiegata*, posizione 60).
- **`database/seeders/DatabaseSeeder.php`** — 6 articoli editoriali
  realistici (non lorem ipsum), categorie `intelligenza-artificiale`,
  `energia`, `salute`, `spazio`, `societa`. Nessuno di fisica.
- **`config/laboratorio.php`** — tassonomia canonica delle categorie:
  `intelligenza-artificiale`, `energia`, `salute`, `societa`, `spazio`,
  `fisica`, `ambiente`. **`fisica` è una categoria editoriale reale** da
  PR #253 (mergiata dopo la stesura originale di questa sezione) — ma
  nessun articolo reale è stato ancora pubblicato in questa categoria.
- **Verifica diretta con il tooling esistente**: `php artisan
  content-clusters:backfill-initial` (dry-run, nessuna scrittura) eseguito
  contro un database locale fresco e seedato con `DatabaseSeeder` riporta
  `MISSING ARTICLE` per **tutti** gli slug della mappa versionata, incluso
  `relativita-speciale` — prova diretta che il catalogo articoli
  disponibile in locale/test non coincide con quello a cui la mappa fa
  riferimento (verosimilmente il catalogo di produzione). Questo repository
  non contiene quindi un dump/fixture del catalogo reale da cui inventariare
  contenuti di fisica esistenti.
- **Nessun documento di pianificazione precedente**: nessun file
  `docs/PERCORSI_FISICA*.md` o equivalente esisteva prima di questo.

Due articoli con `category='fisica'` esistono nel database SQLite di
sviluppo locale ("Onde gravitazionali osservate di nuovo",
"Nuove particelle al CERN") — ma sono dati di test sintetici creati durante
la missione precedente (Article Calendar V1, verifica browser), non
contenuto editoriale reale, e vengono deliberatamente esclusi da questo
inventario per lo stesso motivo per cui non si inventano articoli: non è
contenuto che un lettore di Kairus vedrà mai.

## Struttura pedagogica candidata (aree tematiche, non titoli)

Senza inventare articoli specifici, le aree tematiche che una fisica
fondamentale editorialmente coerente coprirebbe tipicamente, in ordine
pedagogico crescente, sono: fenomeni osservabili quotidiani → meccanica
classica di base → relatività (la voce `relativita-speciale` già presente
nel catalogo suggerisce che la redazione ha già in mente questo tema) →
fisica moderna/applicazioni (fisica delle particelle, gravità quantistica
se e quando pubblicata). Questa è una mappa di *ruoli* editoriali, non un
piano di pubblicazione: la redazione decide quali articoli scrivere.

## Classificazione dei gap

- **ESSENTIAL** (blocca qualunque Percorso): un pillar article di fisica
  pubblicato che introduca il tema in modo autonomo — oggi non esiste. La
  categoria `fisica` esiste già nella tassonomia (PR #253), quindi la
  decisione redazionale "categoria dedicata vs. sotto-tema di `spazio`" è
  di fatto già presa: resta solo da scrivere e pubblicare il contenuto.
- **USEFUL** (rafforzerebbe il Percorso ma non lo blocca): 2-3 articoli di
  supporto sullo stesso tema. Lo slug `relativita-speciale` già presente
  nel catalogo di produzione (mappa versionata) è un candidato — ma
  attualmente è membro non primario di *Spazio*, non di un Percorso di
  fisica proprio; spostarlo/duplicarlo va deciso editorialmente, non
  automaticamente da questo audit.
- **OPTIONAL**: contenuti di estensione (fisica delle particelle,
  frontiere della ricerca) una volta che il nucleo esiste.

## Simulazione Growth S2 ("Continua da qui")

Nessuna simulazione con dati reali è possibile senza articoli di fisica
pubblicati. Il comportamento è comunque deterministico e già verificato
end-to-end (Mission 2, PR #268): se in futuro esistesse un Percorso attivo
con un "next" per un dato articolo, `ArticleContinuationService` lo
userebbe come unica prosecuzione (priorità 1, sempre vincente sul
fallback di categoria) — quindi attivare un Percorso di fisica, quando
pronto, sostituirebbe automaticamente qualunque fallback di categoria
attualmente mostrato sugli articoli di fisica esistenti, senza bisogno di
codice aggiuntivo.

## Proposta SEO / structured data — design, nessuna implementazione necessaria

`ContentClusterController::show()` (righe 26-73) genera già, per *qualunque*
cluster attivo, JSON-LD completo (`CollectionPage` + `BreadcrumbList` +
`ItemList` con tutti gli articoli membri), canonical URL via
`route('percorsi.show', ...)`, e usa `seo_title`/`seo_description` se
valorizzati altrimenti un fallback automatico da `description`. **Nessun
lavoro SEO aggiuntivo è necessario**: attivare un futuro Percorso di fisica
erediterebbe automaticamente questa infrastruttura. L'unico lavoro
editoriale sarebbe compilare `seo_title`/`seo_description` con testo
specifico del tema.

## Backfill — anteprima dry-run, nessuna scrittura

Il comando `php artisan content-clusters:backfill-initial` è già
dry-run di default (richiede `--apply` esplicito per scrivere, mai eseguito
in questo audit). Eseguito contro l'attuale `config/content-clusters-initial.php`
(che non contiene un Percorso di fisica), riporta correttamente `MISSING
ARTICLE` per tutti gli slug mappati in locale — comportamento atteso e
corretto, verificato di persona in questo audit, non assunto. Quando la
redazione deciderà titolo/slug degli articoli di fisica, il percorso di
attivazione sarà: aggiungere una nuova voce a
`content-clusters-initial.php` con gli slug reali → dry-run per
conferma → `--apply` in locale/test → PR separata. Nessuna di queste azioni
è stata eseguita qui.

## Prossimi passi (editoriali, non tecnici)

1. ~~Decisione redazionale: la fisica è una categoria a sé o resta
   distribuita (es. dentro `spazio`)?~~ Già decisa: `fisica` è categoria a
   sé da PR #253.
2. Un pillar article pubblicato prima di qualunque lavoro tecnico sul
   Percorso.
3. Solo a quel punto: nuova voce in `content-clusters-initial.php` e
   riesecuzione di questo audit per un verdetto aggiornato (READY WHEN
   SCHEDULED CONTENT PUBLISHES o READY NOW).
