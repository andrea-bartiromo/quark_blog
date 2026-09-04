# Prestazioni e CSS — Baseline locale (Cantiere J, Prompt 237-241)

**Avvertenza obbligatoria**: tutte le misure sotto sono state
raccolte con `php artisan serve` (server di sviluppo integrato,
mono-thread, nessuna opcode cache di produzione, nessuna CDN, nessuna
concorrenza reale, nessun HTTP/2) su questa macchina, con un database
SQLite locale e dati di prova ridotti. **Sono utili solo per confronto
relativo prima/dopo all'interno di questo stesso cantiere — non
rappresentano, nemmeno approssimativamente, le prestazioni reali in
produzione.** Nessun claim assoluto ("la home carica in Xms") va letto
come prestazione di produzione.

## Metodo

Playwright + Chromium locale, viewport 1280×900, cache browser vuota
a ogni navigazione, `waitUntil:'load'`. Metriche lette da
`performance.getEntriesByType('navigation')`/`'paint'` (API standard
del browser, non stimate).

## Misure "prima" (HEAD = `5e48bf25edb00ab43edb753a0044962e25ef5a47`, prima di qualunque modifica di questo cantiere)

| Superficie | DOMContentLoaded | Load | Copertura CSS `editorial-system.css` (pagina singola) |
|---|---|---|---|
| Home | ~13.0s | ~13.0s | 28.3% (14403/50857 B) |
| Percorsi indice | ~12.7s | ~12.8s | 16.2% (8260/50857 B) |
| Percorso dettaglio | ~13.1s | ~13.1s | 13.5% (6853/50857 B) |
| Articolo | ~15.9s | ~15.9s | 17.3% (8789/50857 B) |
| Notizie | ~12.8s | ~12.8s | 17.2% (8742/50857 B) |
| Categoria | ~12.8s | ~12.8s | 17.2% (8742/50857 B) |
| Ricerca | ~12.9s | ~12.9s | 17.3% (8820/50857 B) |

I tempi (12-16 secondi) sono dominati dal server di sviluppo
mono-thread e dalle query N+1 già note e non toccate da questo
cantiere (sidebar, opzioni categoria) — non dal CSS. Non è stato
possibile isolare qui il contributo specifico del CSS al tempo di
caricamento con questo server; l'unica metrica CSS attendibile in
questo ambiente è la **copertura** (byte effettivamente applicati),
non il tempo.

Copertura CSS per-pagina fisiologicamente bassa (13-28%): attesa per
un foglio di stile condiviso da 7 superfici — ogni pagina esercita
solo le regole della propria superficie. La copertura in **unione**
su tutte le 7 superfici insieme è **43.3%** (22012/50857 B) — vedi
`docs/PERFORMANCE_CSS_AUDIT.md` per l'analisi completa di cosa
compone il restante 56.7% (commenti, stati `:hover`/`:focus-visible`,
media query per altri viewport, varianti di componente non esercitate
da questi fixture — nessun codice morto).

## File CSS coinvolti

`public/css/editorial-system.css`: 51034 byte, 1675 righe, non
minificato (nessuno strumento di build CSS in uso nel progetto — tutti
i file in `public/css/` sono serviti cosi' come sono). Nessun altro
file CSS toccato da questo cantiere.

## Misure "dopo"

Vedi la sezione dedicata nel report finale
(`docs/KAIRUS_PUBLIC_RESTRUCTURING_FINAL_HANDOFF.md`, Prompt 251) —
nessuna modifica a `editorial-system.css` in questo cantiere ha
cambiato il numero di byte serviti in modo significativo (nessuna
rimozione CSS eseguita, vedi `PERFORMANCE_CSS_AUDIT.md`), quindi la
copertura e le dimensioni restano sostanzialmente invariate; il
confronto "dopo" nel report finale lo conferma esplicitamente invece
di darlo per scontato.
