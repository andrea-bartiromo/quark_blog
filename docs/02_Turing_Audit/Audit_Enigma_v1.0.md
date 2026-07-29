# Audit tecnico ed editoriale — `/turing/enigma`

| Campo | Valore |
|---|---|
| Versione | v1.0 |
| Data | Ricostruito il 2026-07-29 |
| Stato | RICOSTRUITO DA CRONOLOGIA DI SESSIONE — parziale |
| Autore | Sessione Claude Code |
| Dipendenze | Nessuna |
| Documenti correlati | Masterplan_Speciale_Turing_v1.0.md, Audit_AI_Tecnico_v1.0.md, Audit_Computation_v1.0.md, Audit_Intelligence_v1.0.md, Audit_Legacy_v1.0.md |

> **Nota sulla natura del documento e asimmetria nota.** A differenza degli audit di Computation/Intelligence/Legacy/AI, l'audit originale di `/turing/enigma` **non ha mai ricevuto la valutazione numerica a 6 assi** (voto tecnico/architetturale/Design System/profondità editoriale/divulgazione/collegamento con Turing) applicata alle altre quattro pagine. Questa asimmetria è stata rilevata e segnalata esplicitamente nel Masterplan originale e viene preservata qui, non risolta silenziosamente: la pagina Enigma resta, a oggi, l'unica delle cinque senza un voto comparabile.

## Struttura e file coinvolti

- View: `resources/views/turing/enigma.blade.php` — **624 righe**, bespoke, con un blocco `<style>` inline di circa 360 righe.
- **Zero componenti del Design System**: nessun `<x-turing.article.*>`, nessun breadcrumb, nessun `<img>` reale (immagini gestite come sfondi CSS).
- Pattern strutturale interamente duplicato indipendentemente in `ai.blade.php` (hero scuro, griglia di card 01/02/03, callout "pannello terminale" fittizio, CTA finale) — la duplicazione tra le due pagine è stata segnalata come una delle evidenze più rilevanti dell'intero Speciale.

## Problemi reali riscontrati

- Assenza totale di adozione del Design System condiviso (`<x-turing.article.*>`), a differenza di computation/intelligence/legacy che lo adottano al 100%.
- ~360 righe di CSS inline non riutilizzabili, duplicate concettualmente in `ai.blade.php`.
- Nessun breadcrumb, a differenza delle altre pagine di dettaglio.

## Elementi grezzi disponibili e non utilizzati

- 8 fotografie storiche Enigma su disco, mai utilizzate nella pagina: `01_macchina_enigma_completa.jpg` … `08_disco_cifrante.jpg`.

## Proposta di struttura e piano PR

Migrazione al Design System condiviso seguendo lo stesso modello architetturale già applicato con successo a computation/intelligence/legacy (Decision Log #010/#011/#012 del Project Book): riuso di `<x-turing.article.*>` (breadcrumb, hero, body, callout, cta) e `<x-special.section-header>`, eliminazione del CSS inline, integrazione delle 8 fotografie storiche non utilizzate.

## Stato

Analisi tecnica **read-only**: nessuna modifica al codice è stata effettuata durante l'audit originale, come esplicitamente richiesto.
