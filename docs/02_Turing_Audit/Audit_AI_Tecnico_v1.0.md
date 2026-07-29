# Audit tecnico — `/turing/ai`

| Campo | Valore |
|---|---|
| Versione | v1.0 |
| Data | Ricostruito il 2026-07-29 |
| Stato | RICOSTRUITO DA CRONOLOGIA DI SESSIONE — parziale |
| Autore | Sessione Claude Code |
| Dipendenze | Nessuna |
| Documenti correlati | Audit_AI_Editoriale_v1.0.md, Masterplan_Speciale_Turing_v1.0.md, Audit_Enigma_v1.0.md |

> Ricostruzione da cronologia di sessione, non trascrizione verbatim del report originale (checklist tecnica a 24 punti).

## Struttura

- View: `resources/views/turing/ai.blade.php` — **644 righe**, bespoke, ~360 righe di `<style>` inline, stesso pattern architetturale di `enigma.blade.php` (duplicazione indipendente, non condivisa).
- Zero componenti Design System, zero binding CMS, immagine hero unica condivisa.
- Route `turing.ai` risultata duplicata a livello di `TuringServiceProvider` (verificata e aggirata con percorso letterale in `computation.blade.php`/`intelligence.blade.php`, mai razionalizzata — esplicitamente fuori scope in Decision #012 del Project Book).

## Problema di contrasto specifico (non condiviso con le altre pagine)

`.ai-light-text` renderizza `#cbd5e1` invece del previsto `#475569` in 2 istanze su 3 (quelle annidate dentro `.ai-copy`), per collisione di specificità CSS: `.ai-copy p` batte il selettore a classe singola `.ai-light-text`. Misurato: **1.42:1**, confermato visivamente via screenshot desktop e mobile — il difetto di contrasto più severo tra tutti quelli riscontrati nell'intero Speciale.

## Contenuti

Word count narrativo misurato: **≈400 parole**, il più basso tra le pagine di approfondimento — dato centrale nell'audit editoriale separato (`Audit_AI_Editoriale_v1.0.md`).

## Duplicazioni e Design System

Pattern "hero scuro + griglia 01/02/03 + pannello terminale fittizio + CTA finale" duplicato quasi identicamente in `enigma.blade.php`, senza alcuna condivisione di componenti tra le due pagine.

## Piano di migrazione proposto

Stesso modello architetturale di computation/intelligence/legacy: `<x-turing.article.*>` + `<x-special.section-header>`, eliminazione CSS inline, correzione del bug di contrasto `.ai-light-text` come intervento indipendente e prioritario (raccomandato **prima** della migrazione completa, per rimuovere il difetto di accessibilità più grave dello Speciale nel minor tempo possibile).
