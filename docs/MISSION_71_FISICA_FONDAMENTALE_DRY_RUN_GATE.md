# Missione 71 — Fisica fondamentale dry-run builder

Data audit: 2026-08-27  
Base verificata: `main` a `e84249bf5dace3694404464301bb0fa870f576e6`

## Esito

**BLOCKED_WITH_EVIDENCE / REQUIRES_PRODUCTION_FACT.**

La missione è esplicitamente condizionata al fatto che la Missione 70 mostri
materiale sufficiente. La ricertificazione corrente conclude invece
`NEEDS CONTENT / REQUIRES PRODUCTION FACT`:

- nessun pillar evidence-backed è disponibile su `main`;
- GPS, ottica, laser ed entanglement non hanno candidati editoriali reali
  versionati;
- gli slug candidati rimanenti appartengono già a *Spazio* o *Scienza
  quotidiana* e il repository non contiene il testo/stato del catalogo di
  produzione necessario a valutarli;
- fixture e test non sono contenuto editoriale e non possono essere usati
  per superare il gate.

## Perché non viene creato un builder specifico

Un builder dovrebbe emettere candidati, motivazioni, posizioni, conflitti,
pillar ed esclusioni di articoli non pubblicabili. Senza il catalogo reale
codificherebbe una proposta editoriale non verificata e produrrebbe un output
apparentemente autorevole su dati incompleti. Questo violerebbe sia il gate
della missione sia l'obbligo di human review.

Il comando generale `content-clusters:backfill-initial` esiste già ed è
dry-run per default, ma consuma una mappa esplicita approvata. Non esiste una
mappa approvata per `fisica-fondamentale`; aggiungerla ora equivarrebbe a
prendere la decisione editoriale che questo audit deve lasciare alle persone.
Nessuna esecuzione `--apply` è stata effettuata.

## Fatti necessari per sbloccare

1. Snapshot read-only del catalogo reale con almeno slug, titolo, stato,
   `published_at`, categoria e membership Percorsi dei candidati.
2. Scelta umana del pillar dopo lettura del contenuto reale.
3. Decisione esplicita sul riuso di membri già primari in *Spazio* e
   *Scienza quotidiana*.
4. Ordine editoriale approvato e lista degli articoli mancanti/esclusi.

Solo dopo questi fatti una missione successiva potrà introdurre un servizio
**esclusivamente read-only**, senza `--apply`, senza AI esterna e con test che
dimostrino zero mutazioni prima/dopo l'esecuzione.
