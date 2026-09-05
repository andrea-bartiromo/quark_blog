# Content Intelligence — audit operativo read-only

Baseline: `84a5803222f940e71cad5e435c52b92171733991`.

## Content Graph e alias (Prompt 38–39)

`ContentGraphOperationalSummaryService` è il punto di convergenza read-only e
delega ai servizi canonici. Copre health Concept, domande rispondibili,
integrità domande approvate, alias, relazioni e articoli pubblicati orfani, con
limite esplicito di 50 righe e link allo strumento admin. Non crea pagine.

`ConceptAliasIntegrityService` segnala solo fatti conservativi:
`EMPTY_AFTER_NORMALIZATION`, `DUPLICATE_EXACT`,
`DUPLICATE_CASE_INSENSITIVE`, `MATCHES_OTHER_CONCEPT_NAME`. Non presume
equivalenza Unicode o semantica. Lo stato production certificato indicava
Content Graph 100% e nessuna incoerenza actionable; il repository da solo non
può riconfermare conteggi live.

Per ogni finding alias la cura proposta è preview/dry-run:

1. esportare mapping `alias_id, vecchio, concept_id, candidato` in memoria;
2. verificare TROVA, Radar e articoli collegati;
3. approvazione umana per ogni `vecchio → nuovo`;
4. applicazione in PR/sessione separata con transazione e audit log;
5. rollback reintroducendo alias e collegamenti fotografati.

Nessun merge distruttivo o mapping viene proposto senza righe production.

## TROVA e zero-results (Prompt 40–41)

Il benchmark V1 è isolato nel branch `test/trova-human-reviewed-benchmark`:
fixture versionata, expected `tipo:slug`, precision@3, no-result rate e duplicate
rate. Non modifica ranking. Gli expected sono intenzioni human-reviewed, non una
dichiarazione di risultato production.

Gli zero-results sono aggregati per query normalizzata con `hit_count`; nessun
IP, sessione, user-agent o utente viene persistito. Limite attuale: il servizio
non classifica bot/rumore e il log fail-open include la query normalizzata in
caso di errore. L'audit production deve applicare una soglia minima (proposta:
almeno 3 hit e 2 giorni distinti, se la dimensione temporale è disponibile),
separare rumore e query genuine e non creare automaticamente Concept/contenuti.

## Radar e Command Center (Prompt 42–44)

Radar concatena provider explainable (health, Search Console, seconda lettura,
attribuzione), deduplica per chiave e ordina HIGH/MEDIUM/LOW. Nessuno score
opaco e nessuna mutazione. La calibrazione richiede un campione production:
classi `utile`, `duplicata`, `prematura`, `non azionabile`, con tasso di utilità
e motivazione. Senza decisioni registrate il tasso è non disponibile.

ADR proposto per gli esiti, senza migration:

- stati: `planned`, `ignored`, `resolved`, `duplicate`;
- chiave opportunità immutabile, attore autorizzato, timestamp e motivazione;
- optimistic concurrency/idempotenza sulla coppia chiave+versione;
- audit log append-only, nessuna cancellazione del finding originario;
- UI esclusivamente dentro Operazioni editoriali.

Il repository non dispone ancora del ledger decisioni; quindi adozione,
tempo-risoluzione e conversione in azione non sono misurabili. Baseline onesta:
conteggi alert disponibili; azioni, tempi e duplicazioni eliminate non
disponibili. Vietato introdurre tracking personale.

## Calendario (Prompt 45)

La vista supporta mese, settimana, lista e prossime quattro settimane; usa
`Article::EDITORIAL_TIMEZONE` (`Europe/Rome`), navigazione mese senza overflow,
ordinamento deterministico e chip limitati. I test coprono conversione serale,
filtri, cambio mese e assenza di endpoint publish/reschedule. Il comando
`project:editorial-audit` è read-only e segnala date/stati discordanti,
ambiguità, righe non interpretabili e articoli fuori piano.

Residui da verificare runtime: transizioni DST del 25 ottobre 2026, collisioni
reali, scheduled scaduti e articoli senza data. Nessuna data è stata modificata.
