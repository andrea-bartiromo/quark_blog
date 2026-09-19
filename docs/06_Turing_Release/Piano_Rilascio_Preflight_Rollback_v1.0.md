# Piano di rilascio — Speciale Turing (preflight + rollback)

| Campo | Valore |
|---|---|
| Versione | v1.0 |
| Data | 2026-09-19 |
| Stato | Cantiere 70 del programma "100 cantieri Kairus" (dipende dal Cantiere 69) |
| Autore | Sessione Claude Code |
| Dipendenze | Cantieri 57, 61, 62, 63, 64, 66, 67, 68, 69 |
| Documenti correlati | `docs/DEPLOYMENT.md`, `docs/ROLLBACK_RUNBOOK.md`, `docs/02_Turing_Audit/`, `docs/06_Turing_Release/Checklist_Release_Candidate_v1.0.md`, `docs/00_Governance/Masterplan_Speciale_Turing_v1.0.md` |

Questo documento è **solo operativo**: non introduce alcun comando o script
nuovo, non esegue alcun rilascio, non decide alcun GO/NO-GO. Come già per
`docs/ROLLBACK_RUNBOOK.md` (Cantiere 75): un ipotetico comando che
orchestrasse automaticamente il rilascio o il rollback dello Speciale
(es. `artisan turing:release`) rientrerebbe nella categoria di nuova
capacità potenzialmente distruttiva che questo programma tratta come
decisione da sottoporre esplicitamente prima di essere costruita — non
qualcosa da aggiungere opportunisticamente dentro un cantiere di
documentazione. La decisione di eseguire il rilascio resta umana ed
esplicita, fuori da questo documento.

## Cosa significa "rilascio" per lo Speciale Turing

L'intero Speciale è già completo in produzione — codice, viste, asset,
test — dietro un singolo interruttore: `config('turing.chapters_public')`
(`config/turing.php`, variabile d'ambiente `TURING_CHAPTERS_PUBLIC`,
default `false`). Quando è `false`, `/turing` mostra la landing statica
"In arrivo" e ogni rotta capitolo (`/turing/enigma`, `/turing/ai`,
`/turing/legacy`, `/turing/computation`, `/turing/intelligence`)
reindirizza con 302 verso `/turing` (Cantiere 57, coperto da
`TuringReleaseGateTest`, 11 test). Il rilascio pubblico dello Speciale
è quindi, tecnicamente, **il flip di una singola variabile d'ambiente**,
non un deploy di nuovo codice: nessuna migrazione è necessaria per questo
flip specifico (le tabelle `turing_chapter_sources`/`turing_chapter_views`
esistono già in produzione indipendentemente dal flag, create dai
Cantieri 61/68).

Effetti automatici del flip, già verificati da test esistenti — nessuna
azione manuale aggiuntiva richiesta:
- Sitemap: i capitoli non pubblicati sono esclusi, l'hub resta indicizzabile
  in entrambi gli stati (`TuringSitemapTest`).
- SEO: `app/Http/Controllers/SeoController.php` e
  `app/Services/PublicPages/PublicPageInventory.php` leggono lo stesso
  flag, nessuna configurazione duplicata.
- Metriche di navigazione: `recordView()` viene chiamato solo quando il
  flag è vero (Cantiere 68) — nessun evento fittizio raccolto prima del
  rilascio.

## Preflight — da verificare PRIMA di impostare `TURING_CHAPTERS_PUBLIC=true`

1. **Checklist beta interna** (`GET /admin/turing/checklist-beta`, Cantiere
   69): tutte le condizioni determinabili automaticamente devono risultare
   `Soddisfatta`. Le condizioni non determinabili automaticamente (owner
   editoriale) restano una decisione umana esplicita, non bloccano questo
   preflight ma vanno registrate a parte.
2. **Report di completezza** (`GET /admin/turing/report-completezza`,
   Cantiere 67): rivedere fonti registrate, copertura della mappa
   concettuale (fotografia statica del 29 luglio 2026, non ricalcolata) e
   stato di pubblicazione per ciascun capitolo.
3. **Gap noto e accettato esplicitamente, non risolto qui**:
   `hero-enigma.png`/`cutaway-enigma.png` sono 287×289px, sotto lo standard
   1200px dichiarato in `docs/04_Turing_Visual/Registro_Asset_Turing_v1.0.md`
   — tripwire documentato in
   `TuringEditorialAssetsTest::test_known_gap_enigma_hero_and_anatomy_fallbacks_are_undersized_for_their_cover_usage()`
   (Cantiere 64). Un umano deve decidere se sostituire l'asset prima del
   rilascio o accettare il gap consapevolmente.
4. **Debito di accessibilità/performance NON riverificato da questo
   cantiere**: `docs/02_Turing_Audit/` e
   `docs/06_Turing_Release/Checklist_Release_Candidate_v1.0.md` sono
   fotografie statiche del 29 luglio 2026 (axe-core/Lighthouse manuali,
   mai ripetuti automaticamente). Prima del rilascio pubblico, un umano
   deve o ri-eseguire quelle misurazioni o accettare esplicitamente il
   debito noto (3 difetti di contrasto condivisi + 1 specifico su AI).
5. **Suite di test verde**: `php artisan test --filter Turing` deve
   risultare interamente verde (a parte lo skip noto del punto 3) prima di
   qualunque deploy.
6. **Owner editoriale assegnato** per la revisione/il rilascio — nessun
   meccanismo di assegnazione esiste ancora nel sistema (Cantiere 69):
   decisione e comunicazione restano manuali, fuori da questo programma.

## Procedura di rilascio

Nessun passo qui è nuovo: la sequenza riusa esclusivamente il processo di
deploy già esistente e documentato in `docs/DEPLOYMENT.md`.

1. Impostare `TURING_CHAPTERS_PUBLIC=true` nel file `.env` di produzione.
2. Eseguire il deploy standard (`deploy.sh`, `docs/DEPLOYMENT.md`): il
   processo di deploy esegue già `config:cache` come parte della sua
   sequenza standard (`docs/DEPLOYMENT.md`, nota su PHP-FPM/OPcache) — non
   serve alcun passo separato per far leggere la nuova variabile
   d'ambiente all'applicazione.
3. Verifica post-rilascio: `/turing` mostra l'hub reale (non più la
   landing "In arrivo"), ciascuna delle 5 rotte capitolo risponde `200`
   invece di reindirizzare, il file `sitemap.xml` include i capitoli
   pubblicati.

## Rollback

Il rollback ha esattamente la stessa forma del rilascio, nella direzione
opposta — nessuna procedura speciale, nessuno script nuovo:

1. Impostare `TURING_CHAPTERS_PUBLIC=false` nel file `.env` di produzione.
2. Ridistribuire (stesso `deploy.sh`).
3. Verifica: `/turing` torna a mostrare la landing "In arrivo", ogni rotta
   capitolo torna a reindirizzare con 302 (`TuringReleaseGateTest`).

Nessun dato va ripristinato o eliminato per completare il rollback:
- Le righe di `turing_chapter_views` raccolte durante la finestra
  pubblica restano conteggi aggregati innocui (nessun identificativo di
  visitatore, Cantiere 68) — possono restare in tabella senza alcun
  effetto sul comportamento con il flag di nuovo `false` (`recordView()`
  smette semplicemente di essere chiamato).
- Nessuna migrazione va eseguita né in un verso né nell'altro: le tabelle
  Turing esistono indipendentemente dal flag.

Per un rollback che coinvolga anche una migrazione di schema o la
Libreria Media (scenario non previsto da questo piano, che riguarda solo
il flip del flag), fare riferimento alla procedura generale in
`docs/ROLLBACK_RUNBOOK.md`.

## Monitoraggio post-rilascio

Il pannello `admin.turing` (Cantiere 68) mostra i conteggi aggregati reali
di navigazione per hub e capitoli. Lo stato resta onestamente
`insufficient_data` per i primi 7 giorni dal primo evento realmente
registrato (mai dalla data di deploy) — nessun dato viene dichiarato
disponibile prima che lo sia davvero.
