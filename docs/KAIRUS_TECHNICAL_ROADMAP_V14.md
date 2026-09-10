# Kairus — Roadmap tecnica generale v14

Data: 2026-09-01. Questa nuova fotografia non modifica l'allegato v13.

## Principio di stato

`costruito ≠ CI green ≠ merged ≠ deployed ≠ verified ≠ measured`.

Ogni attività usa il livello massimo realmente provato. Gli SHA v13
`6d90a0f`, `64a2ad8` e `dd012c8` sono facts storici superati. Baseline main e
production certificata corrente: `84a5803222f940e71cad5e435c52b92171733991`.
Lo SHA storico della PR #505 non sostituisce lo squash/main.

## Agosto chiuso

PR #503 Dashboard Data Export, stabilizzazione temporale #504 e Percorsi
compatti/paginati con anteprima narrativa #505 risultano merged e deployed sullo
SHA corrente. I cantieri non vengono riaperti; restano solo verifiche runtime e
misurazioni esplicitamente indicate.

## Settembre

1. **same-SHA CI e resilienza** — mantenere workflow_dispatch/push main per
   Deploy Safety e Backup Restore; certificare lo SHA integrato, non gli head.
2. **misurazione** — CWV attendibile, seconda lettura e tassonomia CTR; nessuna
   ottimizzazione su campioni piccoli o dati mancanti.
3. **continuità di lettura** — benchmark TROVA e qualità transizioni; niente
   ranking automatico o contenuti generati.
4. **Fisica fondamentale** — nessuna anticipazione; certificazione pubblica
   dopo il 2026-09-07 13:30 UTC.
5. **closeout WCAG/SEO e Trust Layer** — runtime critical journeys, fonti
   pubbliche, pagine autore e policy human-reviewed.

## Stato reale

| Cantiere | built | merged | deployed | verified | measured |
|---|---|---|---|---|---|
| Data Export V1 | sì | sì | sì | statico/parziale | no |
| temporale baseline | sì | sì | sì | sì | n/a |
| `/percorsi` compatto + anteprime | sì | sì | sì | sì | no |
| categoria paginate | branch locale | no | no | test pending | no |
| fonti pubbliche | branch locale | no | no | test pending | no |
| Trust Layer autore | branch locale | no | no | test pending | no |
| benchmark TROVA | branch locale | no | no | test pending | no |
| certificazione scheduled | branch locale | no | no | test pending | no |
| privacy failure Newsletter | branch locale | no | no | test pending | no |
| Fisica fondamentale pubblico | pre-lancio | n/a | n/a | dopo data | no |
| CWV/CTR | strumenti parziali | n/a | n/a | no | no |

## Rinvii vincolanti

Homepage V2, PWA, account, Kairus+, multilingua, embeddings, pagine automatiche
e pubblicazione Social restano fuori perimetro. Workspace Social può avanzare
solo come bozza/preview senza provider. Nessun nuovo cantiere deve precedere i
gate same-SHA e le misure sufficienti.

## Criterio valore/rischio

Un intervento entra nel piano soltanto se riduce un rischio dimostrato oppure
produce valore editoriale misurabile. Branch piccoli, baseline diretta da main,
nessuna catena stacked e nessuna dichiarazione di successo prima del gate.
