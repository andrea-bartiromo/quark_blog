# Workspace Social Admin V1 — runbook redazionale

Percorso: **Admin → Distribuzione social → Bozze Social (workspace)**
(`/admin/distribuzione-social/bozze`).

## Creare una bozza

1. "+ Nuova bozza".
2. Seleziona l'articolo (può essere in qualunque stato editoriale, ma
   potrà essere programmato solo quando è pubblicato o programmato — vedi
   sotto).
3. Seleziona il canale: Facebook o LinkedIn. **Un canale per bozza**: per
   pubblicare sullo stesso articolo su entrambi, crea due bozze distinte
   con copy indipendente.
4. Copy opzionale: se lasciato vuoto, viene proposto un testo di partenza
   da titolo e sommario dell'articolo — sempre modificabile dopo.

## Revisionare

Dalla pagina di dettaglio della bozza, "Invia in revisione" (da bozza) o
"Riporta in bozza" (da revisionato, per correggere ancora). Il copy resta
modificabile in entrambi gli stati.

## Approvare

Da "revisionato", "Approva" — richiede un copy non vuoto. **Da questo
momento copy e URL personalizzato diventano di sola lettura**: per
modificarli serve prima "Riporta in revisione".

## Programmare

Da "approvato": imposta data e ora (sempre in Europe/Rome) nel modulo
"Contenuto e programmazione", poi "Programma". La programmazione richiede
tutte queste condizioni, verificate dal sistema:

- copy non vuoto;
- l'articolo collegato è pubblicato oppure programmato (mai bozza o in
  revisione);
- se l'articolo è programmato, l'orario Social deve essere successivo
  all'orario di pubblicazione dell'articolo;
- l'orario deve essere nel futuro;
- nessuna collisione con un'altra bozza già programmata sullo stesso
  canale nello stesso istante.

Se una qualunque condizione non è soddisfatta, la programmazione viene
rifiutata con un messaggio che spiega cosa correggere — mai un errore
tecnico.

## Annullare la programmazione

Dalla pagina della bozza programmata, "Annulla programmazione": torna ad
"approvato" e la data impostata viene cancellata (va reimpostata se si
vuole riprogrammare).

## Interpretare una collisione

Se il sistema segnala che l'orario scelto è già occupato sullo stesso
canale, **non viene mai spostata automaticamente nessuna data**: scegli
manualmente un orario diverso. L'indice delle bozze mostra un badge
"⚠ Collisione" su ogni riga programmata coinvolta.

## Cosa NON fa questa V1

- Non pubblica nulla: nessun post arriva mai davvero su Facebook o
  LinkedIn da qui.
- Non invia notifiche o email a nessuno.
- Non genera copy con l'intelligenza artificiale.
- Non aggiunge hashtag automaticamente.
- Non permette di eliminare una bozza (il ledger è pensato come storico,
  coerente con `social_publications`).
- Non collega in alcun modo questa bozza a un tentativo di invio reale:
  quando esisterà una fase provider, sarà un lavoro separato ed esplicito.
