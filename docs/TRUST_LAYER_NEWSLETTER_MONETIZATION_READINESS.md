# Newsletter trust e readiness monetizzazione (B-66/B-67)

Audit non implementativo. Nessun account, script, CMP, cookie banner o
link affiliato creato. Nessun template/modello modificato.

## B-66 — Newsletter trust e disclosure

Nessun campo `sponsor`/`affiliate`/`disclosure` esiste oggi su
`Newsletter`, `CommunicationTemplate`, `CommunicationCampaign` o modelli
collegati (verificato via ricerca diretta). La Newsletter è oggi
puramente editoriale.

Requisiti per una futura policy di disclosure (nessuna implementazione
qui):

1. Contenuto sponsorizzato futuro etichettato esplicitamente nel corpo
   dell'email, non solo in una pagina policy separata — coerente col
   principio di trasparenza già dichiarato in `/metodologia`
   ("Indipendenza editoriale").
2. Il consenso newsletter esistente (`CommunicationSubscriber` e flusso
   di conferma, fuori scope) è consenso alla ricezione, non a contenuto
   commerciale — non estendibile silenziosamente.

## B-67 — Matrice readiness monetizzazione

Nessuna matrice preesistente trovata nel repository — documento nuovo,
non un aggiornamento.

| Leva | Stato dati | Blocco |
|---|---|---|
| Sostegno volontario | Nessuna infrastruttura | — |
| Sponsor diretti | Nessun campo/flusso | Trust Layer V1 non ancora in produzione |
| Newsletter sponsor | Nessun campo (B-66) | Policy disclosure non scritta/pubblicata |
| Affiliazioni | Nessun link/tracciamento | — |
| Display advertising | `AdController`/`Ad` già esistenti (pregressi, fuori scope) | Verifica tecnica del blocco script pre-consenso non eseguita in questa sessione |

**Classificazione: NO-GO** su ogni nuova leva, per costruzione: mancano
Trust Layer V1 in produzione (PR aperte non mergiate), baseline CWV field
reale (solo dati lab con limiti d'ambiente, vedi B-46), policy di
disclosure pubblicata.
