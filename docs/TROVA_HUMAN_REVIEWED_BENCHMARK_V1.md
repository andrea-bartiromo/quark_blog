# TROVA — benchmark human-reviewed V1

## Scopo e confine

La fixture `tests/Fixtures/trova/benchmark-v1.json` congela un piccolo set
revisionabile di intenzioni: termini esatti, sinonimi/alias, domanda naturale,
errore comune e zero-results. Non cambia ranking, non crea Concept e non usa
query production. Gli identificatori attesi sono `tipo:slug`, quindi restano
leggibili e indipendenti dagli ID del database.

La fixture è un contratto editoriale, non la prova che oggi tutte le query
abbiano già il risultato atteso. Prima di promuovere una modifica del motore,
un test d'integrazione deve popolare fixture equivalenti, trasformare i risultati
in `tipo:slug` e passarli a `TrovaBenchmarkEvaluator`.

## Metriche

- **precision@3**: media, per caso, dei risultati attesi presenti nei primi tre;
- **no-result rate**: quota di casi con lista effettiva vuota, separando nel
  report gli zero attesi dagli zero inattesi;
- **duplicate rate**: duplicati nei primi tre divisi per `casi × 3`.

Un valore mancante non vale zero editoriale e uno zero atteso non vale errore.
I risultati vanno sempre pubblicati con versione fixture, SHA, data, database
effimero e conteggio casi. Le query zero-result production restano soggette alla
soglia privacy del servizio diagnostico esistente.

## Procedura ripetibile

1. revisione umana della fixture, senza inserire query personali;
2. `php artisan test tests/Unit/Search/TrovaBenchmarkFixtureTest.php`;
3. esecuzione del benchmark su database effimero con contenuti dichiarati;
4. report di metriche e casi falliti, senza correzione automatica del ranking;
5. eventuale proposta separata, con confronto prima/dopo e controllo pubblico.

La fixture contiene intenzioni sintetiche rappresentative. L'aggancio a un
campione production aggregato è deliberatamente rinviato a una sessione
read-only con soglia privacy e approvazione editoriale.
