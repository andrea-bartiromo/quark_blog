# Riesame gate pilot Trust Layer — 2026-09 (Prompt 121-140)

Riesame dei due design/prototipi pilot già esistenti (`docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md`,
`docs/TRUST_LAYER_ATLANTE_VISUALE_PILOT.md`), ciascuno con il proprio gate
GO/NO-GO tenuto esplicitamente separato — nessuna decisione qui attiva
l'uno in base all'altro, e nessuna delle due diventa un GO automatico.

## "Cosa sappiamo davvero" — dipendenza tecnica risolta, decisione invariata

Il documento originale (missione B-45) elencava tre condizioni per il
NO-GO: owner editoriale, contenuto sorgente reale approvato, e il merge
di `feat/public-article-sources-v1`. Quest'ultima è stata risolta da
allora (PR #532, riconciliata su `main`) — verificato qui aggiornando il
prototipo per usare il componente reale `<x-article.primary-sources>`
invece di una riproduzione statica duplicata, con un nuovo test che
renderizza davvero la view (non solo verifica il routing, come il test
esistente).

**La decisione resta NO-GO**: le due condizioni rimanenti (owner
editoriale, contenuto sorgente approvato) sono entrambe decisioni umane/
editoriali — nessun cantiere tecnico può soddisfarle. Risolvere la
dipendenza tecnica elimina un blocco, non il gate stesso.

## "Atlante visuale" — nessun cambiamento

Le condizioni mancanti (owner, articolo sorgente per la prima tavola,
metrica di successo dedicata) sono interamente editoriali/di prodotto,
non tecniche — nessuna di esse dipende da un merge o da codice.
Verificato che il prototipo e il suo test di non-routing restano validi
su `main` attuale (nessuna regressione). **NO-GO invariato.**

## Cosa questo riesame non ha fatto

- Non ha assegnato un owner editoriale a nessuno dei due pilot — non è
  una decisione che un audit tecnico possa prendere.
- Non ha scritto o approvato alcun contenuto editoriale reale per
  nessuno dei due formati — i prototipi restano interamente segnaposto.
- Non ha aperto alcuna route pubblica, migration, o modificato
  contenuto editoriale esistente.
- Non ha reso l'uno GO in base all'altro, né introdotto alcuna logica
  che li colleghi.

## Esito

Due gate riesaminati, entrambi confermati **NO-GO** per un pilot manuale
reale. Un blocco tecnico chiuso (Fonti primarie, "Cosa sappiamo
davvero") con evidenza diretta (test che renderizza la view reale). Le
condizioni editoriali rimanenti per entrambi i pilot restano a carico di
una decisione umana esplicita, non di questa sessione.
