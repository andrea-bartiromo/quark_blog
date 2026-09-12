# Prima misura reale del Laboratorio prestazioni (Cantiere 27)

Vedi `docs/PERFORMANCE_LAB.md` per metodo e avvertenze. Questa è la
prima esecuzione **attendibile** dello strumento (`npm run performance:lab`),
conservata come punto di riferimento per il prossimo confronto
prima/dopo — non uno SLA, non un obiettivo da inseguire.

**Avvertenza obbligatoria** (stessa di `docs/PERFORMANCE_BASELINE.md`):
misure raccolte con `php artisan serve` (mono-thread, nessuna cache di
produzione, nessuna CDN) su una macchina non dedicata, con un database
locale e la fixture deterministica di `BrowserTestSeeder`. Utili solo
per confronto relativo prima/dopo — mai un dato di produzione.

- Commit: `6084a5c` (fix isolamento terze parti + validazione risposta,
  vedi sotto)
- Raccolta: 2026-09-12T22:14:02.357Z
- `--runs=3` (mediana di 3 misure per superficie/viewport)

| Superficie | Viewport | TTFB | DOMContentLoaded | Load | First Contentful Paint |
|---|---|---|---|---|---|
| Home | 390 | 48ms | 321ms | 323ms | 320ms |
| Home | 768 | 48ms | 319ms | 348ms | 352ms |
| Home | 1440 | 49ms | 180ms | 204ms | 212ms |
| Ricerca | 390 | 38ms | 143ms | 152ms | 144ms |
| Ricerca | 768 | 33ms | 142ms | 153ms | 156ms |
| Ricerca | 1440 | 35ms | 139ms | 152ms | 180ms |
| Articolo | 390 | 49ms | 177ms | 179ms | 200ms |
| Articolo | 768 | 46ms | 179ms | 182ms | 204ms |
| Articolo | 1440 | 50ms | 191ms | 193ms | 224ms |
| Autore | 390 | 36ms | 143ms | 157ms | 160ms |
| Autore | 768 | 34ms | 139ms | 142ms | 164ms |
| Autore | 1440 | 35ms | 129ms | 148ms | 196ms |
| Categoria | 390 | 39ms | 164ms | 169ms | 168ms |
| Categoria | 768 | 36ms | 139ms | 153ms | 160ms |
| Categoria | 1440 | 39ms | 142ms | 149ms | 172ms |
| Notizie | 390 | 40ms | 156ms | 161ms | 160ms |
| Notizie | 768 | 45ms | 158ms | 167ms | 176ms |
| Notizie | 1440 | 44ms | 145ms | 148ms | 184ms |

## Conclusione (corretta rispetto alla prima esecuzione)

La primissima esecuzione dello strumento (commit `295fc97`, non
conservata come baseline) aveva prodotto DOMContentLoaded/Load/FCP
uniformi a ~12.6-12.7 secondi su **ogni** superficie e viewport, con lo
stesso identico ordine di grandezza indipendentemente dal contenuto
della pagina. Una revisione Codex su questa PR ha correttamente
individuato che quell'uniformità non era compatibile con una
conclusione di "server di sviluppo mono-thread lento": un server lento
avrebbe comunque mostrato tempi diversi tra una home page e una pagina
di ricerca. La causa reale, già documentata in
`docs/CWV_BASELINE_RUNNER.md` per `scripts/cwv-baseline.mjs`, è che
`resources/views/layouts/partials/head.blade.php` carica il foglio di
stile Google Fonts, e in questo ambiente sandboxato quella richiesta
fallisce con `ERR_CONNECTION_RESET`; il retry/backoff del browser
blocca l'evento `load` per ~12-13 secondi su ogni pagina, mascherando
qualunque differenza reale tra le superfici.

Con il blocco esplicito di ogni richiesta di terze parti (questo
commit), i numeri sopra sono coerenti con quanto ci si aspetta da
`php artisan serve` su contenuto realmente leggero: TTFB 33-55ms,
Load sotto i 350ms su ogni combinazione, e — cosa che la misura
precedente non poteva mostrare — tempi che VARIANO in modo sensato tra
le superfici (la home, con più payload, è la più lenta; ricerca e
categoria, più leggere, sono le più veloci). Nessun claim di
prestazioni di produzione: resta un dato di ambiente di sviluppo non
dedicato, utile solo per un confronto relativo prima/dopo.

Report JSON completo (con ogni campione grezzo, non solo la mediana)
non committato — rigenerabile in qualunque momento con
`npm run performance:lab`.
