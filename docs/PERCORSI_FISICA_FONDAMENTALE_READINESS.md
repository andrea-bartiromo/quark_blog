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
