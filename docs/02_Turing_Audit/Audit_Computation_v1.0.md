# Audit tecnico ed editoriale — `/turing/computation`

| Campo | Valore |
|---|---|
| Versione | v1.0 |
| Data | Ricostruito il 2026-07-29 |
| Stato | RICOSTRUITO DA CRONOLOGIA DI SESSIONE — parziale |
| Autore | Sessione Claude Code |
| Dipendenze | Nessuna |
| Documenti correlati | Masterplan_Speciale_Turing_v1.0.md, Audit_Intelligence_v1.0.md, Audit_Legacy_v1.0.md |

## Struttura e Design System

- View: `resources/views/turing/computation.blade.php` — 118-120 righe, **zero CSS inline**, adozione **completa** di `<x-turing.article.*>` (breadcrumb, hero, body, callout, cta) e `<x-special.section-header variant="panel" align="left">`. Introdotta in Decision #011 del Project Book.
- Zero binding CMS, hero image singola condivisa.
- Test: `tests/Feature/TuringComputationPageTest.php`, 14 test.

## Contrasto (difetti condivisi del Design System, vedi Audit Hub RC)

Stessi tre difetti WCAG AA misurati identicamente su intelligence e legacy: breadcrumb link 3.26:1, etichetta pagina corrente 2.36:1, kicker callout 4.09:1 (dopo correzione della metodologia di compositing alpha, che in una prima passata aveva prodotto un falso 5.47:1 assumendo erroneamente uno sfondo bianco opaco).

## Collegamenti

CTA finale "Dal calcolo all'intelligenza" aggiornata in Decision #012 per puntare a `route('turing.intelligence')` (in precedenza puntava provvisoriamente a `/turing/ai`, prima che la pagina Intelligence esistesse).

## Word count e profondità editoriale

≈565 parole (narrativo), superiore ad AI ma comunque oggetto di valutazione comparativa nel Masterplan rispetto alle altre pagine di dettaglio.

## Piano di miglioramento proposto

Correzione dei tre difetti di contrasto condivisi a livello di `turing.css` (unico intervento, si propaga a tutte le pagine); nessuna migrazione architetturale necessaria (pagina già pienamente allineata al Design System).
