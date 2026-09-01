# Settembre 2026 — misurazione, continuità e closeout tecnico

Baseline repository esaminata: `84a5803222f940e71cad5e435c52b92171733991`.
Production dichiarata e certificata dall'operatore sullo stesso SHA il
2026-08-31. Questo documento non sostituisce una lettura production.

## Core Web Vitals, CLS, WCAG e SEO (Prompt 26–32)

Il runner `scripts/cwv-baseline.mjs` e i contratti browser esistono. L'ultima
esecuzione storica documentata non è una baseline utilizzabile: Kairus rispondeva
in circa 30 ms, mentre Google Fonts bloccati dall'ambiente portavano ogni pagina
a circa 12,7–12,9 secondi. Il ritardo non è attribuibile con evidenza a codice
owned. Di conseguenza non è autorizzata una PR LCP.

Budget proposti, da misurare sul medesimo SHA e con rete dichiarata:

| Metrica | verde | blocco regressione |
|---|---:|---:|
| LCP p75 mobile | ≤ 2,5 s | > 3,0 s o +15% |
| CLS p75 | ≤ 0,10 | > 0,15 |
| INP p75 | ≤ 200 ms | > 300 ms |
| errori console first-party | 0 | > 0 |
| overflow orizzontale critical journeys | 0 | > 0 |

Le immagini responsive riservano dimensioni, `srcset`/`sizes` e priorità hero;
i test browser esistenti coprono layout shift, overflow, focus e reduced motion.
Non emerge un finding CLS riproducibile dal repository.

I quattro finding di contrasto storici del verbale Turing risultano assorbiti:
breadcrumb link 9,59:1, breadcrumb corrente 6,96:1, kicker terminale 5,23:1,
testo AI chiaro 7,58:1. Nessun P0/P1 WCAG residuo è dimostrato staticamente.
Serve comunque un verbale runtime su home, articolo, ricerca, categoria,
Percorso, menu, newsletter e admin login.

SEO statico: canonical/paginazione di Percorsi, sitemap e confini admin sono
coperti da test. La pagina `/percorsi` dichiara `CollectionPage`, non `ItemList`:
la precedente certificazione che pretendeva `ItemList` era un'asserzione errata,
presente né sulla baseline `dd012c80` né sul target. Crawl, redirect HTTP/HTTPS e
WWW richiedono una sessione production read-only; i risultati non disponibili
non sono rappresentati come verdi.

## Seconda lettura e CTR (Prompt 33–35)

`ContinuationAnalyticsService` persiste soltanto `impression` e
`second_read_start` del modulo autonomo “Continua da qui”, con deduplica di
sessione ed esclusione staff. Non esistono eventi click distinti per next,
previous, pillar o tappe, né una sorgente di acquisizione Google/Social/
Newsletter/interno. Quindi:

- conversione proxy disponibile: `second_read_start / impression`, con finestra;
- CTR dei singoli placement: **non disponibile**, non zero;
- segmentazione per sorgente: **non disponibile**;
- breakdown Percorso: attribuzione alle membership correnti e non sommabile se
  un articolo appartiene a più Percorsi;
- utenti ritornanti: non misurabili con l'attuale schema privacy-safe.

Nessuna ottimizzazione copy/layout è giustificata senza denominatore e soglia
minima. La revisione delle transizioni deve restare human-reviewed: chiarezza,
promessa mantenuta, non ripetizione e coerenza col target, senza modificare
ordine, membership o date.

## Fisica fondamentale e pubblicazioni temporali (Prompt 36–49)

Facts correnti forniti e coerenti con i test: Percorso #7, pillar articolo #23
in posizione 10, `scheduled` al 2026-09-07 13:30 UTC; membri #23/#15/#19/#35/
#36, posizioni valide, transizioni coerenti, nessun draft/review e tutte le
membership `is_primary=0` perché condivise. La decisione pre-lancio è
**APPROVA**, preservando integralmente l'ordine narrativo e senza pubblicazione
anticipata. `NOT READY` resta il fatto tecnico; la priorità operativa è attesa
programmata fino alla data del pillar.

La certificazione pubblica del Prompt 37 è `BLOCKED_BY_TIME` fino a dopo il
2026-09-07 13:30 UTC. Deve verificare HTTP, canonical, hero, sequenza, dati
strutturati, articoli condivisi, gap, subscription e assenza di titoli futuri.

Le date e gli slug production degli articoli #21 e #22 non sono contenuti nel
repository. I Prompt 46–49 sono quindi `BLOCKED_BY_PRODUCTION_FACTS`: nessuna
pre/post-certificazione può essere inventata. La procedura è pronta, ma va
eseguita prima/dopo gli orari reali con query aggregate/read-only, 404/200,
sitemap, canonical, fonti, Percorso, Concept, failed jobs e Social OFF.

## Decisione

Closeout tecnico: **PARTIAL**. Il codice è costruito e lo SHA è deployed/
verified, ma CWV, CTR, segmentazione e verifiche post-data non sono ancora
measured. Questo impedisce correttamente di aprire Homepage V2 o altre superfici
rinviate.
