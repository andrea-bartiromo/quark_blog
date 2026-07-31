# Document Register — Kairus / Speciale Turing

| Campo | Valore |
|---|---|
| Versione | v1.0 |
| Data | 2026-07-29 |
| Stato | ATTIVO — registro vivo, aggiornato ad ogni nuovo documento creato o modificato |
| Autore | Sessione Claude Code — Documentation Architect |

Regola di versionamento: mai `_definitivo`/`_finale`. Sempre `_v1.0`, `_v1.1`, `_v2.0`. Le versioni precedenti non vengono mai sovrascritte in-place.

## Governance

| Nome documento | Versione | Stato | Data | Percorso | Documento padre | Dipendenze | Ultima revisione | Note |
|---|---|---|---|---|---|---|---|---|
| Document Register | v1.0 | Attivo | 2026-07-29 | `docs/00_Governance/Document_Register.md` | — | Tutti i documenti sottostanti | 2026-07-29 | Questo file |
| Project Book (canonico) | 12 decisioni (§4-19) | Attivo, git-tracked | pre-esistente | `docs/PROJECT_BOOK.md` | — | — | pre-esistente | Fonte canonica, aggiornata ad ogni PR. Diverge da Project_Book_v3.1.docx: vedi nota sotto |
| Project Book (edizione Word) | v3.1 | Importato, da riconciliare | pre-esistente (caricato dall'utente) | `docs/00_Governance/Project_Book_v3.1.docx` | — | — | 2026-07-29 (importato) | Contiene Decision Log fino a #024, struttura narrativa Parte I-V; numerazione/titoli non coincidono sempre con la fonte canonica `.md` oltre la #012 |
| Masterplan dello Speciale Turing | v1.0 | Ricostruito | 2026-07-29 | `docs/00_Governance/Masterplan_Speciale_Turing_v1.0.md` | I 6 audit | `docs/02_Turing_Audit/*` | 2026-07-29 | Sintesi ricostruita da cronologia, non trascrizione integrale dell'originale (consegnato solo in chat) |
| Architettura Editoriale | v1.0 | Importato | pre-esistente (caricato dall'utente) | `docs/00_Governance/Architettura_Editoriale_v1.0.docx` | Masterplan | Masterplan | 2026-07-29 (importato) | — |
| Blueprint Strutturale | v1.0 | Importato | pre-esistente (caricato dall'utente) | `docs/00_Governance/Blueprint_Strutturale_v1.0.docx` | Architettura Editoriale | Masterplan, Architettura Editoriale | 2026-07-29 (importato) | — |
| Decision Log dello Speciale Turing (estratto) | v1.0 | Attivo | 2026-07-29 | `docs/00_Governance/Decision_Log_Speciale_Turing_v1.0.md` | Project Book canonico | `docs/PROJECT_BOOK.md` | 2026-07-29 | Indice, non copia — segnala la divergenza tra le due edizioni del Project Book |

## Audit

| Nome documento | Versione | Stato | Data | Percorso | Dipendenze | Note |
|---|---|---|---|---|---|---|
| Audit Release Candidate — Hub | v1.0 | Ricostruito | 2026-07-29 | `docs/02_Turing_Audit/Audit_Hub_Release_Candidate_v1.0.md` | — | — |
| Audit Enigma | v1.0 | Ricostruito, parziale | 2026-07-29 | `docs/02_Turing_Audit/Audit_Enigma_v1.0.md` | — | **Unica pagina senza voto a 6 assi** (asimmetria nota e preservata) |
| Audit AI (tecnico) | v1.0 | Ricostruito, parziale | 2026-07-29 | `docs/02_Turing_Audit/Audit_AI_Tecnico_v1.0.md` | — | — |
| Audit AI (editoriale) | v1.0 | Ricostruito, parziale | 2026-07-29 | `docs/02_Turing_Audit/Audit_AI_Editoriale_v1.0.md` | Audit AI tecnico | — |
| Audit Computation | v1.0 | Ricostruito, parziale | 2026-07-29 | `docs/02_Turing_Audit/Audit_Computation_v1.0.md` | — | — |
| Audit Intelligence | v1.0 | Ricostruito, parziale | 2026-07-29 | `docs/02_Turing_Audit/Audit_Intelligence_v1.0.md` | — | — |
| Audit Legacy | v1.0 | Ricostruito, parziale | 2026-07-29 | `docs/02_Turing_Audit/Audit_Legacy_v1.0.md` | — | Contiene il bug più concreto dell'intero Speciale (3 link teaser) |
| Investigation — sezioni mancanti hub | pre-esistente | Attivo, git-tracked | pre-esistente | `docs/TURING_MISSING_SECTIONS_INVESTIGATION.md` | — | Indagine tecnica distinta dai 6 audit, riguarda dati CMS/seeder, non contenuti editoriali |

## Produzione

| Nome documento | Versione | Stato | Data | Percorso | Dipendenze | Note |
|---|---|---|---|---|---|---|
| Production Tracker | v0.1 | DA REDIGERE | 2026-07-29 | `docs/03_Turing_Produzione/Production_Tracker_v0.1.md` | Masterplan | Placeholder |
| Matrice di Tracciabilità | v0.1 | DA REDIGERE | 2026-07-29 | `docs/03_Turing_Produzione/Matrice_Tracciabilita_v0.1.md` | Architettura Editoriale §4 | Fonte parziale già esistente nel Blueprint editoriale |
| Glossario | v1.0 | Attivo | 2026-07-29 | `docs/03_Turing_Produzione/Glossario_v1.0.md` | Manuale Cap. 1-2 | Solo aggiunte future, mai ridefinizioni |
| Piano di Produzione | — | Non estratto | — | Presente in `Blueprint_Strutturale_v1.0.docx` §8 | — | Non ancora estratto in documento autonomo |

## Visual

| Nome documento | Versione | Stato | Data | Percorso | Dipendenze | Note |
|---|---|---|---|---|---|---|
| Manuale di Identità Visiva — Volume I | v0.3 (3/10 capitoli) | Attivo | 2026-07-29 | `docs/04_Turing_Visual/Kairus_Manuale_Identita_Visiva_Volume_I_v0.3.docx` | Glossario_v1.0.md | Capitoli 1-2 approvati, Capitolo 3 ("Il rischio della decorazione") completato in questa sessione |
| Piano Iconografico | v0.1 | DA REDIGERE | 2026-07-29 | `docs/04_Turing_Visual/Piano_Iconografico_v0.1.md` | Registro Asset, Manuale Cap. 2 | Placeholder |
| Registro Asset Turing | v1.0 | Attivo | 2026-07-29 | `docs/04_Turing_Visual/Registro_Asset_Turing_v1.0.md` | `docs/TURING_EDITORIAL_ASSETS.md` | Integra la fonte canonica con gli asset non utilizzati emersi dagli audit |
| Prompt Master (immagini) | v0.1 | DA REDIGERE | 2026-07-29 | `docs/04_Turing_Visual/Prompt_Master_v0.1.md` | Registro Asset | Concettualmente parte del futuro Volume IV del Manuale |

## Fonti

| Nome documento | Versione | Stato | Data | Percorso | Dipendenze | Note |
|---|---|---|---|---|---|---|
| Registro Fonti / Bibliografia | v0.1 | DA REDIGERE | 2026-07-29 | `docs/05_Turing_Fonti/Registro_Fonti_v0.1.md` | Audit Intelligence | Unica fonte primaria già identificata: saggio 1950 |

## Release

| Nome documento | Versione | Stato | Data | Percorso | Dipendenze | Note |
|---|---|---|---|---|---|---|
| Checklist Release Candidate | v1.0 | Attivo | 2026-07-29 | `docs/06_Turing_Release/Checklist_Release_Candidate_v1.0.md` | I 6 audit | Sostituisce Report Accessibilità/Performance/Test separati (contenuto già aggregato qui) |
| Report SEO | v0.1 | DA REDIGERE | 2026-07-29 | `docs/06_Turing_Release/Report_SEO_v0.1.md` | — | Mai eseguito un audit SEO Turing-specifico |

## Documentazione pre-esistente non Turing-specifica (non riorganizzata)

Questi file erano già presenti in `docs/` prima di questa sessione e restano nella loro posizione originale, essendo documentazione di piattaforma più ampia, non specifica dello Speciale Turing: `ARTICLE_COVER_ASSETS.md`, `EDITORIAL_IMAGE_SYSTEM.md`, `EDITORIAL_WORKFLOW.md`, `MEDIA_LIBRARY.md`, e la directory `evidence/timeline-chapters/`.

## Azione richiesta, non ancora presa

La divergenza tra `docs/PROJECT_BOOK.md` (canonico, fino a Decision #012) e `docs/00_Governance/Project_Book_v3.1.docx` (fino a Decision #024, struttura riorganizzata) resta **irrisolta per scelta**, in ossequio alla regola "mai sovrascrivere, mai eliminare versioni precedenti" e "in caso di incoerenza prevale il documento con autorità maggiore" — qui entrambi i documenti rivendicano lo stesso ruolo, quindi la riconciliazione richiede una decisione editoriale esplicita che non spetta a questo processo di consolidamento.
