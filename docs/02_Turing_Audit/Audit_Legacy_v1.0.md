# Audit tecnico ed editoriale — `/turing/legacy`

| Campo | Valore |
|---|---|
| Versione | v1.0 |
| Data | Ricostruito il 2026-07-29 |
| Stato | RICOSTRUITO DA CRONOLOGIA DI SESSIONE — parziale |
| Autore | Sessione Claude Code |
| Dipendenze | Nessuna |
| Documenti correlati | Masterplan_Speciale_Turing_v1.0.md, Audit_Computation_v1.0.md, Audit_Intelligence_v1.0.md, Audit_Enigma_v1.0.md, Audit_AI_Editoriale_v1.0.md |

## Struttura e Design System

- View: `resources/views/turing/legacy.blade.php` — 118-120 righe, zero CSS inline, adozione completa del Design System. Introdotta in Decision #010 del Project Book, route `turing.legacy`.
- Test: `tests/Feature/TuringLegacyPageTest.php`, 9 test — **notevolmente privo di qualsiasi test che verifichi i link verso `/turing/computation` o `/turing/intelligence`**, a differenza degli altri audit.

## Bug reale confermato: 3 link interni errati

Righe **36-64** di `legacy.blade.php` contengono tre teaser link, di cui due errati:

| Link | Route usata | Route corretta | Esito |
|---|---|---|---|
| Macchina universale | `route('turing').'#macchina-universale'` | `route('turing.computation')` | ERRATO |
| Enigma | `route('turing.enigma')` | (invariato) | CORRETTO |
| Test di Turing / AI | `route('turing.ai')` | `route('turing.intelligence')` (il testo circostante discute esplicitamente il saggio del 1950) | ERRATO |

Questo è il bug tecnico più concreto e riproducibile riscontrato nei 5 audit, ed è stato proposto come PR indipendente nel Masterplan (deduplicato: proposto due volte, autonomamente, in due audit diversi — PR-L3 dell'audit Legacy e PR-C3 dell'audit Computation coincidevano parzialmente; PR-I3/PR-L4 idem — la deduplicazione è stata applicata esplicitamente nel Masterplan).

## Posizionamento della pagina nello Speciale Turing

Sezione obbligatoria dell'audit originale: valutazione del ruolo di `/turing/legacy` rispetto a Enigma/Computation/Intelligence/AI. Conclusione registrata: lo spazio editoriale di Legacy è nel complesso ben definito (eredità storica/culturale, persecuzione, riabilitazione, attualità), ma la pagina **non funziona pienamente come conclusione dello Speciale** a causa dei tre link teaser difettosi sopra descritti, che di fatto rompono il flusso di navigazione verso le altre pagine proprio nel punto in cui dovrebbero rilanciarlo. Questa criticità è stata il fattore che ha determinato, nel Masterplan, la proposta di un flusso di lettura esplicito e la richiesta correzione dei link come intervento prioritario.

## Contrasto

Stessi tre difetti condivisi del Design System (vedi Audit Hub RC).

## Piano di miglioramento proposto

1. Correzione dei tre link teaser (righe 36-64) — priorità alta, bug riproducibile e concreto.
2. Aggiunta di test di copertura per i link verso computation/intelligence, assenti in `TuringLegacyPageTest.php`.
3. Correzione dei tre difetti di contrasto condivisi a livello di `turing.css`.
