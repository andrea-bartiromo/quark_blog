# Laboratorio prestazioni (Cantiere 27, programma 100-cantieri Kairus)

Strumento ripetibile per catturare metriche di navigazione reali su un
set fisso di superfici pubbliche, cosi' un confronto prima/dopo tra due
esecuzioni misura un cambiamento reale dell'applicazione, non una
differenza di ambiente o di contenuto.

## Perché

`docs/PERFORMANCE_CWV_S3_AUDIT_PLAN.md` aveva rimandato ogni audit
Core Web Vitals per mancanza di un browser affidabile nella sessione
di allora. `docs/PERFORMANCE_BASELINE.md` (Cantiere J) ha prodotto una
sola misura ad-hoc, non ripetibile automaticamente. Questo laboratorio
colma entrambi i gap: Chromium/Playwright sono già presenti nel
progetto (`tests/browser/*.spec.js`), e questo script li riusa per
produrre una misura ogni volta che serve, non una tantum.

## Uso

```
npm run performance:lab
npm run performance:lab -- --runs=5
npm run performance:lab -- --out=docs/performance-lab/2026-09-12.json
```

Richiede una libreria di sviluppo con la fixture deterministica già
seminata:

```
touch database/database.sqlite   # se non esiste già
php artisan migrate:fresh --force
php artisan db:seed --class="Database\Seeders\BrowserTestSeeder" --force
```

Lo script avvia un proprio `php artisan serve` su una porta dedicata
(8199, mai quella di `test:browser`), lo interroga, lo termina alla
fine. Non tocca mai un server già in esecuzione né dati di produzione.

## Cosa misura

Stesse superfici e larghezze di viewport (390/768/1440) già usate da
`tests/browser/public-regression.spec.js`, sulla stessa fixture
deterministica (`Database\Seeders\BrowserTestSeeder`): home, ricerca
con risultati, articolo, autore, categoria, notizie.

Per ciascuna combinazione, con cache del browser vuota a ogni
navigazione:

- **DOMContentLoaded** e **Load** (`performance.getEntriesByType('navigation')`);
- **TTFB** (`responseStart`);
- **First Paint** / **First Contentful Paint** (`performance.getEntriesByType('paint')`);
- dimensione del trasferimento di rete della navigazione principale.

Tutte API standard del browser, mai stimate. Con `--runs` > 1 (default
3), ogni combinazione viene misurata più volte e il report riporta la
**mediana**, non un singolo numero rumoroso.

## Avvertenza obbligatoria (stessa di `PERFORMANCE_BASELINE.md`)

Misure raccolte con `php artisan serve` (server di sviluppo integrato,
mono-thread, nessuna opcode cache di produzione, nessuna CDN, nessuna
concorrenza reale, nessun HTTP/2) su una macchina non dedicata, con un
database locale e dati di prova ridotti. **Utili solo per confronto
relativo prima/dopo all'interno dello stesso ambiente — non
rappresentano, nemmeno approssimativamente, le prestazioni reali in
produzione.** Nessun claim assoluto va letto come prestazione di
produzione.

## Output

Un file JSON (default `docs/performance-lab/latest.json`, mai
sovrascritto silenziosamente in git — vedi nota sotto) con, per ogni
superficie/viewport: la mediana e ogni singolo campione raccolto.

Il report NON viene committato automaticamente: `docs/performance-lab/`
è nel `.gitignore` a eccezione di snapshot esplicitamente salvati con
`--out` e aggiunti manualmente quando si vuole conservare un confronto
prima/dopo per una PR specifica (stesso principio già seguito da
`docs/PERFORMANCE_BASELINE.md`: un numero ha senso solo insieme al
contesto — commit, ambiente, metodo — che lo accompagna).

## Non è un gate di rilascio

Sola lettura, nessuna scrittura sull'applicazione, mai eseguito in CI
(troppo lento e troppo dipendente dalla macchina per essere un segnale
affidabile in una pipeline condivisa — stesso principio di ogni altro
comando `*-audit`/`*-report` di questo programma). Uno strumento per
un operatore che sta valutando un cambiamento specifico, non un numero
da inseguire.
