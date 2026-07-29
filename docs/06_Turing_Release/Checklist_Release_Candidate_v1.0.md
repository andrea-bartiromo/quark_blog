# Checklist Release Candidate — Speciale Turing

| Campo | Valore |
|---|---|
| Versione | v1.0 |
| Data | 2026-07-29 |
| Stato | RICOSTRUITO DA CRONOLOGIA DI SESSIONE — aggrega evidenze già raccolte |
| Autore | Sessione Claude Code |
| Dipendenze | I 6 audit in `docs/02_Turing_Audit/` |
| Documenti correlati | Masterplan_Speciale_Turing_v1.0.md |

> Aggrega, senza rieseguire nuove misurazioni, i risultati di accessibilità/performance/test già raccolti nei 6 audit. Sostituisce la necessità di documenti "Report Accessibilità / Report Performance / Report Test" separati, per evitare duplicazione di contenuto già presente negli audit.

## Accessibilità (WCAG AA)

| Difetto | Pagine coinvolte | Misura | Stato |
|---|---|---|---|
| Breadcrumb link | hub, computation, intelligence, legacy | 3.26:1 | Aperto |
| Etichetta pagina corrente | hub, computation, intelligence, legacy | 2.36:1 | Aperto |
| Kicker callout terminale | hub, computation, intelligence, legacy | 4.09:1 | Aperto |
| `.ai-light-text` | ai | 1.42:1 | Aperto — più grave dello Speciale |

## Performance (Lighthouse)

Punteggi coerenti su computation/intelligence/legacy: **Performance 86-87, Best Practices 92, SEO 96, Accessibility 100** (Best Practices/SEO/Accessibility calcolati da Lighthouse stesso, che non rileva i 4 difetti di contrasto sopra — confermati invece via axe-core e compositing manuale). Inflazione di Speed Index/FCP tracciata a un artefatto ambientale del sandbox (font Google bloccati), non un difetto reale (TTFB reale 40-50ms).

## Test automatici

| Pagina | File di test | N. test | Lacune note |
|---|---|---|---|
| Computation | `TuringComputationPageTest.php` | 14 | — |
| Intelligence | `TuringIntelligencePageTest.php` | 13 | — |
| Legacy | `TuringLegacyPageTest.php` | 9 | Nessun test sui link verso computation/intelligence |

## Link interni

3 link teaser in `legacy.blade.php` (righe 36-64): 1 corretto, 2 errati (vedi Audit_Legacy_v1.0.md).

## Esito complessivo

Nessun bug bloccante per il rilascio. Debito noto e ordinato per priorità nel Masterplan. SEO Turing-specifico non ancora auditato in modo dedicato (solo il punteggio Lighthouse generico sopra riportato).
