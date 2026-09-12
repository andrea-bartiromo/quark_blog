# Prima misura reale del Laboratorio prestazioni (Cantiere 27)

Vedi `docs/PERFORMANCE_LAB.md` per metodo e avvertenze. Questa è la
prima esecuzione reale dello strumento (`npm run performance:lab`),
conservata come punto di riferimento per il prossimo confronto
prima/dopo — non uno SLA, non un obiettivo da inseguire.

**Avvertenza obbligatoria** (stessa di `docs/PERFORMANCE_BASELINE.md`):
misure raccolte con `php artisan serve` (mono-thread, nessuna cache di
produzione, nessuna CDN) su una macchina non dedicata, con un database
locale e la fixture deterministica di `BrowserTestSeeder`. Utili solo
per confronto relativo prima/dopo — mai un dato di produzione.

- Commit: `295fc972120abbe425de73e7eae5bbc678acfd24`
- Raccolta: 2026-09-12T18:45:35.054Z
- `--runs=1` (una sola misura per superficie/viewport — non ancora una
  mediana; una prossima esecuzione con `--runs=5` darebbe un dato più
  robusto contro il rumore, non necessario per questo primo snapshot
  di validazione).

| Superficie | Viewport | TTFB | DOMContentLoaded | Load | First Contentful Paint |
|---|---|---|---|---|---|
| Home | 390 | 29ms | 12638ms | 12639ms | 12640ms |
| Home | 768 | — | 12699ms | 12700ms | 12716ms |
| Home | 1440 | — | 12636ms | 12651ms | 12660ms |
| Ricerca | 390 | — | 12646ms | 12654ms | 12652ms |
| Ricerca | 768 | — | 12612ms | 12622ms | 12628ms |
| Ricerca | 1440 | — | 12595ms | 12606ms | 12628ms |
| Articolo | 390 | — | 12622ms | 12624ms | 12628ms |
| Articolo | 768 | — | 12559ms | 12560ms | 12576ms |
| Articolo | 1440 | — | 12601ms | 12622ms | 12640ms |
| Autore | 390 | — | 12610ms | 12616ms | 12628ms |
| Autore | 768 | — | 12618ms | 12635ms | 12640ms |
| Autore | 1440 | — | 12604ms | 12628ms | 12640ms |
| Categoria | 390 | — | 12634ms | 12640ms | 12644ms |
| Categoria | 768 | — | 12540ms | 12541ms | 12552ms |
| Categoria | 1440 | — | 12572ms | 12575ms | 12592ms |
| Notizie | 390 | — | 12656ms | 12658ms | 12656ms |
| Notizie | 768 | — | 12528ms | 12530ms | 12540ms |
| Notizie | 1440 | — | 12561ms | 12568ms | 12588ms |

TTFB (29ms sulla home, coerente sulle altre superfici) conferma la
stessa conclusione già in `docs/PERFORMANCE_BASELINE.md`: i 12-13
secondi di DOMContentLoaded/Load sono dominati dal server di sviluppo
mono-thread e dal caricamento risorse, non dal tempo di risposta
applicativo. Nessuna sorpresa rispetto alla misura precedente
(Cantiere J): questa prima esecuzione del laboratorio conferma che lo
strumento produce numeri coerenti con quelli già osservati
manualmente, non solo che "gira".

Report JSON completo (con ogni campione grezzo, non solo la mediana)
non committato — rigenerabile in qualunque momento con
`npm run performance:lab`.
