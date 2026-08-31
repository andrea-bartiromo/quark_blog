# Anteprima narrativa delle card Percorsi

La pagina `/percorsi` riusa `ContentClusterPublicSequence` anche per le prime tre tappe mostrate nelle card. Il controller non seleziona autonomamente articoli “pubblicati”: carica in una sola query tutte le membership dei soli sei Percorsi della pagina corrente e il resolver produce in una seconda query l’insieme degli ID conformi ad `Article::published()`. Ogni prefisso passa quindi da `resolveLoaded()` e conserva lo stop al primo gap.

## Budget query

Per il blocco Percorsi del controller, prima dell’anteprima erano necessarie tre query costanti: count della paginazione, pagina dei Percorsi con conteggio correlato, pillar pubblici. L’anteprima aggiunge due query costanti e indipendenti dal numero di card: membership della pagina e corpus degli ID pubblici. Il budget passa quindi da **3 a 5 query**, senza N+1 e senza leggere membership delle altre pagine.

Non esiste cache persistente. Scheduled, draft, review e articoli pubblicati oltre un gap non entrano nel markup dell’anteprima.
