# Decision Log dello Speciale Turing — estratto

| Campo | Valore |
|---|---|
| Versione | v1.0 |
| Data | 2026-07-29 |
| Stato | ESTRATTO DA FONTE ESISTENTE — non duplica il testo integrale |
| Autore | Sessione Claude Code |
| Dipendenze | `docs/PROJECT_BOOK.md` (fonte canonica, testo integrale) |
| Documenti correlati | Project_Book_v3.1.docx, Masterplan_Speciale_Turing_v1.0.md |

> Questo documento è un **indice**, non una copia: il testo integrale di ogni decisione resta nell'unica fonte autorevole, `docs/PROJECT_BOOK.md` (già presente nel repository, sezione "4. Decision Log" e successivi paragrafi "Aggiornamento successivo alla Pull Request #N"). Duplicarlo qui violerebbe la regola "se il contenuto appartiene a un documento esistente, aggiorna quello, non crearne uno nuovo". Questo estratto seleziona solo le decisioni pertinenti allo Speciale Turing, con riferimento a riga per consultazione rapida.

## Decisioni pertinenti allo Speciale Turing

| # | Titolo | Riga in `docs/PROJECT_BOOK.md` | Sintesi in una riga |
|---|---|---|---|
| #001 | Nuova architettura della Timeline | 37 | Ridisegno della Timeline dello Speciale Turing dopo criticità emerse in sviluppo. |
| #002 | Componente Timeline riutilizzabile e design token | 164 | Timeline estratta in `<x-special.timeline>`, riusabile da futuri Special Project. |
| #003 | Capitoli temporali e Chapter Opener | 179 | Introduzione dei capitoli temporali come struttura narrativa condivisa. |
| #004 | Componenti comuni degli Special Projects | 192 | Consolidamento dei componenti condivisi tra Special Project. |
| #005 | Media Library per gli Special Projects | 208 | Gestione centralizzata degli asset fotografici/grafici. |
| #006 | Feature Cards riutilizzabili | 240 | Estrazione delle card `.turing-route-card` in componente dati-driven condiviso. |
| #007 | Scoping CSS di Turing e consolidamento `has-bg` | 292 | Eliminazione del conflitto `turing.css` / `turing-overrides.css`; `turing.css` unica fonte di verità. |
| #008 | Section Header riutilizzabile | 355 | `<x-special.section-header>` come componente condiviso. |
| #009 | Modal riutilizzabile per gli Special Projects | 407 | `<x-special.modal>` per gli approfondimenti in overlay. |
| #010 | Pagina di dettaglio dedicata all'Eredità di Turing | 450 | Introduzione di `/turing/legacy`, primo utilizzo pieno del Design System per una pagina di dettaglio. |
| #011 | Pagina di dettaglio dedicata alla Computazione | 526 | Introduzione di `/turing/computation`, stesso modello architetturale di #010. |
| #012 | Pagina di dettaglio dedicata all'Intelligenza (Test di Turing) | 600 | Introduzione di `/turing/intelligence`; separazione dei ruoli editoriali rispetto a `/turing/ai`; corretta una regressione di immagini hero rotte ereditata dalla PR #46. |

## Nota sulla divergenza con l'edizione Word "v3.1"

Il file caricato `Project_Book_v3.1.docx` (ora in `docs/00_Governance/Project_Book_v3.1.docx`) contiene una numerazione delle decisioni che **arriva fino a #024** ed è strutturata in modo narrativo/editoriale (Parte I–V), mentre `docs/PROJECT_BOOK.md` — la fonte canonica nel repository, aggiornata ad ogni Pull Request — si ferma oggi alla **Decision #012**. Inoltre alcuni titoli non coincidono a parità di numero (es. **#005**: "Chapter Opener riutilizzabile" nella versione Word contro "Media Library per gli Special Projects" nel file `.md`), il che indica che la versione Word non è una semplice prosecuzione cronologica del file `.md`, ma una **redazione editoriale riorganizzata**, prodotta in una sessione precedente e mai ricondotta al file canonico del repository.

**Questa divergenza non viene risolta unilateralmente in questo documento**, per rispetto della regola "in caso di incoerenza prevale il documento con autorità maggiore" — qui entrambi i documenti rivendicano il ruolo di Project Book. Si raccomanda una verifica editoriale dedicata (vedi Document_Register, nota su `Project_Book_v3.1.docx`) prima di usare la numerazione "#013–#024" della versione Word come fonte per nuovo lavoro: quelle decisioni non sono al momento riscontrabili nel file `.md` versionato nel repository.
