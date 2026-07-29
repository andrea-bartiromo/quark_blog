# Masterplan dello Speciale Turing

| Campo | Valore |
|---|---|
| Versione | v1.0 |
| Data | Ricostruito il 2026-07-29 |
| Stato | RICOSTRUITO DA CRONOLOGIA DI SESSIONE — sintesi, non trascrizione integrale |
| Autore | Sessione Claude Code |
| Dipendenze | I 6 audit in `docs/02_Turing_Audit/` |
| Documenti correlati | Architettura_Editoriale_v1.0.docx, Blueprint_Strutturale_v1.0.docx |

> Ricostruito esclusivamente dalle evidenze dei 6 audit già consolidati in `docs/02_Turing_Audit/`, come richiesto in origine ("usa esclusivamente le evidenze emerse nei report precedenti, non eseguire nuove analisi"). È una sintesi delle conclusioni, non la trascrizione integrale dell'originale (8 sezioni: Visione generale, Analisi comparativa, Mappa editoriale, Flusso di lettura, Mappa tecnica, Roadmap, Backlog PR, Valutazione finale).

## Visione generale

Lo Speciale Turing è composto da 5 pagine: hub (`/turing`), Enigma, Computation, Intelligence, AI, Legacy. Tre di queste (Computation, Intelligence, Legacy) sono pienamente allineate al Design System condiviso; due (Enigma, AI) restano bespoke con CSS duplicato indipendentemente.

## Analisi comparativa (sintesi)

| Pagina | Design System | CSS inline | Contrasto AA | Note |
|---|---|---|---|---|
| Enigma | Nessuno | ~360 righe | non misurato in isolamento | Nessun voto a 6 assi mai assegnato (asimmetria nota, non risolta) |
| AI | Nessuno | ~360 righe | 1 bug specifico grave (1.42:1) + 3 condivisi | Contenuto troppo sintetico (≈400 parole) |
| Computation | Completo | 0 | 3 difetti condivisi | ≈565 parole |
| Intelligence | Completo | 0 | 3 difetti condivisi | ≈565 parole |
| Legacy | Completo | 0 | 3 difetti condivisi | 3 link teaser errati (righe 36-64) |

## Mappa editoriale

Enigma → base storica; Computation → macchina universale; Intelligence → test del 1950, framing storico-concettuale; AI → dibattito contemporaneo sui LLM (senza duplicare Intelligence); Legacy → eredità, persecuzione, riabilitazione, attualità, e chiusura dello Speciale.

## Flusso di lettura

Il flusso previsto (hub → Enigma → Computation → Intelligence → AI → Legacy) è oggi rotto in uscita da Legacy, i cui 3 link teaser di rilancio verso le altre pagine sono in parte errati (vedi Audit_Legacy_v1.0.md).

## Mappa tecnica

Debito tecnico concentrato in 2 punti: (1) 3 difetti di contrasto WCAG AA condivisi a livello di `turing.css`, propagati a tutte le pagine che riusano `<x-turing.article.*>`; (2) doppia migrazione mancante (Enigma, AI) verso lo stesso Design System già adottato con successo dalle altre tre.

## Roadmap e Backlog delle PR (deduplicato)

1. **Fix contrasto condiviso** su `turing.css` (3 selettori) — impatta hub/computation/intelligence/legacy in un solo intervento.
2. **Fix contrasto specifico `.ai-light-text`** su `/turing/ai` — priorità alta, difetto più severo dello Speciale (1.42:1).
3. **Fix 3 link teaser** in `legacy.blade.php` righe 36-64 — proposto indipendentemente sia dall'audit Legacy sia dall'audit Computation/Intelligence; deduplicato in un'unica PR.
4. **Scrittura nuovi contenuti** per `/turing/ai` (approfondimento su imitazione vs comprensione, apparato divulgativo).
5. **Migrazione Enigma e AI** al Design System condiviso — solo dopo il punto 4, per non dover riscrivere i contenuti due volte.

## Valutazione finale

Lo Speciale è pubblicabile nella sua forma attuale (nessun bug bloccante rilevato dall'Audit Hub RC), con debito tecnico ed editoriale noto e ordinato per priorità come sopra. La correzione dei link Legacy e del contrasto `.ai-light-text` sono gli interventi a più alto rapporto impatto/costo.
