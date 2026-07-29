# Audit Release Candidate — `/turing` (pagina hub)

| Campo | Valore |
|---|---|
| Titolo | Audit Release Candidate — pagina hub `/turing` |
| Versione | v1.0 |
| Data | Ricostruito il 2026-07-29 (audit originale eseguito in sessione precedente) |
| Stato | RICOSTRUITO DA CRONOLOGIA DI SESSIONE |
| Autore | Sessione Claude Code — Documentation Architect |
| Dipendenze | Nessuna (primo audit della serie) |
| Documenti correlati | Masterplan_Speciale_Turing_v1.0.md, Audit_Enigma_v1.0.md, Audit_AI_Tecnico_v1.0.md, Audit_Computation_v1.0.md, Audit_Intelligence_v1.0.md, Audit_Legacy_v1.0.md |

> **Nota sulla natura del documento.** Questo file è una ricostruzione fedele, a partire dalla cronologia della conversazione, di un audit che in origine è stato consegnato solo come testo in chat e non era mai stato salvato su file. Riporta le conclusioni concrete effettivamente raggiunte (evidenze, classi CSS, componenti, valori misurati) così come registrate nella sessione. Non è una trascrizione verbatim del report originale: per il testo integrale letterale sarebbe necessario riestrarlo dalla trascrizione grezza della sessione (disponibile ma non ancora processata).

## Metodo originale

Revisione release-candidate della pagina hub `/turing`, condotta su 4 viewport (desktop 1920, desktop 1366, tablet, mobile 375/430), con controlli su layout/responsive/overflow, immagini, contrasto colore, componente Timeline, modali, pulsanti, link interni, console browser, network, accessibilità ed equivalente Lighthouse. Verifica eseguita con Playwright/CDP, non per ispezione visiva soggettiva.

## BUG CRITICI

Nessun bug critico bloccante è stato riscontrato che impedisca il rilascio della pagina hub in sé (la card-spacing della Timeline, unico difetto bloccante individuato, era già stato corretto e mergeato come prerequisito di questo audit — vedi PR #95, `fix(turing): restore spacing between timeline cards`).

## BUG MINORI

- Le stesse tre criticità di contrasto WCAG AA rilevate poi in modo identico su `computation`/`intelligence`/`legacy` (vedi sezione "Contrasto" più sotto) sono presenti anche nei componenti condivisi renderizzati dalla hub stessa, essendo difetti a livello di Design System (`turing.css`), non specifici di singola pagina.

## Contrasto (evidenza tecnica riusata in tutti gli audit successivi)

Misurato via CDP (`CSS.getMatchedStylesForNode`) e compositing manuale alpha-corretto dei background `rgba()`, poi confermato via Lighthouse/axe-core indipendente:

| Elemento | Selettore | Contrasto misurato | Soglia AA | Esito |
|---|---|---|---|---|
| Link breadcrumb | `.turing-article-breadcrumb a` | 3.26:1 | 4.5:1 | FALLISCE |
| Etichetta pagina corrente breadcrumb | `[aria-current="page"]` | 2.36:1 | 4.5:1 | FALLISCE |
| Kicker del callout "terminale" | `.turing-terminal-card span` | 4.09:1 | 4.5:1 | FALLISCE (marginale) |

Causa radice: colori pensati per sfondi chiari applicati senza correzione in contesti di fatto scuri (sfondo `.turing-page` `#0F172A`).

## ESITO

**APPROVATO PER IL RILASCIO** per la pagina hub in sé, con i tre difetti di contrasto sopra riportati registrati come debito tecnico del Design System condiviso, da correggere una sola volta a livello di `turing.css` (si propaga automaticamente a tutte le pagine che riusano i componenti `<x-turing.article.*>`).
