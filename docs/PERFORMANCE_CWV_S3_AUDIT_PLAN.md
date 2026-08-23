# Performance & Core Web Vitals Hardening S3

Missione 19 — `DESIGN COMPLETE — IMPLEMENTATION DEFERRED` perché questa sessione online non dispone di browser/runtime affidabile per misurare CWV.

## Regola di questo audit

Nessuna modifica performance senza:

1. `BEFORE EVIDENCE`;
2. una causa osservabile;
3. `CHANGE` strettamente collegato alla causa;
4. `AFTER EVIDENCE` nello stesso ambiente/profilo.

Il lavoro responsive-image già presente su main non viene assunto come problema residuo né come soluzione universale.

## Superfici da misurare

- home;
- articolo con cover e body immagini;
- categoria pagina 1 e pagina N;
- `/notizie`;
- ricerca con risultati;
- Percorso index/show;
- articolo con newsletter popup eligible;
- viewport mobile ~390, tablet ~768, desktop ~1440.

## Metriche

### LCP
Registrare:
- elemento LCP reale;
- URL/byte size della resource associata;
- priority/loading/fetchpriority;
- TTFB e resource load delay separati;
- se il candidato è hero, testo o altro elemento.

### CLS
Registrare ogni layout-shift cluster e attribuire la causa: immagini senza dimensioni, font swap, newsletter popup/alert, moduli dinamici, ads o altro.

### INP
Misurare interazioni reali: menu/navigation, search filters, newsletter popup close/submit, media lightbox, eventuali carousel. Separare main-thread JS da network latency.

## Audit tecnico

- `width`/`height` e aspect ratio di immagini;
- responsive `srcset/sizes` e `currentSrc` reale;
- hero eager/fetchpriority soltanto quando giustificato;
- lazy loading body/card images;
- CSS render blocking e dimensione per route;
- font richiesti, font-display, fallback/layout shift;
- first-party JS e long tasks;
- third-party requests (Google Fonts/analytics ecc.) separati dai costi first-party;
- newsletter band/popup scripts;
- analytics consent/exclusion behavior;
- article body external images;
- category pagination pages.

## Ambiente minimo per evidence

- checkout esatto di `main` o branch sotto test;
- app in esecuzione con fixture deterministiche;
- Chromium/Playwright o Chrome DevTools Performance;
- stessa macchina/profilo throttling prima/dopo;
- cache state dichiarato;
- almeno 3 run per scenario quando si usano metriche rumorose;
- report mediano + range, non un singolo numero favorevole.

## Lighthouse

Lighthouse è utile solo se l'ambiente non introduce blocchi di rete artificiali. Se CDN/font/analytics falliscono per sandbox, annotare il rumore e non attribuirlo al codice applicativo. Nessuna score-chasing optimization basata su una singola run.

## Decision matrix

Una modifica può entrare in PR soltanto se:

- la regressione è riproducibile;
- il collo di bottiglia è first-party o controllabile dal repository;
- il beneficio dopo il change è misurabile;
- non rimuove analytics/funzioni prodotto per migliorare artificialmente il punteggio;
- non collide con PR attive sulle stesse superfici.

## Stato corrente

Questa sessione non può produrre `BEFORE EVIDENCE` browser affidabile. Di conseguenza **nessun runtime file è stato modificato** e nessun numero LCP/CLS/INP viene inventato.

## Safety

Docs-only. Nessuna migration, nessuna mutation, nessun merge/deploy.
